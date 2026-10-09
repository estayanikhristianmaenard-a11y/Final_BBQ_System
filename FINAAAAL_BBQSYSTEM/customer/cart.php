<?php
/**
 * Customer cart — quantity controls, remove, reset order (with confirmation),
 * and a summary computed from MySQL. Checkout itself arrives in Phase 4.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

// --- Cart actions (POST) -----------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $productId = (int) ($_POST['product_id'] ?? 0);

    if (isset($_POST['reset'])) {
        cart_clear();
        flash('success', 'Your order has been reset. The cart is now empty.');
    } elseif (isset($_POST['remove'])) {
        cart_remove($productId);
        flash('success', 'Item removed from your cart.');
    } elseif (isset($_POST['step'])) {
        $step = (int) $_POST['step'];
        $qty  = cart_has($productId) + $step;
        if ($qty < 1) {
            $qty = 1; // keep at least 1; use Remove to delete the line
        }
        $result = cart_set_qty($productId, $qty);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
    } else {
        $result = cart_set_qty($productId, (int) ($_POST['qty'] ?? 1));
        flash($result['ok'] ? 'success' : 'error', $result['message']);
    }

    redirect(url('customer/cart.php'));
}

// --- Render ------------------------------------------------------------------
$view = cart_view();
foreach ($view['notices'] as $notice) {
    flash('warning', $notice);
}

$user = current_user();
$pageTitle = 'My Cart — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>My Cart</h1>
  <p class="muted">Review your order before checkout. Prices and stock are re-checked when you check out.</p>
</section>

<?php if (!$view['lines']): ?>
  <div class="empty-state card">
    <div class="empty-icon">&#128722;</div>
    <p><strong>Your cart is empty.</strong></p>
    <p class="muted">Add freshly grilled ihaw-ihaw from the menu.</p>
    <a class="btn btn-primary" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <div class="cart-lines">
      <?php foreach ($view['lines'] as $line):
          $image = $line['image'] ?: 'assets/images/products/placeholder.svg'; ?>
        <div class="cart-row<?= $line['buyable'] ? '' : ' unavailable' ?>">
          <img class="cart-thumb" src="<?= e(url($image)) ?>" alt="<?= e($line['name']) ?>">
          <div class="cart-info">
            <div class="cart-name">
              <?= e($line['name']) ?>
              <?php if (!$line['buyable']): ?><span class="badge badge-out"><?= e($line['reason']) ?></span><?php endif; ?>
            </div>
            <div class="cart-price"><?= e(peso($line['price'])) ?> each</div>
          </div>

          <form class="qty-form" method="post" action="<?= e(url('customer/cart.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $line['id'] ?>">
            <div class="stepper">
              <button class="step-btn" type="submit" name="step" value="-1" aria-label="Decrease quantity">&minus;</button>
              <input class="qty-field" type="number" name="qty" value="<?= (int) $line['qty'] ?>"
                     min="1" max="<?= max(1, (int) $line['stock']) ?>" data-autosubmit
                     aria-label="Quantity for <?= e($line['name']) ?>">
              <button class="step-btn" type="submit" name="step" value="1" aria-label="Increase quantity">&plus;</button>
            </div>
            <button class="btn btn-outline btn-sm" type="submit" name="remove" value="1">Remove</button>
          </form>

          <div class="cart-line-total"><?= $line['buyable'] ? e(peso($line['line_total'])) : '—' ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <aside class="cart-summary card">
      <h2>Order summary</h2>
      <div class="summary-row"><span>Items</span><strong><?= (int) $view['items'] ?></strong></div>
      <?php if ($view['unavailable']): ?>
        <div class="summary-row muted"><span>Unavailable</span><strong><?= (int) $view['unavailable'] ?></strong></div>
      <?php endif; ?>
      <div class="summary-row summary-total"><span>Subtotal</span><strong><?= e(peso($view['subtotal'])) ?></strong></div>
      <p class="hint">Final stock and prices are validated by the server when you check out.</p>

      <a class="btn btn-primary btn-block" href="<?= e(url('customer/checkout.php')) ?>">Proceed to Checkout</a>
      <p class="hint center">You'll confirm payment method on the next step.</p>

      <form method="post" action="<?= e(url('customer/cart.php')) ?>" data-confirm="Reset your entire order? This will remove all items from your cart.">
        <?= csrf_field() ?>
        <button class="btn btn-danger btn-block mt" type="submit" name="reset" value="1">Reset Order</button>
      </form>

      <a class="btn btn-outline btn-block mt" href="<?= e(url('customer/menu.php')) ?>">Add more items</a>
    </aside>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
