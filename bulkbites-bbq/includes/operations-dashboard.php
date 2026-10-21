<?php
/** Shared manager dashboard and staff kitchen workspace. Routes enforce role access. */
$user = current_user();
$isManagerDashboard = $user['role'] === 'MANAGER';
$dashboardReturn = $isManagerDashboard ? 'manager/dashboard.php' : 'staff/kitchen.php';
$stats = $isManagerDashboard ? manager_dashboard_stats() : null;
$hasOrderActionDialogs = true;
$counts = staff_order_counts();
$queue  = staff_active_queue();
$lowStock = staff_low_stock();

$columns = [
    ['key' => 'NEW',       'title' => 'New',        'statuses' => ['NEW']],
    ['key' => 'IN PROGRESS', 'title' => 'In progress', 'statuses' => ['ACCEPTED', 'PREPARING']],
    ['key' => 'READY',     'title' => 'Ready',      'statuses' => ['READY']],
];

/** One KDS card (also the server-side fallback when JS is off). */
$render_card = static function (array $order) use ($user, $dashboardReturn): void {
    $age = (int) $order['age_seconds'];
    $ageClass = $age >= 900 ? ' age-hot' : ($age >= 300 ? ' age-warm' : '');
    $minutes = intdiv($age, 60);
    $ageLabel = $minutes < 1 ? 'just now' : $minutes . 'm ' . ($age % 60) . 's';
    ?>
    <article class="kds-card<?= $ageClass ?>" data-order-id="<?= (int) $order['id'] ?>" data-order-status="<?= e($order['status']) ?>">
      <?php if($wait=order_acceptance_wait_text($order)): ?><p class="acceptance-wait"><?= e($wait) ?></p><?php endif; ?>
      <header class="kds-card-head">
        <strong class="kds-no"><?= e($order['order_number']) ?></strong>
        <span class="kds-age" data-created="<?= e($order['created_at']) ?>"><?= e($ageLabel) ?></span>
      </header>
      <div class="kds-meta">
        <span><?= e($order['customer_label']) ?></span>
        <span><?= e($order['payment_method']) ?> · <?= e(peso($order['total'])) ?></span>
      </div>
      <p class="kds-meta"><?= e(fulfillment_label($order)) ?><?= is_delivery($order) ? ' · fee ' . e(peso($order['delivery_fee'])) : '' ?></p>
      <?php if(is_delivery($order)): ?><p class="kds-meta"><?= e($order['delivery_address']) ?></p><?php if($order['status']==='READY'): ?><p class="kds-meta">Complete after delivery and payment are confirmed.</p><?php endif; ?><?php endif; ?>
      <?php if($order['pickup_contact']): ?><p class="kds-meta"><?= e($order['pickup_contact']) ?></p><?php endif; ?>
      <?php if($estimate=order_estimate_text($order)): ?><p class="kds-meta"><?= e($estimate) ?></p><?php endif; ?>
      <ul class="kds-items">
        <li><?= e(order_payment_prefix($order) . payment_status_label($order['payment_status'], $order['status'], $order['fulfillment_type'] ?? 'PICKUP')) ?></li>
        <?php foreach ($order['items'] as $item): ?>
          <li><span class="kds-qty"><?= (int) $item['quantity'] ?>×</span> <?= e($item['product_name']) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($order['notes']): ?>
        <p class="kds-notes">Notes: <?= e($order['notes']) ?></p>
      <?php endif; ?>
      <div class="kds-actions">
        <?php foreach (array_diff(order_available_actions($order), ['CANCELLED']) as $target): ?>
          <form method="post" action="<?= e(url('api/orders.php')) ?>"
                <?= $target === 'COLLECT_CASH' ? 'data-cash-amount="' . e(peso($order['total'])) . '"' : '' ?>
                        <?= $target === 'CANCELLED' ? 'data-confirm="Cancel this order? This cannot be undone."' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <input type="hidden" name="action" value="<?= e($target) ?>">
            <input type="hidden" name="return" value="<?= e($dashboardReturn) ?>">
            <button class="btn btn-sm <?= $target === 'CANCELLED' ? 'btn-outline kds-btn-cancel' : 'btn-primary' ?>"
                    type="submit"><?= e(order_status_action_label($target, $order)) ?></button>
          </form>
        <?php endforeach; ?>
        <?php if(in_array('CANCELLED',order_available_actions($order),true)): ?>
        <details class="order-more-actions"><summary>More actions</summary>
        <?php foreach (array_intersect(order_available_actions($order), ['CANCELLED']) as $target): ?>
          <form method="post" action="<?= e(url('api/orders.php')) ?>"
                <?= $target === 'COLLECT_CASH' ? 'data-cash-amount="' . e(peso($order['total'])) . '"' : '' ?>
                        <?= $target === 'CANCELLED' ? 'data-confirm="Cancel this order? This cannot be undone."' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <input type="hidden" name="action" value="<?= e($target) ?>">
            <input type="hidden" name="return" value="<?= e($dashboardReturn) ?>">
            <button class="btn btn-sm <?= $target === 'CANCELLED' ? 'btn-outline kds-btn-cancel' : 'btn-primary' ?>"
                    type="submit"><?= e(order_status_action_label($target, $order)) ?></button>
          </form>
        <?php endforeach; ?>
        </details>
        <?php endif; ?>
      </div>
    </article>
    <?php
};

$pageTitle = ($isManagerDashboard ? 'Dashboard' : 'Kitchen') . ' - BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(url('assets/css/kds.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/kds.css') ?>">

<section class="page-head">
  <div>
    <h1><?= $isManagerDashboard ? 'Dashboard' : 'Kitchen' ?></h1>
    <p class="muted"><?= $isManagerDashboard ? "Today's business and live order queue in one place." : 'Live orders. Tap the next action to update an order.' ?></p>
  </div>
  <div class="kds-legend muted small">
    <span><span class="kds-dot dot-new"></span> New</span>
    <span><span class="kds-dot dot-prep"></span> In progress</span>
    <span><span class="kds-dot dot-ready"></span> Ready</span>
  </div>
</section>

<?php if ($isManagerDashboard): ?>
<?php if (!email_configured()): ?>
  <div class="notice" role="status">
    <strong>Connect an email sender</strong> to enable customer sign-in codes.
    <a href="<?= e(url('manager/email.php')) ?>">Set up email delivery</a>
  </div>
<?php endif; ?>

<?php endif; ?>
<?php $orderingReturn=$dashboardReturn; require __DIR__.'/ordering-controls.php'; ?>
<?php if ($isManagerDashboard): ?>
<div class="stat-grid demo-overview-stats">
  <a class="stat-card stat-new" href="<?= e(url('staff/orders.php')) ?>?status=ALL&amp;period=TODAY">
    <strong data-dashboard-stat="orders_today"><?= (int) $stats['orders_today'] ?></strong>
    <span>Orders today</span>
    <em><span data-dashboard-stat="active_now"><?= (int) $stats['active_now'] ?></span> active now</em>
  </a>
  <a class="stat-card stat-ready" href="<?= e(url('manager/reports.php')) ?>">
    <strong data-dashboard-stat="sales_today"><?= e(peso($stats['sales_today'])) ?></strong>
    <span>Net collections today</span>
    <em>Confirmed payments; demo payments excluded</em>
  </a>
  <a class="stat-card stat-progress" href="<?= e(url('staff/orders.php')) ?>?status=COMPLETED&amp;period=TODAY">
    <strong data-dashboard-stat="completed_today"><?= (int) $stats['completed_today'] ?></strong>
    <span>Completed today</span>
    <em><span data-dashboard-stat="cancelled_today"><?= (int) $stats['cancelled_today'] ?></span> cancelled today</em>
  </a>

</div>

<?php endif; ?>
<h2 class="dashboard-queue-title">Order queue</h2>
<div class="kds-totals" aria-label="Order counts">
  <span class="kds-total"><strong data-count="awaiting_acceptance"><?= (int) $counts['awaiting_acceptance'] ?></strong> awaiting acceptance</span>
  <span class="kds-total"><strong data-count="awaiting_payment"><?= (int) $counts['awaiting_payment'] ?></strong> awaiting payment</span>
  <span class="kds-total"><strong data-count="in_progress"><?= (int) $counts['in_progress'] ?></strong> in progress</span>
  <span class="kds-total"><strong data-count="ready"><?= (int) $counts['ready'] ?></strong> ready</span>
  <?php if (!$isManagerDashboard): ?><span class="kds-total"><strong data-count="completed_today"><?= (int) $counts['completed_today'] ?></strong> completed today</span><?php endif; ?>
  <a class="btn btn-outline btn-sm" href="<?= e(url('staff/orders.php')) ?>">Orders</a>
</div>

<label class="kitchen-stage-picker" hidden>Stage<select data-kitchen-stage><option value="ALL">All stages</option><option value="NEW">New</option><option value="IN PROGRESS">In progress</option><option value="READY">Ready</option></select></label>
<nav class="stage-links" aria-label="Jump to kitchen stage">
  <a class="btn btn-outline" href="#kds-new">New</a>
  <a class="btn btn-outline" href="#kds-in-progress">In progress</a>
  <a class="btn btn-outline" href="#kds-ready">Ready orders</a>
</nav>
<div id="kds-flash" class="flash flash-info" style="display:none" role="status"></div>
<p class="live-status" data-kds-connection data-connection="online">Order board loaded. Checking for updates every 3 seconds.</p>

<div class="kds-board" id="kds-board" role="region" aria-label="Live order board" aria-live="off">
  <?php foreach ($columns as $column): ?>
    <section id="kds-<?= str_replace(' ', '-', strtolower($column['key'])) ?>" class="kds-col kds-col-<?= str_replace(' ', '-', strtolower($column['key'])) ?>" data-col="<?= e($column['key']) ?>">
      <h2 class="kds-col-head">
        <?= e($column['title']) ?>
        <span class="kds-col-count" data-col-count="<?= e($column['key']) ?>">
          <?= count(array_filter($queue, fn($o) => in_array($o['status'], $column['statuses'], true))) ?>
        </span>
      </h2>
      <div class="kds-cards">
        <?php foreach ($queue as $order): ?>
          <?php if (in_array($order['status'], $column['statuses'], true)) { $render_card($order); } ?>
        <?php endforeach; ?>
      </div>
      <p class="kds-empty" style="<?= count(array_filter($queue, fn($o) => in_array($o['status'], $column['statuses'], true))) ? 'display:none' : '' ?>">No orders in this stage.</p>
    </section>
  <?php endforeach; ?>
</div>

<p class="notice" role="status" data-dashboard-low-stock<?= $lowStock ? '' : ' hidden' ?>><span data-low-stock-text>Stock needs attention: <?= e(implode(', ', array_map(static fn($row) => $row['name'].' ('.(int)$row['quantity_available'].' left)', $lowStock))) ?>.</span> <a href="<?= e(url($isManagerDashboard ? 'manager/products.php' : 'staff/inventory.php')) ?>">View stock</a></p>
<script src="<?= e(url('assets/js/kds.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/kds.js') ?>"
        defer data-kds-return="<?= e($dashboardReturn) ?>" data-kds-api="<?= e(url('api/orders.php')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
