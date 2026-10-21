<?php
/** Public menu: the store's small selection, quantity controls, and session cart. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_customer_or_guest();
$customerExperience = true;
$hasCartInteraction = true;

// --- Add to cart (POST) — server-validated against products + inventory -----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $result = cart_add((int) ($_POST['product_id'] ?? 0), (int) ($_POST['qty'] ?? 1));
    if (is_ajax()) {
        $view = cart_view();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok'=>$result['ok'], 'message'=>implode(' ', array_merge([$result['message']], $view['notices'])), 'cart'=>cart_feedback_state($view)]);
        exit;
    }
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect(url('customer/menu.php#product-' . (int) ($_POST['product_id'] ?? 0)));
}

// Keep the entire active selection visible; a small menu needs no filters.
$products = db()->query(
    'SELECT p.id, p.name, p.description, p.price, p.image,
            i.quantity_available AS stock, i.is_available, i.low_stock_threshold
     FROM products p
     JOIN inventory i ON i.product_id = p.id
     JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND c.is_active = 1
     ORDER BY c.sort_order, p.name'
)->fetchAll();

$cartPreview = cart_view();
foreach ($cartPreview['notices'] as $notice) {
    flash('warning', $notice);
}
$inCart = cart_raw();
$hasCartDock = cart_count() > 0;
$user = current_user();
$settings = store_settings();
$orderingOpen = store_is_open($settings);
$pageTitle = 'Menu — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="store-banner" aria-labelledby="store-title">
  <div class="banner-intro">
    <h1 id="store-title">Let's Ihaw!</h1>
    <p class="banner-tagline">Filipino barbecue for your next meal or merienda.</p>
  </div>
  <div class="banner-details">
    <p class="banner-location">Pickup at <?= e($settings['pickup_location']) ?></p>
    <p class="banner-delivery">Fixed <?= e(peso(delivery_fee_centavos()/100)) ?> delivery fee.<br>Within Pasig only.</p>
    <div class="store-info" role="status"><strong><?= e(store_status_text($settings)) ?></strong><?php if($hours=store_hours_text($settings)): ?><span><?= e($hours) ?></span><?php endif; ?><?php if($settings['contact_details']): ?><span>Contact: <?= e($settings['contact_details']) ?></span><?php endif; ?></div>
  </div>
  <div class="banner-grill">
    <?php $grillInteractive = true; $grillMenuLink = true; require dirname(__DIR__) . '/includes/grill-scene.php'; ?>
    <p class="banner-contact-note">Confirm your delivery address with the shop first.</p>
  </div>
</section>

<section class="menu-heading" id="menu" aria-labelledby="menu-title">
  <div><h2 id="menu-title">OUR MENU</h2><p>Prices per stick. Pickup or delivery at checkout.</p></div>
</section>

<?php if (!$products): ?>
  <div class="empty-state card">
    <p><strong>The menu is currently unavailable.</strong></p>
    <p class="muted">Please check back soon.</p>
  </div>
<?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $product):
        $available = (int) $product['is_available'] === 1 && (int) $product['stock'] > 0;
        $unavailableLabel = (int) $product['stock'] <= 0 ? 'Sold out' : 'Unavailable';
        $lowStock  = $available && (int) $product['stock'] <= (int) $product['low_stock_threshold'];
        $cartQty    = (int) ($inCart[$product['id']] ?? 0);
        $image      = $product['image'] ?: 'assets/images/products/placeholder.svg';
    ?>
      <article id="product-<?= (int) $product['id'] ?>" class="product-card<?= $available ? '' : ' sold-out' ?>" data-product-id="<?= (int) $product['id'] ?>">
        <div class="product-image">
          <img src="<?= e(url($image)) ?>" alt="<?= e($product['name']) ?>" width="640" height="640" loading="lazy" decoding="async">
        </div>
        <div class="product-body">
          <div class="product-head">
            <h3><?= e($product['name']) ?></h3>
            <span class="price"><?= e(peso($product['price'])) ?></span>
          </div>
          <?php if (trim((string) ($product['description'] ?? '')) !== ''): ?>
            <p class="product-desc"><?= e($product['description']) ?></p>
          <?php endif; ?>
          <div class="product-meta">
            <?php if ($lowStock): ?>
              <span class="badge badge-low">Only <?= (int) $product['stock'] ?> left</span>
            <?php endif; ?>
              <span class="badge badge-incart" data-in-cart<?= $cartQty ? '' : ' hidden' ?>>In cart ×<?= $cartQty ?></span>
          </div>
          <form class="add-form" data-cart-form method="post" action="<?= e(url('customer/menu.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <?php if ($available && $orderingOpen): ?>
              <label class="sr-only" for="qty-<?= (int) $product['id'] ?>">Quantity for <?= e($product['name']) ?></label>
              <div class="stepper menu-stepper">
              <button class="step-btn" type="button" data-quantity-step="-1" aria-label="Decrease quantity for <?= e($product['name']) ?>" hidden>&minus;</button>
              <input class="qty-input" type="number" id="qty-<?= (int) $product['id'] ?>"
                     name="qty" value="1" min="1" max="<?= (int) $product['stock'] ?>">
              <button class="step-btn" type="button" data-quantity-step="1" aria-label="Increase quantity for <?= e($product['name']) ?>" hidden>&plus;</button>
              </div>
              <button class="btn btn-primary" type="submit" aria-label="Add <?= e($product['name']) ?> to cart">Add to cart</button>
            <?php else: ?>
              <button class="btn" type="submit" disabled><?= e($available ? 'Ordering closed' : $unavailableLabel) ?></button>
            <?php endif; ?>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<a class="cart-dock" data-menu-cart-dock href="<?= e(url('customer/cart.php')) ?>"<?= cart_count() ? '' : ' hidden' ?>>
  <span><strong data-cart-dock-count><?= cart_count() ?> in your cart</strong><small data-cart-dock-total><?= e(peso($cartPreview['subtotal'])) ?> <?= $cartPreview['unavailable'] ? 'available subtotal' : 'subtotal' ?></small></span>
  <span>View cart <span aria-hidden="true">&rarr;</span></span>
</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>
