<?php
/**
 * Checkout — order review, payment method, and notes.
 * Submitting runs the full server-side transaction (includes/checkout.php).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

$checkoutToken = bin2hex(random_bytes(16));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    // One-time checkout token: blocks double-submits and replayed forms
    $postedToken = (string) ($_POST['checkout_token'] ?? '');
    $sessionToken = (string) ($_SESSION['checkout_token'] ?? '');
    unset($_SESSION['checkout_token']); // single-use no matter the outcome
    if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        flash('error', 'This checkout form expired or was already submitted. Please review your cart and try again.');
        redirect(url('customer/cart.php'));
    }

    $user = current_user();
    $result = place_order(
        (int) $user['id'],
        (string) ($_POST['payment_method'] ?? ''),
        (string) ($_POST['notes'] ?? '')
    );

    if ($result['ok']) {
        flash('success', 'Your order ' . $result['order_number'] . ' has been placed!');
        redirect(url('customer/order.php') . '?ref=' . urlencode($result['order_number']) . '&placed=1');
    }

    flash('error', $result['message']);
    redirect(url('customer/checkout.php'));
}

// --- GET: review screen ------------------------------------------------------
$view = cart_view();
$buyable = array_filter($view['lines'], fn($line) => $line['buyable']);

if (!$buyable) {
    flash('warning', 'Your cart is empty — add something delicious before checking out.');
    redirect(url('customer/menu.php'));
}

$_SESSION['checkout_token'] = $checkoutToken;
$payment = (string) ($_POST['payment_method'] ?? 'CASH');
$user = current_user();
$pageTitle = 'Checkout — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>Checkout</h1>
  <p class="muted">Review your order and choose how you'll pay. Prices and stock are re-validated by the server when you place the order.</p>
</section>

<?php if ($view['unavailable']): ?>
  <div class="flash flash-warning">
    <?= (int) $view['unavailable'] ?> item(s) in your cart are unavailable and are not included in this order.
  </div>
<?php endif; ?>

<div class="cart-layout">
  <div class="cart-lines">
    <div class="card">
      <h2 class="card-title">Order items</h2>
      <?php foreach ($buyable as $line): ?>
        <div class="checkout-line">
          <img class="cart-thumb" src="<?= e(url($line['image'] ?: 'assets/images/products/placeholder.svg')) ?>" alt="<?= e($line['name']) ?>">
          <div class="cart-info">
            <div class="cart-name"><?= e($line['name']) ?></div>
            <div class="cart-price"><?= e(peso($line['price'])) ?> × <?= (int) $line['qty'] ?></div>
          </div>
          <div class="cart-line-total"><?= e(peso($line['line_total'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card mt">
      <h2 class="card-title">Payment &amp; order information</h2>
      <form method="post" action="<?= e(url('customer/checkout.php')) ?>" id="checkout-form">
        <?= csrf_field() ?>
        <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">

        <fieldset class="pay-options">
          <legend>Payment method</legend>
          <label class="pay-option<?= $payment === 'CASH' ? ' selected' : '' ?>">
            <input type="radio" name="payment_method" value="CASH" <?= $payment === 'CASH' ? 'checked' : '' ?>>
            <span><strong>Cash</strong><br><span class="muted">Pay at pickup</span></span>
          </label>
          <label class="pay-option<?= $payment === 'GCASH' ? ' selected' : '' ?>">
            <input type="radio" name="payment_method" value="GCASH" <?= $payment === 'GCASH' ? 'checked' : '' ?>>
            <span><strong>GCash</strong><br><span class="muted">Pay when you pick up</span></span>
          </label>
        </fieldset>

        <div class="form-group mt">
          <label for="notes">Order notes <span class="muted">(optional)</span></label>
          <textarea id="notes" name="notes" maxlength="255" rows="3"
                    placeholder="Pickup time, spice level, bulk order details..."><?= e($_POST['notes'] ?? '') ?></textarea>
          <div class="hint">Up to 255 characters.</div>
        </div>
      </form>
    </div>
  </div>

  <aside class="cart-summary card">
    <h2>Order summary</h2>
    <div class="summary-row"><span>Items</span><strong><?= (int) $view['items'] ?></strong></div>
    <div class="summary-row summary-total"><span>Total</span><strong><?= e(peso($view['subtotal'])) ?></strong></div>
    <p class="hint">Total is computed from database prices. Stock is deducted only when the order succeeds.</p>
    <button class="btn btn-primary btn-block" type="submit" form="checkout-form">Place order — <?= e(peso($view['subtotal'])) ?></button>
    <a class="btn btn-outline btn-block mt" href="<?= e(url('customer/cart.php')) ?>">Back to cart</a>
  </aside>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
