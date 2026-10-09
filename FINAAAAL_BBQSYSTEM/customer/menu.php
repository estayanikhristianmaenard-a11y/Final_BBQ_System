<?php
/**
 * Customer menu — product cards from MySQL with search, category filtering,
 * stock badges, and add-to-cart (session cart; no checkout here).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

$qsArgs = [
    'q'   => trim((string) ($_GET['q'] ?? '')),
    'cat' => (int) ($_GET['cat'] ?? 0),
];
if (mb_strlen($qsArgs['q']) > 60) {
    $qsArgs['q'] = mb_substr($qsArgs['q'], 0, 60);
}
$qsSuffix = '?' . http_build_query(array_filter($qsArgs, fn($v) => $v !== '' && $v !== 0));

// --- Add to cart (POST) — server-validated against products + inventory -----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $result = cart_add((int) ($_POST['product_id'] ?? 0), (int) ($_POST['qty'] ?? 1));
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect(url('customer/menu.php' . $qsSuffix));
}

// --- Categories (active, with active-product counts) -------------------------
$catStmt = db()->query(
    'SELECT c.id, c.name, COUNT(p.id) AS products
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
     WHERE c.is_active = 1
     GROUP BY c.id, c.name, c.sort_order
     ORDER BY c.sort_order, c.name'
);
$categories = $catStmt->fetchAll();

$validCatIds = array_column($categories, 'id');
if (!in_array($qsArgs['cat'], $validCatIds, true)) {
    $qsArgs['cat'] = 0;
}
$qsSuffix = '?' . http_build_query(array_filter($qsArgs, fn($v) => $v !== '' && $v !== 0));

// --- Products (MySQL: name, price, image, stock) -----------------------------
$sql = 'SELECT p.id, p.name, p.description, p.price, p.image,
               i.quantity_available AS stock, i.is_available, i.low_stock_threshold
        FROM products p
        JOIN inventory i ON i.product_id = p.id
        JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1 AND c.is_active = 1';
$params = [];
if ($qsArgs['cat']) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $qsArgs['cat'];
}
if ($qsArgs['q'] !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . $qsArgs['q'] . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY c.sort_order, p.name';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$inCart = cart_raw();
$user = current_user();
$pageTitle = 'Menu — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>Our Menu</h1>
  <p class="muted">Freshly grilled Filipino ihaw-ihaw · prices straight from the store database</p>
</section>

<form class="menu-toolbar" method="get" action="<?= e(url('customer/menu.php')) ?>">
  <input type="search" name="q" value="<?= e($qsArgs['q']) ?>" placeholder="Search BBQ, isaw, hotdog..." aria-label="Search products">
  <?php if ($qsArgs['cat']): ?>
    <input type="hidden" name="cat" value="<?= (int) $qsArgs['cat'] ?>">
  <?php endif; ?>
  <button class="btn btn-dark" type="submit">Search</button>
  <?php if ($qsArgs['q'] || $qsArgs['cat']): ?>
    <a class="btn btn-outline" href="<?= e(url('customer/menu.php')) ?>">Clear</a>
  <?php endif; ?>
</form>

<nav class="cat-tabs" aria-label="Categories">
  <a class="cat-tab<?= $qsArgs['cat'] ? '' : ' active' ?>" href="<?= e(url('customer/menu.php' . ($qsArgs['q'] ? '?q=' . urlencode($qsArgs['q']) : ''))) ?>">All</a>
  <?php foreach ($categories as $category): ?>
    <a class="cat-tab<?= (int) $qsArgs['cat'] === (int) $category['id'] ? ' active' : '' ?>"
       href="<?= e(url('customer/menu.php' . '?' . http_build_query(['cat' => (int) $category['id']] + ($qsArgs['q'] ? ['q' => $qsArgs['q']] : [])))) ?>">
      <?= e($category['name']) ?> <span class="count"><?= (int) $category['products'] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if (!$products): ?>
  <div class="empty-state card">
    <p><strong>No products match your search.</strong></p>
    <a class="btn btn-outline" href="<?= e(url('customer/menu.php')) ?>">Show all products</a>
  </div>
<?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $product):
        $available = (int) $product['is_available'] === 1 && (int) $product['stock'] > 0;
        $lowStock  = $available && (int) $product['stock'] <= (int) $product['low_stock_threshold'];
        $cartQty    = (int) ($inCart[$product['id']] ?? 0);
        $image      = $product['image'] ?: 'assets/images/products/placeholder.svg';
    ?>
      <article class="product-card<?= $available ? '' : ' sold-out' ?>">
        <div class="product-image">
          <img src="<?= e(url($image)) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
          <?php if (!$available): ?><span class="ribbon">Sold out</span><?php endif; ?>
        </div>
        <div class="product-body">
          <div class="product-head">
            <h3><?= e($product['name']) ?></h3>
            <span class="price"><?= e(peso($product['price'])) ?></span>
          </div>
          <p class="product-desc"><?= e($product['description'] ?? '') ?></p>
          <div class="product-meta">
            <?php if (!$available): ?>
              <span class="badge badge-out">Sold out</span>
            <?php elseif ($lowStock): ?>
              <span class="badge badge-low">Low stock · <?= (int) $product['stock'] ?> left</span>
            <?php else: ?>
              <span class="badge badge-in"><?= (int) $product['stock'] ?> available</span>
            <?php endif; ?>
            <?php if ($cartQty > 0): ?>
              <span class="badge badge-incart">In cart ×<?= $cartQty ?></span>
            <?php endif; ?>
          </div>
          <form class="add-form" method="post" action="<?= e(url('customer/menu.php' . $qsSuffix)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <?php if ($available): ?>
              <label class="sr-only" for="qty-<?= (int) $product['id'] ?>">Quantity</label>
              <input class="qty-input" type="number" id="qty-<?= (int) $product['id'] ?>"
                     name="qty" value="1" min="1" max="<?= (int) $product['stock'] ?>">
              <button class="btn btn-primary" type="submit">Add to cart</button>
            <?php else: ?>
              <button class="btn" type="submit" disabled>Sold out</button>
            <?php endif; ?>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
