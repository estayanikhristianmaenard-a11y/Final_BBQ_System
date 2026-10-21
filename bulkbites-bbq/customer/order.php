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
    echo '<div class="empty-state card">'
        . '<p><strong>Order not found.</strong></p>'
        . '<p class="muted">It may not exist, or it belongs to another account.</p>'
        . '<a class="btn btn-primary" href="' . e(url('customer/orders.php')) . '">Back to my orders</a></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$items = order_items((int) $order['id']);
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    csrf_verify();
    $result=($_POST['action'] ?? '')==='cancel' ? cancel_customer_order((int)$order['id'],(int)$user['id']) : ['ok'=>false,'message'=>'Unknown order action.'];
    flash($result['ok']?'success':'error',$result['message']);
    redirect(url('customer/order.php') . '?ref=' . urlencode($ref));
}
$settings=store_settings();
$pickupLocation=$order['pickup_location'] ?: $settings['pickup_location'];
$history = order_history((int) $order['id']);
$justPlaced = isset($_GET['placed']) && $order['status'] === 'NEW';
$checkoutQuery = db()->prepare('SELECT session_id FROM paymongo_checkouts WHERE order_id = ?');
$checkoutQuery->execute([$order['id']]);
$providerSession = $checkoutQuery->fetchColumn();

$pageTitle = 'Order ' . e($order['order_number']) . ' — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<p data-live-feedback role="status" class="hint">Checking for order updates every 3 seconds.</p>
<div data-live-orders data-live-revision="<?= e(customer_order_revision($order)) ?>" data-live-api="<?= e(url('api/order-status.php').'?ref='.rawurlencode($ref)) ?>">
<section class="page-head order-head">
  <p role="status"><?= e(order_payment_prefix($order) . payment_status_label($order['payment_status'], $order['status'], $order['fulfillment_type'] ?? 'PICKUP')) ?>.
  <?php if ($order['reservation_expires_at']): ?>Reservation ends <?= e(date('g:i A', strtotime($order['reservation_expires_at']))) ?>.<?php endif; ?></p>
  <div>
    <h1><?= e($order['order_number']) ?></h1>
    <p class="muted">Placed <?= e(date('F j, Y · g:i A', strtotime($order['created_at']))) ?></p>
  </div>

</section>
<div class="order-primary-summary card"><div><span class="muted">Order total</span><strong><?= e(peso($order['total'])) ?></strong></div>
<a class="btn <?= $order['payment_status']==='PAID'?'btn-primary':'btn-outline' ?>" href="<?= e(url('customer/receipt.php').'?order='.urlencode($order['order_number'])) ?>"><?= $order['payment_status']==='PAID'?'View receipt':'View order record' ?></a></div>
<?php if(in_array($order['status'],['COMPLETED','CANCELLED'],true)): ?><section class="order-callout" role="status"><h2><?= e(order_status_label($order['status'])) ?></h2><p><?= $order['status']==='COMPLETED'?'Thank you for ordering from BulkBites BBQ.':($order['payment_status']==='REFUND_REQUIRED'?'Contact the shop to arrange your refund.':'This order is no longer being prepared.') ?></p></section><?php endif; ?>
<?php if ($justPlaced): ?>
  <div class="flash flash-success">
    <strong>Order received.</strong> Your order <strong><?= e($order['order_number']) ?></strong>
    is waiting for payment or shop acceptance. Preparation starts after acceptance.
  </div>
<?php endif; ?>

<?php if ($order['payment_method']==='DEMO'): ?>
  <section class="card"><h2>Demo payment</h2><p><strong>DEMO PAYMENT — no real money.</strong> This order uses the local payment simulator.</p>
    <?php if(demo_payment_enabled() && $order['status']==='NEW' && $order['payment_status']==='PENDING'): ?><a class="btn btn-primary" href="<?= e(url('customer/demo-payment.php').'?ref='.rawurlencode($ref)) ?>">Continue demo payment</a><?php endif; ?>
  </section>
<?php elseif ($order['payment_method'] === 'GCASH'): ?>
  <div class="card">
    <h2>Online payment (test mode)</h2>
    <p>No real money is collected by this demo checkout. Returning here does not confirm payment.</p>
    <?php if (isset($_GET['payment_cancelled'])): ?><p>Checkout was closed. The order remains unpaid until payment is verified; the reservation deadline still applies.</p><?php endif; ?>
    <?php if (paymongo_ready() && $order['payment_status'] === 'PENDING' && $order['status'] === 'NEW'): ?>
      <form method="post" action="<?= e(url('customer/payment.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($ref) ?>">
        <button class="btn btn-primary" name="action" value="pay" type="submit">Continue test checkout</button>
      </form>
    <?php endif; ?>
    <?php if (paymongo_ready() && $providerSession && !in_array($order['payment_status'], ['PAID','REFUNDED'], true)): ?>
      <form method="post" action="<?= e(url('customer/payment.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($ref) ?>">
        <button class="btn btn-outline" name="action" value="check" type="submit">Check payment status</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($order['status'] === 'NEW'): ?>
<section class="order-callout" role="status">
  <?php if (in_array($order['payment_method'], ['DEMO','GCASH'], true) && $order['payment_status'] === 'PENDING'): ?>
    <h2>Complete your payment</h2><p>Your order is saved. Use the payment option above so staff can review it.</p>
  <?php else: ?>
    <h2>Waiting for staff acceptance</h2><p>Your order is saved. The shop will confirm it before preparation begins.</p>
  <?php endif; ?>
</section>
<?php elseif ($order['status'] === 'ACCEPTED'): ?>
<section class="order-callout" role="status"><h2>Your order is accepted</h2><p>The shop has confirmed your order. <?= is_delivery($order) ? 'Keep your phone available for delivery arrangements.' : 'Wait for the Ready update before collecting it.' ?></p></section>
<?php endif; ?>
<?php if ($order['status'] === 'READY'): ?>
<section class="order-callout" role="status">
  <h2><?= is_delivery($order) ? 'Ready for delivery' : 'Ready for pickup' ?></h2>
  <?php if(is_delivery($order)): ?><p>Your food is ready. Keep your phone available while the shop arranges delivery.<?= $order['payment_status']!=='PAID' ? ' Prepare ' . e(peso($order['total'])) . ' cash on delivery.' : ' Payment confirmed.' ?></p><?php else: ?>
  <p>Show order <?= e($order['order_number']) ?> at <?= e($pickupLocation) ?>.</p>
  <p><?= $order['payment_status'] === 'PAID' ? 'Payment confirmed. Collect your food from the counter.' : 'Pay ' . e(peso($order['total'])) . ' in cash when you collect your food.' ?></p><?php endif; ?>
</section>
<?php endif; ?>
<?php if ($order['status'] === 'PREPARING'): ?>
<section class="grilling-status card" aria-labelledby="grilling-title">
  <div><h2 id="grilling-title">Your BBQ is being prepared</h2><p>We'll update this page when your food is ready.</p></div>
  <?php $grillInteractive = false; require dirname(__DIR__) . '/includes/grill-scene.php'; ?>
</section>
<?php endif; ?>


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
<tr><td colspan="3">Food subtotal</td><td class="num"><?= e(peso($order['subtotal'])) ?></td></tr>
          <tr><td colspan="3">Delivery fee</td><td class="num"><?= e(peso($order['delivery_fee'])) ?></td></tr>

        </tfoot>
      </table>
    </div>

    <details class="card mt order-history"><summary>Order history</summary>
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
    </details>
  </div>

  <aside class="cart-summary card">
    <details class="order-support"><summary>Details, help and cancellation</summary>
    <div class="summary-row"><span>Customer name</span><strong><?= e($order['pickup_name'] ?: $user['full_name']) ?></strong></div>
    <?php if($order['pickup_contact']): ?><div class="summary-row"><span>Contact</span><strong><?= e($order['pickup_contact']) ?></strong></div><?php endif; ?>
    <div class="summary-row"><span>Order type</span><strong><?= e(fulfillment_label($order)) ?></strong></div>
    <?php if(is_delivery($order)): ?><p><strong>Delivery address</strong><br><?= nl2br(e($order['delivery_address'])) ?></p><?php endif; ?>



    <div class="summary-row"><span>Payment</span><strong><?= e(payment_method_label($order['payment_method'])) ?></strong></div>

    <?php if($estimate=order_estimate_text($order)): ?><p><?= e($estimate) ?></p><?php endif; ?>
    <?php if ($order['notes']): ?>
      <div class="order-notes"><strong>Notes:</strong> <?= e($order['notes']) ?></div>
    <?php endif; ?>
    <div class="pickup-help"><h3>Shop and cancellation</h3><p><?= e($pickupLocation) ?></p>
      <?php if($settings['contact_details']): ?><p>Contact: <?= e($settings['contact_details']) ?></p><?php else: ?><p>For help, speak to staff at the pickup counter.</p><?php endif; ?>
      <?php if($order['status']==='NEW'): ?>
        <p>You can cancel before the shop accepts. Paid cancellations require the shop to arrange a refund.</p>
        <form method="post" action="<?= e(url('customer/order.php') . '?ref=' . urlencode($ref)) ?>" data-confirm="Cancel this order? This cannot be undone. A refund, if needed, must be arranged with the shop.">
          <?= csrf_field() ?><button class="btn btn-outline btn-block" name="action" value="cancel" type="submit">Cancel order</button>
        </form>
      <?php elseif(in_array($order['status'],['ACCEPTED','PREPARING','READY'],true)): ?><p>The shop has accepted your order. Contact staff for cancellation help; preparation may already have started.</p>
      <?php elseif($order['payment_status']==='REFUND_REQUIRED'): ?><p>Your cancellation needs a refund. Contact the shop; no refund is sent automatically.</p><?php endif; ?>
    </div>
    </details>
  </aside>
</div>
<nav class="order-return-links"><a href="<?= e(url('customer/orders.php')) ?>">My orders</a><a href="<?= e(url('customer/menu.php')) ?>">Back to menu</a></nav>
</div>
<script src="<?= e(url('assets/js/order-status.js')) ?>?v=<?= (int)filemtime(dirname(__DIR__) . '/assets/js/order-status.js') ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
