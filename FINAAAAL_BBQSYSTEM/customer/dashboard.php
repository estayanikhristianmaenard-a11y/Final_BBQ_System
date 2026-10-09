<?php
/**
 * Customer dashboard — role-gated (CUSTOMER only).
 * Shows a live cart snapshot and quick links; orders/receipts/notifications
 * panels light up in Phases 4–5.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

$user = current_user();
$view = cart_view();
$ordersStmt = db()->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS spent FROM orders WHERE customer_id = ?');
$ordersStmt->execute([(int) $user['id']]);
$ordersStat = $ordersStmt->fetch() ?: ['cnt' => 0, 'spent' => 0];
$pageTitle = 'My Dashboard — BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-head">
  <h1>Hello, <?= e($user['full_name']) ?>! &#128075;</h1>
  <p class="muted">Order your favorite ihaw-ihaw and skip the line.</p>
</section>

<div class="dash-grid">
  <a class="dash-card" href="<?= e(url('customer/menu.php')) ?>">
    <div class="dash-icon">&#128293;</div>
    <h3>Browse Menu</h3>
    <p>BBQ, Isaw Manok, Bituka, Tenga, Balunan, and Hotdog — freshly grilled.</p>
    <span class="dash-cta">Order now &rarr;</span>
  </a>

  <a class="dash-card" href="<?= e(url('customer/cart.php')) ?>">
    <div class="dash-icon">&#128722;</div>
    <h3>My Cart</h3>
    <p>
      <?php if ($view['items'] > 0): ?>
        <strong><?= (int) $view['items'] ?> item(s)</strong> · <?= e(peso($view['subtotal'])) ?> ready for checkout.
      <?php else: ?>
        Your cart is empty — add something delicious.
      <?php endif; ?>
    </p>
    <span class="dash-cta">View cart &rarr;</span>
  </a>

  <a class="dash-card pending" href="<?= e(url('customer/orders.php')) ?>">
    <div class="dash-icon">&#128241;</div>
    <h3>My Orders</h3>
    <p>
      <?php if ((int) $ordersStat['cnt'] > 0): ?>
        <strong><?= (int) $ordersStat['cnt'] ?> order(s)</strong> placed · <?= e(peso($ordersStat['spent'])) ?> total.
      <?php else: ?>
        No orders yet — your history and status tracking appear here.
      <?php endif; ?>
    </p>
    <span class="dash-cta">View orders &rarr;</span>
  </a>
</div>

<div class="flash flash-info" style="margin-top:20px">
  <strong>Phase 4 (Ordering)</strong> — checkout runs a full database transaction: prices and stock are
  re-validated server-side, inventory is deducted, and your order number is generated. Receipts and
  notifications arrive in Phase 5.
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
