<?php
/**
 * Order detail / confirmation — items, totals, payment info, and the
 * full status timeline from order_status_history. Ownership is enforced:
 * a customer can only open their own orders.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

$user = current_user();
$ref = trim((string) ($_GET['ref'] ?? ''));
$order = $ref !== '' ? find_customer_order($ref, (int) $user['id']) : null;

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found — BulkBites BBQ';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state card"><div class="empty-icon">&#128533;</div>'
        . '<p><strong>Order not found.</strong></p>'
        . '<p class="muted">It may not exist, or it belongs to another account.</p>'
        . '<a class="btn btn-primary" href="' . e(url('customer/orders.php')) . '">Back to my orders</a></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$items = order_items((int) $order['id']);
$history = order_history((int) $order['id']);
$justPlaced = isset($_GET['placed']);

$pageTitle = 'Order ' . e($order['order_number']) . ' — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($justPlaced): ?>
  <div class="flash flash-success">
    <strong>Order confirmed!</strong> Thank you — your order <strong><?= e($order['order_number']) ?></strong>
    has been sent to the grill. We'll update its status as it progresses.
  </div>
<?php endif; ?>

<section class="page-head order-head">
  <div>
    <h1><?= e($order['order_number']) ?></h1>
    <p class="muted">Placed <?= e(date('F j, Y · g:i A', strtotime($order['created_at']))) ?></p>
  </div>
  <div class="order-head-status"><?= order_status_badge($order['status']) ?></div>
</section>

<div class="cart-layout">
  <div class="cart-lines">
    <div class="card">
      <h2 class="card-title">Items</h2>
      <table class="data-table">
        <thead>
          <tr><th>Item</th><th class="num">Unit price</th><th class="num">Qty</th><th class="num">Subtotal</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?></td>
              <td class="num"><?= e(peso($item['unit_price'])) ?></td>
              <td class="num"><?= (int) $item['quantity'] ?></td>
              <td class="num"><?= e(peso($item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td colspan="3">Total</td>
            <td class="num"><?= e(peso($order['total'])) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="card mt">
      <h2 class="card-title">Order status</h2>
      <ol class="timeline">
        <?php foreach ($history as $entry): ?>
          <li class="timeline-item">
            <span class="timeline-dot"></span>
            <div class="timeline-body">
              <strong><?= e(order_status_label($entry['new_status'])) ?></strong>
              <span class="muted"> · <?= e(date('M j, g:i A', strtotime($entry['created_at']))) ?></span>
              <?php if ($entry['changed_by_name']): ?>
                <div class="muted small">by <?= e($entry['changed_by_name']) ?></div>
              <?php endif; ?>
              <?php if ($entry['remarks']): ?>
                <div class="small"><?= e($entry['remarks']) ?></div>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="hint">This page updates as staff advance your order — refresh for the latest status.</p>
    </div>
  </div>

  <aside class="cart-summary card">
    <h2>Order info</h2>
    <div class="summary-row"><span>Status</span><strong><?= e(order_status_label($order['status'])) ?></strong></div>
    <div class="summary-row"><span>Payment</span><strong><?= e($order['payment_method'] === 'GCASH' ? 'GCash' : 'Cash') ?></strong></div>
    <div class="summary-row summary-total"><span>Total</span><strong><?= e(peso($order['total'])) ?></strong></div>
    <?php if ($order['notes']): ?>
      <div class="order-notes"><strong>Notes:</strong> <?= e($order['notes']) ?></div>
    <?php endif; ?>
    <a class="btn btn-primary btn-block mt" href="<?= e(url('customer/receipt.php') . '?order=' . urlencode($order['order_number'])) ?>">&#128196; View / print receipt</a>
    <a class="btn btn-outline btn-block mt" href="<?= e(url('customer/orders.php')) ?>">All my orders</a>
    <a class="btn btn-primary btn-block mt" href="<?= e(url('customer/menu.php')) ?>">Order more</a>
  </aside>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
