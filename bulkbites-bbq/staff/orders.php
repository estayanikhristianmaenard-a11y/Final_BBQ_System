<?php
/**
 * Order queue — Phase 6. Staff view of ALL orders (architecture permission
 * matrix: "View all orders ✅") with item details, status filter, and the
 * same status actions as the KDS. Actions post to api/orders.php.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');

$user = current_user();
$hasOrderActionDialogs = true;

// Whitelisted status filter
$validFilters = ['ALL', 'ACTIVE', 'HISTORY', 'NEW', 'ACCEPTED', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED'];
$filter = strtoupper((string) ($_GET['status'] ?? 'ACTIVE'));
if (!in_array($filter, $validFilters, true)) {
    $filter = 'ACTIVE';
}

$period = ($_GET['period'] ?? '') === 'TODAY' ? 'TODAY' : '';
$filters = [];
if($period==='TODAY') { $filters[in_array($filter,['HISTORY','COMPLETED','CANCELLED'],true)?'outcome_today':'today']=true; }
if ($filter === 'ACTIVE') {
    $filters['statuses'] = ['NEW', 'ACCEPTED', 'PREPARING', 'READY'];
} elseif ($filter === 'HISTORY') {
    $filters['statuses'] = ['COMPLETED','CANCELLED'];
} elseif ($filter !== 'ALL') {
    $filters['statuses'] = [$filter];
}
$filters['recent_first'] = in_array($filter,['HISTORY','COMPLETED','CANCELLED','ALL'],true);
$orders = orders_with_items($filters, 100);
$counts = staff_order_counts();

$periodCaption=$period==='TODAY' ? ($filter==='COMPLETED' ? 'Completed today' : ($filter==='CANCELLED' ? 'Cancelled today' : ($filter==='HISTORY' ? 'Completed or cancelled today' : 'Orders placed today'))) : '';
$returnQuery='staff/orders.php?'.http_build_query(array_filter(['status'=>$filter,'period'=>$period]));
$pageTitle = 'Order Queue — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Orders</h1>
    <p class="muted"><?= $filters['recent_first'] ? 'Latest 100 orders. Open an order for its full details.' : 'Active orders appear oldest first. Open an order for details and actions.' ?></p>
  </div>
  <a class="btn btn-primary" href="<?= e(url($user['role']==='MANAGER' ? 'manager/dashboard.php' : 'staff/kitchen.php')) ?>"><?= $user['role']==='MANAGER' ? 'Dashboard' : 'Kitchen' ?></a>
</section>

<?php if($periodCaption): ?><p class="notice"><?= e($periodCaption) ?> · <?= e(date('M j, Y')) ?>. <a href="<?= e(url('staff/orders.php').'?status='.rawurlencode($filter)) ?>">Show all dates</a></p><?php endif; ?>
<nav class="cat-tabs" aria-label="Filter orders">
  <?php
  $tabs = ['ACTIVE'=>'Active orders', 'HISTORY'=>'History'];
  foreach ($tabs as $key => $label): ?>
    <a class="cat-tab<?= ($filter === $key || ($key==='HISTORY' && in_array($filter,['COMPLETED','CANCELLED'],true)) || ($key==='ACTIVE' && in_array($filter,['NEW','ACCEPTED','PREPARING','READY'],true))) ? ' active' : '' ?>"
       href="<?= e(url('staff/orders.php') . '?' . http_build_query(array_filter(['status'=>$key,'period'=>$period]))) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<p data-live-feedback role="status" class="hint">Checking for order updates every 3 seconds.</p>
<div data-live-orders>
<?php if (!$orders): ?>
  <div class="empty-state card">
    <div class="empty-icon" aria-hidden="true"><svg class="nav-icon" focusable="false"><use href="<?= e(url('assets/images/nav-icons.svg#receipt')) ?>"></use></svg></div>
    <p><strong>No orders match this filter.</strong></p>
    <p class="muted">New customer orders appear here automatically.</p>
    <a class="btn btn-primary" href="<?= e(url('staff/kitchen.php')) ?>">Open Kitchen Display</a>
  </div>
<?php else: ?>
  <div class="card queue-wrap">
    <table class="data-table queue-table">
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer / Type</th>
          <th class="num">Total</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php
            $paymentSummary = match($order['payment_status']) {
                'LEGACY_UNVERIFIED'=>'Payment unverified', 'UNPAID'=>'Cash due', 'PENDING'=>'Awaiting payment',
                'PAID'=>'Paid', 'REFUND_REQUIRED'=>'Refund required', 'REFUNDED'=>'Refunded', 'EXPIRED'=>'Payment expired',
                default=>payment_status_label($order['payment_status'],$order['status'],$order['fulfillment_type'])
            };
            if($order['status']==='CANCELLED' && $order['payment_status']==='PAID') { $paymentSummary='Refund review needed'; }
            if($order['status']==='CANCELLED' && in_array($order['payment_status'],['UNPAID','PENDING','EXPIRED'],true)) { $paymentSummary='No payment due'; }
          ?>
          <tr id="order-<?= (int) $order['id'] ?>"<?= $order['status'] === 'NEW' ? ' class="queue-row-new"' : '' ?>>
            <td><strong><?= e($order['order_number']) ?></strong><br><span class="muted small"><?= e(date('M j, Y · g:i A',strtotime($order['created_at']))) ?></span></td>
            <td><?= e($order['customer_label']) ?><br><span class="muted small"><?= e(fulfillment_label($order)) ?></span></td>
            <td class="num"><?= e(peso($order['total'])) ?></td>
            <td><?= order_status_badge($order['status']) ?><br><span class="small"><?= e(order_payment_prefix($order).$paymentSummary) ?></span><?php if($wait=order_acceptance_wait_text($order)): ?><p class="acceptance-wait"><?= e($wait) ?></p><?php endif; ?></td>
            <td>
              <details class="order-view"><summary>View order</summary>
                <p class="order-action-summary"><?= order_status_badge($order['status']) ?> · <?= e(fulfillment_label($order)) ?> · <?= e($paymentSummary) ?></p>
              <div class="queue-actions">
                <?php foreach (array_diff(order_available_actions($order), ['CANCELLED']) as $target): ?>
                  <form method="post" action="<?= e(url('api/orders.php')) ?>"
                        <?= $target === 'COLLECT_CASH' ? 'data-cash-amount="' . e(peso($order['total'])) . '"' : '' ?>
                        <?= $target === 'CANCELLED' ? 'data-confirm="Cancel this order? This cannot be undone."' : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="action" value="<?= e($target) ?>">
                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                    <button class="btn btn-sm <?= $target === 'CANCELLED' ? 'btn-outline' : 'btn-primary' ?>" type="submit">
                      <?= e(order_status_action_label($target, $order)) ?>
                    </button>
                  </form>
                <?php endforeach; ?>
                <?php if(in_array('CANCELLED',order_available_actions($order),true) || in_array($order['status'],['ACCEPTED','PREPARING'],true) || ($user['role']==='MANAGER' && $order['payment_status']==='REFUND_REQUIRED')): ?>
                <details class="order-secondary-actions"><summary>Other actions</summary>
                <?php if(in_array($order['status'],['ACCEPTED','PREPARING'],true)): ?>
                  <p><strong>Preparation estimate</strong></p><p class="small"><?= e(order_estimate_text($order)) ?></p>
                    <form method="post" action="<?= e(url('api/orders.php')) ?>">
                      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="action" value="SET_ESTIMATE"><input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                      <label>Minutes from now <input type="number" name="minutes" min="1" max="180" required></label><button class="btn btn-outline btn-sm" type="submit">Update estimate</button>
                    </form>

                <?php endif; ?>
                <?php if ($user['role'] === 'MANAGER' && $order['payment_status'] === 'REFUND_REQUIRED'): ?>
                  <form method="post" action="<?= e(url('api/orders.php')) ?>" data-confirm="Record a refund already completed outside this website? This does not send money.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="action" value="RECORD_REFUND">
                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                    <label>Completed refund reference <input name="reference" maxlength="100" required></label>
                    <button class="btn btn-sm btn-outline" type="submit">Record completed refund</button>
                  </form>
                <?php endif; ?>
                <?php foreach (array_intersect(order_available_actions($order), ['CANCELLED']) as $target): ?>
                  <form method="post" action="<?= e(url('api/orders.php')) ?>"
                        <?= $target === 'COLLECT_CASH' ? 'data-cash-amount="' . e(peso($order['total'])) . '"' : '' ?>
                        <?= $target === 'CANCELLED' ? 'data-confirm="Cancel this order? This cannot be undone."' : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="action" value="<?= e($target) ?>">
                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                    <button class="btn btn-sm <?= $target === 'CANCELLED' ? 'btn-outline' : 'btn-primary' ?>" type="submit">
                      <?= e(order_status_action_label($target, $order)) ?>
                    </button>
                  </form>
                <?php endforeach; ?>
                </details>
                <?php endif; ?>
              </div>
                <section class="order-detail-items"><h3>Order items</h3>
                <?php foreach($order['items'] as $item): ?><div class="summary-row"><span><?= (int)$item['quantity'] ?> × <?= e($item['product_name']) ?></span><strong><?= e(peso($item['line_total'])) ?></strong></div><?php endforeach; ?>
                <div class="summary-row"><span>Food subtotal</span><strong><?= e(peso($order['subtotal'])) ?></strong></div>
                <div class="summary-row"><span>Delivery fee</span><strong><?= e(peso($order['delivery_fee'])) ?></strong></div>
                <div class="summary-row summary-total"><span>Total</span><strong><?= e(peso($order['total'])) ?></strong></div>
                </section>
                <details class="order-extra-details"><summary>Customer, delivery and staff details</summary>
                <dl class="order-detail-grid">
                  <div><dt>Customer</dt><dd><?= e($order['customer_label']) ?></dd></div>
                  <div><dt>Order type</dt><dd><?= e(fulfillment_label($order)) ?></dd></div>
                  <div><dt>Placed</dt><dd><?= e(date('M j, Y · g:i A',strtotime($order['created_at']))) ?></dd></div>
                  <div><dt>Status</dt><dd><?= e(order_status_label($order['status'])) ?></dd></div>
                  <div><dt>Payment</dt><dd><?= e(payment_method_label($order['payment_method'])) ?><br><?= e(order_payment_prefix($order).payment_status_label($order['payment_status'],$order['status'],$order['fulfillment_type'])) ?></dd></div>
                  <div><dt>Handled by</dt><dd><?= e($order['handler_name'] ?: 'Not assigned') ?></dd></div>
                </dl>
                <?php if($order['payment_status']==='LEGACY_UNVERIFIED'): ?><p class="notice">This older record has no verified payment details. It is not proof of payment.</p><?php endif; ?>
                <?php if($order['pickup_contact']): ?><p><strong>Contact</strong><br><?= e($order['pickup_contact']) ?></p><?php endif; ?>
                <p><strong><?= is_delivery($order)?'Delivery address':'Pickup location' ?></strong><br><?= nl2br(e(is_delivery($order)?$order['delivery_address']:($order['pickup_location'] ?: store_settings()['pickup_location']))) ?></p>
                <?php if($estimate=order_estimate_text($order)): ?><p class="hint"><?= e($estimate) ?></p><?php endif; ?>
                </details>
                <?php if($order['notes']): ?><p><strong>Notes</strong><br><?= nl2br(e($order['notes'])) ?></p><?php endif; ?>

              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>
<script src="<?= e(url('assets/js/order-status.js')) ?>?v=<?= (int)filemtime(dirname(__DIR__).'/assets/js/order-status.js') ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
