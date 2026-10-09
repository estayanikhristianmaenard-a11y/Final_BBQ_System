<?php
/**
 * My Orders — order history with live status badges.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

$user = current_user();
$stmt = db()->prepare(
    'SELECT o.id, o.order_number, o.status, o.total, o.payment_method, o.created_at,
            COALESCE(SUM(oi.quantity), 0) AS item_count, COUNT(oi.id) AS line_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.customer_id = ?
     GROUP BY o.id, o.order_number, o.status, o.total, o.payment_method, o.created_at
     ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->execute([(int) $user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <h1>My Orders</h1>
  <p class="muted">Every order you've placed, with its live status.</p>
</section>

<?php if (!$orders): ?>
  <div class="empty-state card">
    <div class="empty-icon">&#128241;</div>
    <p><strong>You haven't placed any orders yet.</strong></p>
    <p class="muted">Your order history will appear here after your first order.</p>
    <a class="btn btn-primary" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
  </div>
<?php else: ?>
  <div class="order-list">
    <?php foreach ($orders as $order): ?>
      <a class="order-card" href="<?= e(url('customer/order.php') . '?ref=' . urlencode($order['order_number'])) ?>">
        <div class="order-card-main">
          <div class="order-no"><?= e($order['order_number']) ?></div>
          <div class="muted"><?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></div>
          <div class="muted">
            <?= (int) $order['item_count'] ?> item(s) in <?= (int) $order['line_count'] ?> line(s)
            · <?= e($order['payment_method']) ?>
          </div>
        </div>
        <div class="order-card-side">
          <div class="order-total"><?= e(peso($order['total'])) ?></div>
          <?= order_status_badge($order['status']) ?>
          <span class="order-view">View &rarr;</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
