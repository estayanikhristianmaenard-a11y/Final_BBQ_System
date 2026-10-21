<?php
/**
 * Digital receipt — architecture §11.
 * Rendered from order + order_items snapshots (never current product data),
 * so old receipts stay correct even after a price change.
 * Customers may open only their own receipts; staff/manager may view any.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER', 'STAFF', 'MANAGER');

$user = current_user();
$ref = trim((string) ($_GET['order'] ?? ''));

$stmt = db()->prepare(
    'SELECT r.receipt_number, r.issued_at, r.total AS receipt_total, r.payment_method AS receipt_payment,
            o.id AS order_id, o.order_number, o.status, o.subtotal, o.total, o.payment_method, o.notes, o.created_at,
            o.customer_id, o.payment_status, o.payment_is_test, o.fulfillment_type, o.delivery_fee, o.delivery_address, o.pickup_contact, o.pickup_name, o.pickup_location, u.full_name, c.display_name
     FROM receipts r
     JOIN orders o ON o.id = r.order_id
     JOIN users u ON u.id = o.customer_id
     LEFT JOIN customers c ON c.user_id = o.customer_id
     WHERE o.order_number = ?'
);
$stmt->execute([$ref]);
$receipt = $stmt->fetch();

// Ownership: customers see only their own orders (404 hides existence)
if ($receipt && $user['role'] === 'CUSTOMER' && (int) $receipt['customer_id'] !== (int) $user['id']) {
    $receipt = null;
}

if (!$receipt) {
    http_response_code(404);
    $pageTitle = 'Receipt not found — BulkBites BBQ';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state card">'
        . '<p><strong>Receipt not found.</strong></p>'
        . '<p class="muted">It may not exist, or it belongs to another account.</p>'
        . '<a class="btn btn-primary" href="' . e(url('customer/orders.php')) . '">Back to my orders</a></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$items = order_items((int) $receipt['order_id']);
$emailDelivery = db()->prepare('SELECT status FROM receipt_email_deliveries WHERE order_id = ?');
$emailDelivery->execute([$receipt['order_id']]);
$emailStatus = $emailDelivery->fetchColumn();
$customerName = $receipt['pickup_name'] ?: ($receipt['display_name'] ?: $receipt['full_name']);
$pageTitle = 'Receipt ' . e($receipt['receipt_number']) . ' — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>" media="print">

<section class="page-head receipt-actions">
  <p><?= $receipt['payment_method']==='DEMO' ? 'DEMO PAYMENT — no real money — ' : ($receipt['payment_is_test'] ? 'TEST PAYMENT - ' : '') ?><?= e(payment_status_label($receipt['payment_status'], $receipt['status'], $receipt['fulfillment_type'] ?? 'PICKUP')) ?>.
  <?= $receipt['payment_status'] === 'PAID' ? 'Payment receipt. Check order status for readiness.' : 'Order record only; this is not proof of payment.' ?></p>
  <div>
    <h1>Receipt <?= e($receipt['receipt_number']) ?></h1>
    <p class="muted">Item names and prices are preserved from checkout. Payment confirmation is shown separately.</p>
  </div>
  <div class="receipt-actions-btns">
    <button class="btn btn-primary" type="button" onclick="window.print()">Print receipt</button>
    <a class="btn btn-outline" href="<?= e(url('customer/order.php') . '?ref=' . urlencode($receipt['order_number'])) ?>">Order status</a>
    <a class="btn btn-outline" href="<?= e(url('customer/orders.php')) ?>">My orders</a>
  </div>
</section>

<?php if ($receipt['payment_status'] === 'PAID' && $emailStatus): ?>
  <p class="receipt-actions muted"><?= $emailStatus === 'SENT' ? 'A copy of this receipt was sent to your email.' : ($emailStatus === 'SKIPPED' ? 'Your receipt is available here to view and print.' : 'Your receipt email is waiting for delivery. You can view or print it here.') ?></p>
<?php endif; ?>
<div class="receipt-paper" id="receipt">
  <div class="receipt-head">
    <div class="receipt-logo"><img class="brand-mark" src="<?= e(url('assets/images/brand-mark.svg')) ?>" alt="" width="36" height="36"></div>
    <div class="receipt-store">
      <strong>BulkBites BBQ</strong>
      <div class="muted"><?= e($receipt['pickup_location'] ?: 'Greensite Compound, Sta. Lucia, Pasig City · near SM City East') ?></div>
    </div>
  </div>

  <?php if($receipt['payment_method']==='DEMO'): ?><p class="notice"><strong>DEMO PAYMENT — no real money collected.</strong></p><?php endif; ?>
  <div class="receipt-meta">
    <div class="receipt-row"><span>Receipt no.</span><strong><?= e($receipt['receipt_number']) ?></strong></div>
    <div class="receipt-row"><span>Order no.</span><strong><?= e($receipt['order_number']) ?></strong></div>
    <div class="receipt-row"><span>Date</span><strong><?= e(date('M j, Y · g:i A', strtotime($receipt['issued_at']))) ?></strong></div>
    <div class="receipt-row"><span>Customer</span><strong><?= e($customerName) ?></strong></div>
    <div class="receipt-row"><span>Status</span><span><?= order_status_badge($receipt['status']) ?></span></div>
  </div>

  <p><strong><?= e(fulfillment_label($receipt)) ?></strong><?php if(is_delivery($receipt)): ?><br><?= nl2br(e($receipt['delivery_address'])) ?><br>Contact: <?= e($receipt['pickup_contact']) ?><?php endif; ?></p>
  <table class="data-table receipt-items">
    <thead>
      <tr><th>Item</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['product_name']) ?></td>
          <td class="num"><?= (int) $item['quantity'] ?></td>
          <td class="num"><?= e(peso($item['unit_price'])) ?></td>
          <td class="num"><?= e(peso($item['line_total'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
<tr><td colspan="3">Food subtotal</td><td class="num"><?= e(peso($receipt['subtotal'])) ?></td></tr>
          <tr><td colspan="3">Delivery fee</td><td class="num"><?= e(peso($receipt['delivery_fee'])) ?></td></tr>
      <tr class="total-row">
        <td colspan="3">Total</td>
        <td class="num"><?= e(peso($receipt['total'])) ?></td>
      </tr>
    </tfoot>
  </table>

  <div class="receipt-meta">
    <div class="receipt-row"><span>Payment method</span><strong><?= e(payment_method_label($receipt['payment_method'])) ?></strong></div>
    <?php if ($receipt['notes']): ?>
      <div class="receipt-row"><span>Notes</span><strong><?= e($receipt['notes']) ?></strong></div>
    <?php endif; ?>
  </div>

  <div class="receipt-foot">
    <p><strong>Thank you for supporting BulkBites BBQ!</strong></p>
    <p class="muted">This receipt was generated by the BulkBites BBQ digital ordering system.</p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
