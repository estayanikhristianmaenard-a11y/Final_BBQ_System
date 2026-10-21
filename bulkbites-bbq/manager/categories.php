<?php
/**
 * Category management — Phase 7, MANAGER only.
 * Add / rename / re-sort / activate categories. Categories that still contain
 * products cannot be deleted (FK RESTRICT) — deactivate them instead, so a
 * menu tab never breaks.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

// --- POST actions (PRG) ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $back = url('manager/categories.php');
    $pdo = db();

    if ($action === 'create' || $action === 'update') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $sort = (int) ($_POST['sort_order'] ?? 0);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            flash('error', 'Category name must be 2–60 characters.');
            redirect($back);
        }
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $action === 'update' ? (int) ($_POST['category_id'] ?? 0) : 0]);
        if ($stmt->fetchColumn()) {
            flash('error', 'A category with that name already exists.');
            redirect($back);
        }

        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO categories (name, sort_order, is_active) VALUES (?, ?, 1)');
                $stmt->execute([$name, $sort]);
                flash('success', 'Category “' . $name . '” created.');
            } else {
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([$name, $sort, (int) ($_POST['category_id'] ?? 0)]);
                if ($stmt->rowCount() > 0) {
                    flash('success', 'Category “' . $name . '” updated.');
                } else {
                    flash('warning', 'Nothing changed — category not found or values identical.');
                }
            }
        } catch (PDOException $ex) {
            flash('error', 'Could not save the category.');
        }
        redirect($back);
    }

    if ($action === 'toggle') {
        $stmt = $pdo->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([(int) ($_POST['category_id'] ?? 0)]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error',
            $stmt->rowCount() > 0 ? 'Category visibility updated.' : 'Category not found.');
        redirect($back);
    }

    if ($action === 'delete') {
        $catId = (int) ($_POST['category_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$catId]);
        $n = (int) $stmt->fetchColumn();

        if ($n > 0) {
            // FK RESTRICT protects data; give the manager the real reason
            flash('error', 'This category still has ' . $n . ' product(s). Move or deactivate them first, or just hide the category.');
            redirect($back);
        }
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$catId]);
        flash($stmt->rowCount() > 0 ? 'success' : 'error',
            $stmt->rowCount() > 0 ? 'Category deleted.' : 'Category not found.');
        redirect($back);
    }

    flash('error', 'Unknown action.');
    redirect($back);
}

// --- GET data ----------------------------------------------------------------
$categories = db()->query(
    'SELECT c.id, c.name, c.sort_order, c.is_active, c.created_at,
            COUNT(p.id) AS products,
            COALESCE(SUM(p.is_active = 1), 0) AS products_active
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.sort_order, c.is_active, c.created_at
     ORDER BY c.sort_order, c.name'
)->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($categories as $row) {
    if ((int) $row['id'] === $editId) {
        $editing = $row;
        break;
    }
}

$pageTitle = 'Categories — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Category management</h1>
    <p class="muted">Organize product groups (Chicken, Pork, Hotdog…). Sort order controls how tabs appear to customers.</p>
  </div>
  <a class="btn btn-outline" href="<?= e(url('manager/products.php')) ?>">Products &amp; stock</a>
</section>
<?php require dirname(__DIR__) . '/includes/settings-nav.php'; ?>

<div class="crud-grid">
  <details class="card record-create" data-editing="<?= $editing ? 'true' : 'false' ?>" open>
    <summary><?= $editing ? 'Edit: ' . e($editing['name']) : 'Add a category' ?></summary>
    <form method="post" action="<?= e(url('manager/categories.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
      <?php if ($editing): ?><input type="hidden" name="category_id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

      <div class="form-group">
        <label for="c-name">Name</label>
        <input type="text" id="c-name" name="name" maxlength="60" required
               value="<?= e($editing['name'] ?? '') ?>" placeholder="e.g. Chicken">
      </div>
      <div class="form-group">
        <label for="c-sort">Sort order <span class="muted small">(lower numbers appear first)</span></label>
        <input type="number" id="c-sort" name="sort_order" min="0" max="999"
               value="<?= (int) ($editing['sort_order'] ?? count($categories) + 1) ?>">
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create category' ?></button>
        <?php if ($editing): ?>
          <a class="btn btn-outline" href="<?= e(url('manager/categories.php')) ?>">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </details>

  <div class="card">
    <h2 class="card-title">Categories — <?= count($categories) ?></h2>
    <table class="data-table">
      <thead>
        <tr><th class="num">#</th><th>Name</th><th class="num">Products</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $row): ?>
          <tr<?= !(int) $row['is_active'] ? ' class="row-muted"' : '' ?>>
            <td class="num muted"><?= (int) $row['sort_order'] ?></td>
            <td><strong><?= e($row['name']) ?></strong></td>
            <td class="num"><?= (int) $row['products'] ?> <span class="muted small">(<?= (int) $row['products_active'] ?> live)</span></td>
            <td>
              <?php if ((int) $row['is_active']): ?>
                <span class="badge badge-in">Visible</span>
              <?php else: ?>
                <span class="badge badge-out">Hidden</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <a class="btn btn-sm btn-outline" href="<?= e(url('manager/categories.php') . '?edit=' . (int) $row['id']) ?>">Edit</a>
                <form method="post" action="<?= e(url('manager/categories.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="category_id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-sm btn-outline" type="submit"><?= (int) $row['is_active'] ? 'Hide' : 'Show' ?></button>
                </form>
                <form method="post" action="<?= e(url('manager/categories.php')) ?>"
                      data-confirm="Delete category <?= e($row['name']) ?>? Only possible when it has no products.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
