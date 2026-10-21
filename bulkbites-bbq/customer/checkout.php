<?php
/**
 * Checkout — order review, payment method, and notes.
 * Submitting runs the full server-side transaction (includes/checkout.php).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!current_user()) {
    $_SESSION['customer_login_next'] = 'customer/checkout.php';
    flash('info', 'Verify your email to continue to checkout. Your cart is saved in this browser.');
    redirect(url('login.php'));
}
require_role('CUSTOMER');
$customerExperience = true;

$checkoutToken = bin2hex(random_bytes(16));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $_SESSION['pickup_name']=is_string($_POST['pickup_name'] ?? null) ? mb_substr($_POST['pickup_name'],0,80) : '';
    $_SESSION['pickup_contact']=is_string($_POST['pickup_contact'] ?? null) ? mb_substr($_POST['pickup_contact'],0,30) : '';

    $_SESSION['checkout_draft'] = [
        'fulfillment_type' => is_string($_POST['fulfillment_type'] ?? null) ? $_POST['fulfillment_type'] : 'PICKUP',
        'delivery_address' => is_string($_POST['delivery_address'] ?? null) ? mb_substr($_POST['delivery_address'],0,500) : '',
        'payment_method' => is_string($_POST['payment_method'] ?? null) ? $_POST['payment_method'] : 'CASH',
        'notes' => is_string($_POST['notes'] ?? null) ? mb_substr($_POST['notes'],0,255) : '',
    ];
    if (isset($_POST['review_order'])) { redirect(url('customer/checkout.php')); }
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
        (string) ($_POST['notes'] ?? ''),
        is_string($_POST['pickup_name'] ?? null) ? $_POST['pickup_name'] : '',
        is_string($_POST['pickup_contact'] ?? null) ? $_POST['pickup_contact'] : '',
        is_string($_POST['fulfillment_type'] ?? null) ? $_POST['fulfillment_type'] : '',
        is_string($_POST['delivery_address'] ?? null) ? $_POST['delivery_address'] : ''
    );

    if ($result['ok']) {
        $_SESSION['checkout_clear_draft'] = 'bulkbites-checkout-' . (int)$user['id'];
        unset($_SESSION['checkout_draft']);
        flash('success', 'Your order ' . $result['order_number'] . ' has been placed!');
        if (($_POST['payment_method'] ?? '')==='DEMO') { redirect(url('customer/demo-payment.php').'?ref='.rawurlencode($result['order_number'])); }
        if (($_POST['payment_method'] ?? '') === 'GCASH') {
            $checkout = paymongo_create_checkout((int) $result['order_id'], (int) $user['id']);
            if ($checkout['ok'] && isset($checkout['checkout_url'])) { redirect($checkout['checkout_url']); }
            flash('warning', $checkout['message'] ?? 'Online checkout could not be started. Check your order before retrying.');
        }
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
$draft = $_SESSION['checkout_draft'] ?? [];
$fulfillment = ($draft['fulfillment_type'] ?? 'PICKUP') === 'DELIVERY' ? 'DELIVERY' : 'PICKUP';
$deliveryFee = $fulfillment === 'DELIVERY' ? delivery_fee_centavos()/100 : 0;
$checkoutTotal = $view['subtotal'] + $deliveryFee;
$payment = (string) ($draft['payment_method'] ?? 'CASH');
$user = current_user();
$settings = store_settings();
$orderingOpen = store_is_open($settings);
$pageTitle = 'Checkout — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>Checkout</h1>
  <p class="muted">Choose pickup or delivery, then review your total.</p>
</section>
<p class="notice" role="status" data-checkout-feedback hidden></p>
<?php if (!$orderingOpen): ?><p class="notice" role="status"><?= e(store_status_text($settings)) ?>. Your cart is saved for later.</p><?php endif; ?>

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
      <form method="post" action="<?= e(url('customer/checkout.php')) ?>" id="checkout-form" data-order-loading data-draft-key="bulkbites-checkout-<?= (int)$user['id'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">
        <fieldset class="pay-options">
          <legend>How would you like your order?</legend>
          <label class="pay-option"><input type="radio" name="fulfillment_type" value="PICKUP"<?= $fulfillment==='PICKUP'?' checked':'' ?>><span><strong>Pickup</strong><br><span class="muted">Collect from the shop · no fee</span></span></label>
          <label class="pay-option"><input type="radio" name="fulfillment_type" value="DELIVERY"<?= $fulfillment==='DELIVERY'?' checked':'' ?>><span><strong>Delivery</strong><br><span class="muted">Fixed <?= e(peso(delivery_fee_centavos()/100)) ?> fee<br>Within Pasig only</span></span></label>
        </fieldset>
        <div class="form-group mt">
          <label for="delivery-address">Delivery address <span class="muted">(delivery only)</span></label>
          <textarea id="delivery-address" name="delivery_address" rows="3" maxlength="500" autocomplete="street-address" placeholder="House / unit, street, barangay, city, and nearby landmark"><?= e($draft['delivery_address'] ?? '') ?></textarea>
        </div>
        <noscript><p class="hint">After changing pickup or delivery, update the total before placing your order.</p><button class="btn btn-outline" type="submit" name="review_order" value="1" formnovalidate>Update total</button></noscript>
        <div class="form-group">
          <label for="pickup-name">Customer name</label>
          <input id="pickup-name" type="text" name="pickup_name" maxlength="80" minlength="2" autocomplete="name" value="<?= e($_SESSION['pickup_name'] ?? $user['full_name']) ?>" required>
        </div>

        <fieldset class="pay-options">
          <legend>Payment method</legend>
          <label class="pay-option<?= $payment === 'CASH' ? ' selected' : '' ?>">
            <input type="radio" name="payment_method" value="CASH" <?= $payment === 'CASH' ? 'checked' : '' ?>>
            <span><strong>Cash</strong><br><span class="muted"><span data-cash-help><?= $fulfillment==='DELIVERY' ? 'Cash on delivery' : 'Pay at pickup' ?></span></span></span>
          </label>
          <?php if(demo_payment_enabled()): ?>
          <label class="pay-option<?= $payment==='DEMO'?' selected':'' ?>">
            <input type="radio" name="payment_method" value="DEMO"<?= $payment==='DEMO'?' checked':'' ?>>
            <span><strong>Demo payment</strong><br><span class="muted">Simulate payment for the presentation. No real money.</span></span>
          </label>
          <?php else: ?>
          <label class="pay-option<?= $payment === 'GCASH' ? ' selected' : '' ?>">
            <input type="radio" name="payment_method" value="GCASH" <?= !paymongo_ready() ? 'disabled' : ($payment === 'GCASH' ? 'checked' : '') ?>>
            <span><strong>GCash / card (TEST)</strong><br><span class="muted"><?= paymongo_ready() ? 'Simulated payment before acceptance; no real money' : 'Test checkout is not configured; choose cash for now' ?></span></span>
          </label>
          <?php endif; ?>
        </fieldset>

        <details class="checkout-optional" data-checkout-optional<?= $fulfillment==='DELIVERY'?' open':'' ?>><summary>Contact and additional details</summary>
        <div class="form-group">
          <label for="pickup-contact">Contact number <span class="muted">(required for delivery)</span></label>
          <input id="pickup-contact" name="pickup_contact" type="tel" maxlength="30" autocomplete="tel" value="<?= e($_SESSION['pickup_contact'] ?? '') ?>">
        </div>
        <div class="form-group mt">
          <label for="notes">Order notes <span class="muted">(optional)</span></label>
          <textarea id="notes" name="notes" maxlength="255" rows="3"
                    placeholder="Special requests or additional instructions..."><?= e($draft['notes'] ?? '') ?></textarea>

        </div>
        </details>
      </form>
    </div>
  </div>

  <aside class="cart-summary card">
    <h2>Order summary</h2>
    <div class="summary-row"><span>Items</span><strong><?= (int) $view['items'] ?></strong></div>
    <div class="summary-row"><span>Food subtotal</span><strong><?= e(peso($view['subtotal'])) ?></strong></div>
    <div class="summary-row"><span>Delivery fee</span><strong data-delivery-fee><?= e(peso($deliveryFee)) ?></strong></div>
    <div class="summary-row summary-total"><span>Total</span><strong data-checkout-total><?= e(peso($checkoutTotal)) ?></strong></div>
    <p class="hint">Check your quantities and total before placing your order.</p>
    <p class="hint" data-pickup-guidance<?= $fulfillment==='DELIVERY'?' hidden':'' ?>>Pickup: <?= e($settings['pickup_location']) ?>. Wait for Ready before collecting.</p>
    <div data-delivery-guidance<?= $fulfillment==='PICKUP'?' hidden':'' ?>><p class="hint"><?= e(delivery_area_text($settings)) ?></p><p class="hint">Keep your phone available. Travel time is separate from preparation.</p></div>
    <p class="hint">Preparation: about <?= (int)$settings['prep_minutes'] ?> minutes after acceptance.</p>
    <noscript><p class="hint"><?= e(delivery_area_text($settings)) ?></p></noscript>
    <button class="btn btn-primary btn-block" type="submit" form="checkout-form" data-place-order data-subtotal-centavos="<?= money_centavos($view['subtotal']) ?>" data-fee-centavos="<?= delivery_fee_centavos() ?>"<?= $orderingOpen ? '' : ' disabled' ?>><?= $orderingOpen ? 'Place order — ' . e(peso($checkoutTotal)) : 'Ordering is closed' ?></button>
    <a class="btn btn-outline btn-block mt" href="<?= e(url('customer/cart.php')) ?>">Back to cart</a>
    <details class="checkout-help"><summary>Receipts and order limits</summary>
      <p>Your receipt is emailed after payment is confirmed. For cash, staff records payment after receiving it at pickup or delivery.</p>
      <p>Up to <?= (int)$settings['max_item_qty'] ?> of each item, <?= (int)$settings['max_order_qty'] ?> pieces total. Cancel before acceptance from your order page.</p>
    </details>
  </aside>
</div>
<script src="<?= e(url('assets/js/checkout-fulfillment.js')) ?>?v=<?= (int)filemtime(dirname(__DIR__).'/assets/js/checkout-fulfillment.js') ?>" defer></script>
<?php require __DIR__ . '/../includes/order-loading.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
