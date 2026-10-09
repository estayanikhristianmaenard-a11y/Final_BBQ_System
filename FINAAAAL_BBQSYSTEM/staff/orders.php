<?php
/**
 * Order queue — Phase 6. Staff view of ALL orders (architecture permission
 * matrix: "View all orders ✅") with item details, status filter, and the
 * same status actions as the KDS. Actions post to api/orders.php.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');

$user = current_user();

// Whitelisted status filter
$validFilters = ['ALL', 'ACTIVE', 'NEW', 'ACCEPTED', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED'];
$filter = strtoupper((string) ($_GET['status'] ?? 'ALL'));
if (!in_array($filter, $validFilters, true)) {
    $filter = 'ALL';
}

$filters = [];
if ($filter === 'ACTIVE') {
    $filters['statuses'] = ['NEW', 'ACCEPTED', 'PREPARING', 'READY'];
} elseif ($filter !== 'ALL') {
    $filters['statuses'] = [$filter];
}
$orders = orders_with_items($filters, 100);
$counts = staff_order_counts();

$pageTitle = 'Order Queue — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Order queue</h1>
    <p class="muted">Every order with its items and live status. Active orders first · latest 100 shown.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('staff/kitchen.php')) ?>">&#127859; Kitchen Display</a>
</section>

<nav class="cat-tabs" aria-label="Filter orders">
  <?php
  $tabs = [
      'ALL'       => 'All',
      'ACTIVE'    => 'Active',
      'NEW'       => 'New',
      'ACCEPTED'  => 'Accepted',
      'PREPARING' => 'Preparing',
      'READY'     => 'Ready',
      'COMPLETED' => 'Completed',
      'CANCELLED' => 'Cancelled',
  ];
  foreach ($tabs as $key => $label): ?>
    <a class="cat-tab<?= $filter === $key ? ' active' : '' ?>"
       href="<?= e(url('staff/orders.php') . '?status=' . $key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$orders): ?>
  <div class="empty-state card">
    <div class="empty-icon">&#128203;</div>
    <p><strong>No orders match this filter.</strong></p>
    <p class="muted">New customer orders arrive on the Kitchen Display instantly.</p>
    <a class="btn btn-primary" href="<?= e(url('staff/kitchen.php')) ?>">Open Kitchen Display</a>
  </div>
<?php else: ?>
  <div class="card queue-wrap">
    <table class="data-table queue-table">
      <thead>
        <tr>
          <th>Order</th>
          <th>Placed</th>
          <th>Customer</th>
          <th>Details</th>
          <th class="num">Total</th>
          <th>Status</th>
          <th>Handled by</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php
            $age = (int) $order['age_seconds'];
            $minutes = intdiv($age, 60);
            ?>
          <tr id="order-<?= (int) $order['id'] ?>"<?= $order['status'] === 'NEW' ? ' class="queue-row-new"' : '' ?>>
            <td>
              <strong><?= e($order['order_number']) ?></strong><br>
              <span class="muted small"><?= e($order['payment_method']) ?></span>
            </td>
            <td class="muted">
              <?= e(date('M j · g:i A', strtotime($order['created_at']))) ?><br>
              <span class="small"><?= $minutes < 1 ? 'just now' : $minutes . ' min ago' ?></span>
            </td>
            <td><?= e($order['customer_label']) ?></td>
            <td class="queue-details">
              <?php foreach ($order['items'] as $item): ?>
                <div class="queue-line">
                  <span class="queue-qty"><?= (int) $item['quantity'] ?>×</span>
                  <?= e($item['product_name']) ?>
                  <span class="muted small"><?= e(peso($item['line_total'])) ?></span>
                </div>
              <?php endforeach; ?>
              <?php if ($order['notes']): ?>
                <div class="queue-notes">&#128172; <?= e($order['notes']) ?></div>
              <?php endif; ?>
            </td>
            <td class="num"><?= e(peso($order['total'])) ?></td>
            <td><?= order_status_badge($order['status']) ?></td>
            <td class="muted small"><?= $order['handler_name'] ? e($order['handler_name']) : '—' ?></td>
            <td>
              <div class="queue-actions">
                <?php foreach (order_status_transitions()[$order['status']] ?? [] as $target): ?>
                  <form method="post" action="<?= e(url('api/orders.php')) ?>"
                        <?= $target === 'CANCELLED' ? 'data-confirm="Cancel this order? This cannot be undone."' : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <input type="hidden" name="action" value="<?= e($target) ?>">
                    <input type="hidden" name="return" value="staff/orders.php?status=<?= e($filter) ?>">
                    <button class="btn btn-sm <?= $target === 'CANCELLED' ? 'btn-outline' : 'btn-primary' ?>" type="submit">
                      <?= e(order_status_action_label($target)) ?>
                    </button>
                  </form>
                <?php endforeach; ?>
                <?php if (!(order_status_transitions()[$order['status']] ?? [])): ?>
                  <span class="muted small">No actions</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
