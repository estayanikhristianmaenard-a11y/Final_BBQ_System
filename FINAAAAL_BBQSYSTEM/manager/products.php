<?php
/**
 * Product management — Phase 7, MANAGER only (architecture §3 matrix).
 * Add / edit / activate / deactivate products with category, price, description,
 * and optional image upload (MIME-validated, stored in uploads/ — architecture
 * risk #15). Products are DEACTIVATED, never deleted, so old receipts stay
 * intact (order_items keeps its own name/price snapshot).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

$user = current_user();

// --- Image upload helper (extension + finfo MIME + getimagesize, 2 MB cap) ---
function product_image_error(array $file): ?string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int) $file['size'] > 2 * 1024 * 1024) {
        return 'Image must be 2 MB or smaller.';
    }
    if ($err !== UPLOAD_ERR_OK) {
        return 'Upload failed (error ' . $err . ').';
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($map[$mime])) {
        return 'Only JPG, PNG, WEBP, or GIF images are allowed.';
    }
    if (@getimagesize($file['tmp_name']) === false) {
        return 'That file is not a valid image.';
    }
    return null;
}

function product_image_store(array $file): ?string
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($map[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return null;
    }
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return null;
    }
    // Deny script execution inside uploads/ (written once)
    if (!is_file($dir . '/.htaccess')) {
        file_put_contents(
            $dir . '/.htaccess',
            "Options -Indexes\n"
            . "<FilesMatch \"\\.(?i:php[0-9]?|phtml|phar|pl|py|cgi|sh|htaccess)$\">\n  Require all denied\n</FilesMatch>\n"
            . "RemoveHandler .php .phtml .phar\nRemoveType .php .phtml\n"
            . "<IfModule php_module>\n  php_flag engine off\n</IfModule>\n"
        );
    }
    $name = bin2hex(random_bytes(10)) . '.' . $map[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return null;
    }
    return 'uploads/' . $name;
}

/** Validate shared product fields. Returns [data, error]. */
function product_validate(array $post): array
{
    $name = trim((string) ($post['name'] ?? ''));
    $desc = trim((string) ($post['description'] ?? ''));
    $priceRaw = trim((string) ($post['price'] ?? ''));
    $catId = (int) ($post['category_id'] ?? 0);

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        return [[], 'Product name must be 2–100 characters.'];
    }
    if ($desc !== '' && mb_strlen($desc) > 255) {
        return [[], 'Description must be 255 characters or fewer.'];
    }
    if (!is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw > 99999999.99) {
        return [[], 'Price must be a valid amount of ₱0.00 or more.'];
    }
    $cat = db()->prepare('SELECT id FROM categories WHERE id = ?');
    $cat->execute([$catId]);
    if (!$cat->fetchColumn()) {
        return [[], 'Please choose a valid category.'];
    }
    return [[
        'name'        => $name,
        'description' => $desc,
        'price'       => number_format((float) $priceRaw, 2, '.', ''),
        'category_id' => $catId,
    ], null];
}

// --- POST actions (PRG) ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $back = url('manager/products.php');

    if ($action === 'create' || $action === 'update') {
        [$data, $error] = product_validate($_POST);
        $imageError = product_image_error($_FILES['image'] ?? []);

        if ($error === null && $imageError === null && $data) {
            $imagePath = product_image_store($_FILES['image'] ?? []);
            $pdo = db();
            try {
                $pdo->beginTransaction();

                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO products (category_id, name, description, price, image, is_active)
                         VALUES (?, ?, ?, ?, ?, 1)'
                    );
                    $stmt->execute([$data['category_id'], $data['name'], $data['description'] !== '' ? $data['description'] : null, $data['price'], $imagePath]);
                    $productId = (int) $pdo->lastInsertId();

                    // Initial stock row (stock lives ONLY in inventory)
                    $stock = max(0, (int) ($_POST['initial_stock'] ?? 0));
                    $threshold = max(0, (int) ($_POST['low_stock_threshold'] ?? 10));
                    $stmt = $pdo->prepare(
                        'INSERT INTO inventory (product_id, quantity_available, unit, low_stock_threshold, is_available, updated_by)
                         VALUES (?, ?, "pcs", ?, 1, ?)'
                    );
                    $stmt->execute([$productId, $stock, $threshold, (int) $user['id']]);
                    if ($stock > 0) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO inventory_movements (product_id, movement_type, quantity_change, performed_by, remarks)
                             VALUES (?, "RESTOCK", ?, ?, "Initial stock on product creation")'
                        );
                        $stmt->execute([$productId, $stock, (int) $user['id']]);
                    }
                    flash('success', 'Product “' . $data['name'] . '” created with ' . $stock . ' pcs initial stock.');
                } else {
                    $productId = (int) ($_POST['product_id'] ?? 0);
                    $stmt = $pdo->prepare('SELECT id, image FROM products WHERE id = ?');
                    $stmt->execute([$productId]);
                    $existing = $stmt->fetch();
                    if (!$existing) {
                        throw new RuntimeException('Product not found.');
                    }
                    // Keep the old image unless a new one was uploaded
                    $finalImage = $imagePath ?? $existing['image'];
                    $stmt = $pdo->prepare(
                        'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image = ? WHERE id = ?'
                    );
                    $stmt->execute([
                        $data['category_id'], $data['name'],
                        $data['description'] !== '' ? $data['description'] : null,
                        $data['price'], $finalImage, $productId,
                    ]);
                    flash('success', 'Product “' . $data['name'] . '” updated.');
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'Could not save the product. Nothing was changed.');
            }
        } else {
            flash('error', $error ?? $imageError ?? 'Invalid product data.');
        }
        redirect($back);
    }

    if ($action === 'toggle') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $stmt = db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$productId]);
        if ($stmt->rowCount() > 0) {
            flash('success', 'Product visibility updated.');
        } else {
            flash('error', 'Product not found.');
        }
        redirect($back);
    }

    flash('error', 'Unknown action.');
    redirect($back);
}

// --- GET data ----------------------------------------------------------------
$products = db()->query(
    'SELECT p.id, p.name, p.description, p.price, p.image, p.is_active, p.created_at,
            c.name AS category_name, c.id AS category_id,
            i.quantity_available, i.unit, i.low_stock_threshold, i.is_available,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi
              WHERE oi.product_id = p.id
                AND oi.order_id IN (SELECT id FROM orders WHERE status <> "CANCELLED")) AS units_sold,
            (SELECT COALESCE(SUM(oi.line_total), 0) FROM order_items oi
              WHERE oi.product_id = p.id
                AND oi.order_id IN (SELECT id FROM orders WHERE status <> "CANCELLED")) AS revenue
     FROM products p
     JOIN categories c ON c.id = p.category_id
     LEFT JOIN inventory i ON i.product_id = p.id
     ORDER BY p.is_active DESC, c.sort_order, p.name'
)->fetchAll();

$categories = db()->query(
    'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name'
)->fetchAll();

$images = array_map('basename', glob(dirname(__DIR__) . '/assets/images/products/*.svg') ?: []);
sort($images);

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    foreach ($products as $row) {
        if ((int) $row['id'] === $editId) {
            $editing = $row;
            break;
        }
    }
    if (!$editing) {
        flash('warning', 'Product not found.');
        redirect(url('manager/products.php'));
    }
}

$pageTitle = 'Products — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Product management</h1>
    <p class="muted">Add, edit, price, and activate products · changes go live on the menu immediately.</p>
  </div>
  <a class="btn btn-outline" href="<?= e(url('manager/categories.php')) ?>">&#128193; Categories</a>
</section>

<div class="crud-grid">
  <div class="card">
    <h2 class="card-title"><?= $editing ? 'Edit: ' . e($editing['name']) : 'Add a product' ?></h2>
    <form method="post" action="<?= e(url('manager/products.php')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="product_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

      <div class="form-group">
        <label for="p-name">Name</label>
        <input type="text" id="p-name" name="name" maxlength="100" required
               value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. BBQ">
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label for="p-cat">Category</label>
          <select id="p-cat" name="category_id" required>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int) $cat['id'] ?>"
                <?= (int) ($editing['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                <?= e($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="p-price">Price (₱)</label>
          <input type="number" id="p-price" name="price" min="0" max="99999999.99" step="0.01" required
                 value="<?= e($editing['price'] ?? '') ?>" placeholder="30.00">
        </div>
      </div>

      <div class="form-group">
        <label for="p-desc">Description</label>
        <textarea id="p-desc" name="description" maxlength="255" rows="2"
                  placeholder="Short menu description"><?= e($editing['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label for="p-image">Image <span class="muted small">(JPG/PNG/WEBP/GIF, max 2 MB — optional)</span></label>
        <input type="file" id="p-image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($editing['image'])): ?>
          <p class="muted small" style="margin:6px 0 0">
            Current: <img class="thumb" src="<?= e(url($editing['image'])) ?>" alt="" style="height:26px;vertical-align:middle">
            <code><?= e($editing['image']) ?></code>
          </p>
        <?php endif; ?>
        <?php if ($images): ?>
          <p class="muted small" style="margin:6px 0 0">
            Bundled art: <?= e(implode(', ', array_map(fn($n) => basename($n, '.svg'), $images))) ?> —
            upload a photo to use your own image.
          </p>
        <?php endif; ?>
      </div>

      <?php if (!$editing): ?>
        <div class="form-row-2">
          <div class="form-group">
            <label for="p-stock">Initial stock (pcs)</label>
            <input type="number" id="p-stock" name="initial_stock" min="0" max="999999" value="0">
          </div>
          <div class="form-group">
            <label for="p-threshold">Low-stock threshold</label>
            <input type="number" id="p-threshold" name="low_stock_threshold" min="0" max="999999" value="10">
          </div>
        </div>
      <?php endif; ?>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create product' ?></button>
        <?php if ($editing): ?>
          <a class="btn btn-outline" href="<?= e(url('manager/products.php')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 class="card-title">Catalogue — <?= count($products) ?> product(s)</h2>
    <div class="queue-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th class="num">Price</th>
            <th class="num">Stock</th>
            <th class="num">Sold</th>
            <th class="num">Revenue</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $row): ?>
            <tr<?= !(int) $row['is_active'] ? ' class="row-muted"' : '' ?>>
              <td>
                <img class="thumb" src="<?= e(url($row['image'] ?: 'assets/images/products/placeholder.svg')) ?>" alt="">
                <strong><?= e($row['name']) ?></strong>
                <?php if ($row['is_available'] !== null && !(int) $row['is_available']): ?><span class="badge badge-out">Sold out</span><?php endif; ?>
              </td>
              <td class="muted"><?= e($row['category_name']) ?></td>
              <td class="num"><?= e(peso($row['price'])) ?></td>
              <td class="num">
                <?php
                  $qty = (int) $row['quantity_available'];
                  $thr = (int) $row['low_stock_threshold'];
                  $state = stock_state($qty, $thr, (int) $row['is_available']);
                ?>
                <strong><?= $qty ?></strong> <?= e($row['unit'] ?? 'pcs') ?>
                <?php if ($state === 'LOW'): ?><span class="badge badge-low">Low</span>
                <?php elseif ($state === 'OUT'): ?><span class="badge badge-out">Out</span><?php endif; ?>
              </td>
              <td class="num"><?= (int) $row['units_sold'] ?></td>
              <td class="num"><?= e(peso($row['revenue'])) ?></td>
              <td>
                <?php if ((int) $row['is_active']): ?>
                  <span class="badge badge-in">Active</span>
                <?php else: ?>
                  <span class="badge badge-out">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-sm btn-outline" href="<?= e(url('manager/products.php') . '?edit=' . (int) $row['id']) ?>">Edit</a>
                  <form method="post" action="<?= e(url('manager/products.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-sm <?= (int) $row['is_active'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"
                      data-confirm="<?= (int) $row['is_active'] ? 'Hide ' . e($row['name']) . ' from the menu? Existing orders are unaffected.' : 'Put ' . e($row['name']) . ' back on the menu?' ?>">
                      <?= (int) $row['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
