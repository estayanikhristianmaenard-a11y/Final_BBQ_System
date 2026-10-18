<?php
/**
 * Manager dashboard — Phase 7 (architecture §6).
 * Every figure below is a live MySQL aggregate (includes/manager_feed.php);
 * there are no placeholder or hardcoded statistics.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

$user = current_user();

$stats = manager_dashboard_stats();
$range7From = date('Y-m-d', strtotime('-6 days'));
$range7To = date('Y-m-d');
$sales7 = manager_sales_by_day($range7From, $range7To);
$status30 = manager_status_counts(date('Y-m-d', strtotime('-29 days')), $range7To);
$bestSellers = manager_best_sellers(date('Y-m-d', strtotime('-29 days')), $range7To, 5);
$lowStock = staff_low_stock();
$recentOrders = manager_recent_orders(8);
$customers = manager_customer_stats(date('Y-m-d', strtotime('-29 days')), $range7To);

$sum7 = 0.0;
$orders7 = 0;
foreach ($sales7 as $row) {
    $sum7 += (float) $row['sales'];
    $orders7 += (int) $row['orders'];
}
$maxDay = 0.0;
foreach ($sales7 as $row) {
    $maxDay = max($maxDay, (float) $row['sales']);
}

$pageTitle = 'Manager Dashboard — BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Manager dashboard</h1>
    <p class="muted">
      Signed in as <strong><?= e($user['full_name']) ?></strong> (MANAGER · <?= e($user['email']) ?>)
      · figures computed live from MySQL
    </p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('manager/reports.php')) ?>">&#128202; Full reports</a>
</section>

<?php if (!email_configured()): ?>
  <div class="notice" role="status">
    <strong>Connect an email sender</strong> to enable customer sign-in codes.
    <a href="<?= e(url('manager/email.php')) ?>">Set up email delivery</a>
  </div>
<?php endif; ?>

<div class="stat-grid">
  <a class="stat-card stat-new" href="<?= e(url('staff/orders.php')) ?>?status=ACTIVE">
    <strong><?= (int) $stats['orders_today'] ?></strong>
    <span>Orders today</span>
    <em><?= (int) $stats['active_now'] ?> active now · <?= (int) $stats['orders_total'] ?> all time</em>
  </a>
  <a class="stat-card stat-ready" href="<?= e(url('manager/reports.php')) ?>">
    <strong><?= e(peso($stats['sales_today'])) ?></strong>
    <span>Sales today</span>
    <em><?= e(peso($stats['sales_yesterday'])) ?> yesterday · <?= e(peso($stats['sales_all'])) ?> all time</em>
  </a>
  <a class="stat-card stat-progress" href="<?= e(url('staff/orders.php')) ?>?status=COMPLETED">
    <strong><?= (int) $stats['completed_today'] ?></strong>
    <span>Completed today</span>
    <em><?= (int) $stats['cancelled_today'] ?> cancelled today</em>
  </a>
  <a class="stat-card stat-done" href="<?= e(url('manager/customers.php')) ?>">
    <strong><?= (int) $stats['customers_total'] ?></strong>
    <span>Customers</span>
    <em><?= (int) $stats['customers_new_month'] ?> new this month · <?= (int) $stats['customers_buying'] ?> have ordered</em>
  </a>
</div>

<div class="dash-grid">
  <div class="card">
    <h2 class="card-title">Sales summary — last 7 days</h2>
    <p class="muted small">
      <?= e($range7From) ?> → <?= e($range7To) ?> ·
      <strong><?= e(peso($sum7)) ?></strong> from <strong><?= $orders7 ?></strong> order(s) · cancelled orders excluded
    </p>
    <table class="data-table">
      <thead>
        <tr><th>Day</th><th class="num">Orders</th><th class="num">Sales</th><th style="width:34%">Trend</th></tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($sales7) as $row): ?>
          <?php $pct = $maxDay > 0 ? (int) round(((float) $row['sales'] / $maxDay) * 100) : 0; ?>
          <tr>
            <td><?= e(date('D, M j', strtotime($row['day']))) ?></td>
            <td class="num"><?= (int) $row['orders'] ?></td>
            <td class="num"><?= e(peso($row['sales'])) ?></td>
            <td>
              <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px">
      <h2 class="card-title">Order statistics — last 30 days</h2>
      <table class="data-table">
        <tbody>
          <?php
          $labels = ['NEW' => 'New', 'ACCEPTED' => 'Accepted', 'PREPARING' => 'Preparing',
                     'READY' => 'Ready', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled'];
          $total30 = array_sum($status30);
          foreach ($labels as $key => $label): ?>
            <tr>
              <td><?= order_status_badge($key) ?> <?= e($label) ?></td>
              <td class="num"><strong><?= (int) $status30[$key] ?></strong></td>
              <td class="num muted small">
                <?= $total30 > 0 ? number_format($status30[$key] / $total30 * 100, 1) : '0.0' ?>%
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2 class="card-title">Customer statistics — last 30 days</h2>
      <table class="data-table">
        <tbody>
          <tr><td>Customers registered</td><td class="num"><strong><?= (int) $customers['customers_total'] ?></strong></td></tr>
          <tr><td>New this month</td><td class="num"><strong><?= (int) $customers['new_month'] ?></strong></td></tr>
          <tr><td>Ordered in range</td><td class="num"><strong><?= (int) $customers['buyers'] ?></strong></td></tr>
          <tr><td>New buyers in range</td><td class="num"><strong><?= (int) $customers['new_buyers'] ?></strong></td></tr>
        </tbody>
      </table>
      <p style="margin:12px 0 0"><a class="btn btn-outline btn-sm" href="<?= e(url('manager/customers.php')) ?>">View all customers</a></p>
    </div>
  </div>
</div>

<div class="dash-grid" style="margin-top:16px">
  <div class="card">
    <h2 class="card-title">Best-selling products — last 30 days</h2>
    <?php if (!$bestSellers): ?>
      <p class="muted">No sales in this period yet — totals appear as soon as orders come in.</p>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Product</th><th class="num">Units</th><th class="num">Revenue</th><th class="num">Orders</th></tr>
        </thead>
        <tbody>
          <?php foreach ($bestSellers as $i => $row): ?>
            <tr>
              <td class="muted"><?= $i + 1 ?></td>
              <td>
                <strong><?= e($row['name']) ?></strong>
                <?php if ($row['is_active'] === null || !(int) $row['is_active']): ?>
                  <span class="badge badge-out">Discontinued</span>
                <?php endif; ?>
              </td>
              <td class="num"><strong><?= (int) $row['units'] ?></strong></td>
              <td class="num"><?= e(peso($row['revenue'])) ?></td>
              <td class="num"><?= (int) $row['orders_n'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px">
      <h2 class="card-title">
        Low-stock report
        <?php if ($lowStock): ?><span class="badge badge-low" style="display:inline"><?= count($lowStock) ?> need attention</span><?php endif; ?>
      </h2>
      <?php if (!$lowStock): ?>
        <p class="muted">All products are above their low-stock thresholds. &#9989;</p>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Min</th></tr></thead>
          <tbody>
            <?php foreach ($lowStock as $row): ?>
              <tr>
                <td>
                  <?= e($row['name']) ?><br>
                  <?php if ($row['state'] === 'OUT'): ?>
                    <span class="badge badge-out">Out of stock</span>
                  <?php else: ?>
                    <span class="badge badge-low">Low stock</span>
                  <?php endif; ?>
                </td>
                <td class="num"><strong><?= (int) $row['quantity_available'] ?></strong> <?= e($row['unit']) ?></td>
                <td class="num muted"><?= (int) $row['low_stock_threshold'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="margin:12px 0 0"><a class="btn btn-primary btn-sm" href="<?= e(url('manager/inventory.php')) ?>">Restock now</a></p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="card-title">Recent transactions</h2>
      <?php if (!$recentOrders): ?>
        <p class="muted">No orders yet — transactions appear here right after checkout.</p>
      <?php else: ?>
        <table class="data-table">
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td>
                  <strong><?= e($order['order_number']) ?></strong><br>
                  <span class="muted small"><?= e(date('M j · g:i A', strtotime($order['created_at']))) ?> · <?= e($order['customer_name']) ?></span>
                </td>
                <td class="num"><?= order_status_badge($order['status']) ?><br><strong><?= e(peso($order['total'])) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="margin:12px 0 0"><a class="btn btn-outline btn-sm" href="<?= e(url('staff/orders.php')) ?>">All orders</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="quick-links">
  <a class="btn btn-outline" href="<?= e(url('manager/products.php')) ?>">&#127859; Products</a>
  <a class="btn btn-outline" href="<?= e(url('manager/categories.php')) ?>">&#128193; Categories</a>
  <a class="btn btn-outline" href="<?= e(url('manager/inventory.php')) ?>">&#128230; Inventory</a>
  <a class="btn btn-outline" href="<?= e(url('manager/customers.php')) ?>">&#128101; Customers</a>
  <a class="btn btn-outline" href="<?= e(url('manager/users.php')) ?>">&#128100; Staff accounts</a>
  <a class="btn btn-outline" href="<?= e(url('staff/kitchen.php')) ?>">&#127859; Kitchen Display</a>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
