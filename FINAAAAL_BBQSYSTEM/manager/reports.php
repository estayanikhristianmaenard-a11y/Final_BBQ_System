<?php
/**
 * Reports — Phase 7, MANAGER only (architecture §3/§6).
 * Sales by date range, status summary, best sellers, customer statistics,
 * payment split, and the low-stock report. Every figure is a live aggregate
 * over the selected range (cancelled orders excluded from sales figures).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

// --- Date filtering (validated Y-m-d, defaults to the last 30 days) ---------
$hadFilter = isset($_GET['from']) || isset($_GET['to']);
[$from, $to] = manager_report_range($_GET);
if ($hadFilter) {
    $rawFrom = trim((string) ($_GET['from'] ?? ''));
    $rawTo = trim((string) ($_GET['to'] ?? ''));
    if (($rawFrom !== '' && manager_date($rawFrom) === null) || ($rawTo !== '' && manager_date($rawTo) === null)) {
        flash('warning', 'Invalid date ignored — dates must look like 2026-10-08.');
        [$from, $to] = manager_report_range([]);
    }
}

$summary = manager_range_summary($from, $to);
$status = manager_status_counts($from, $to);
$byDay = manager_sales_by_day($from, $to);
$best = manager_best_sellers($from, $to, 10);
$customers = manager_customer_stats($from, $to);
$lowStock = staff_low_stock();

$maxDay = 0.0;
foreach ($byDay as $row) {
    $maxDay = max($maxDay, (float) $row['sales']);
}

/** Preset date-range links (architecture: quick date filtering). */
function report_presets(string $currentFrom, string $currentTo): array
{
    $today = date('Y-m-d');
    $presets = [
        'Today'     => [$today, $today],
        'Last 7 days'  => [date('Y-m-d', strtotime('-6 days')), $today],
        'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), $today],
        'This month'   => [date('Y-m-01'), $today],
        'Last month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    ];
    $links = [];
    foreach ($presets as $label => [$f, $t]) {
        $links[$label] = ['from' => $f, 'to' => $t, 'active' => $f === $currentFrom && $t === $currentTo];
    }
    return $links;
}
$presets = report_presets($from, $to);

$pageTitle = 'Reports — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Sales &amp; operations reports</h1>
    <p class="muted">Every figure below is computed live from MySQL for the selected range · cancelled orders are excluded from sales.</p>
  </div>
</section>

<form class="filter-bar card" method="get" action="<?= e(url('manager/reports.php')) ?>">
  <div class="filter-fields">
    <div class="form-group">
      <label for="f-from">From</label>
      <input type="date" id="f-from" name="from" value="<?= e($from) ?>" max="<?= e(date('Y-m-d')) ?>">
    </div>
    <div class="form-group">
      <label for="f-to">To</label>
      <input type="date" id="f-to" name="to" value="<?= e($to) ?>" max="<?= e(date('Y-m-d')) ?>">
    </div>
    <div class="form-group filter-go">
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Apply range</button>
    </div>
  </div>
  <div class="filter-presets">
    <?php foreach ($presets as $label => $p): ?>
      <a class="cat-tab<?= $p['active'] ? ' active' : '' ?>"
         href="<?= e(url('manager/reports.php') . '?' . http_build_query(['from' => $p['from'], 'to' => $p['to']])) ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
    <span class="muted small" style="align-self:center">Showing <?= e($from) ?> → <?= e($to) ?></span>
  </div>
</form>

<div class="stat-grid">
  <div class="stat-card stat-ready">
    <strong><?= e(peso($summary['sales'])) ?></strong>
    <span>Total sales</span>
    <em><?= $summary['orders'] ?> order(s) in range · <?= $summary['cancelled'] ?> cancelled</em>
  </div>
  <div class="stat-card stat-new">
    <strong><?= $summary['orders'] ?></strong>
    <span>Orders</span>
    <em><?= (int) array_sum($status) - $summary['cancelled'] ?> fulfilled · <?= $summary['cancelled'] ?> cancelled</em>
  </div>
  <div class="stat-card stat-progress">
    <strong><?= e(peso($summary['avg_order'])) ?></strong>
    <span>Average order</span>
    <em>mean value of non-cancelled orders</em>
  </div>
  <div class="stat-card stat-done">
    <strong><?= e(peso($summary['cash'])) ?> / <?= e(peso($summary['gcash'])) ?></strong>
    <span>Cash / GCASH</span>
    <em><?= $summary['cash_n'] ?> cash · <?= $summary['gcash_n'] ?> gcash order(s)</em>
  </div>
</div>

<div class="card">
  <h2 class="card-title">Daily sales — <?= count($byDay) ?> day(s) in range</h2>
  <div class="queue-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Day</th><th class="num">Orders</th><th class="num">Cancelled</th><th class="num">Sales</th><th style="width:30%">Trend</th></tr>
      </thead>
      <tbody>
        <?php
          $rangeOrders = 0;
          $rangeSales = 0.0;
        ?>
        <?php foreach (array_reverse($byDay) as $row): ?>
          <?php
            $rangeOrders += (int) $row['orders'];
            $rangeSales += (float) $row['sales'];
            $pct = $maxDay > 0 ? (int) round(((float) $row['sales'] / $maxDay) * 100) : 0;
          ?>
          <tr>
            <td><?= e(date('D, M j Y', strtotime($row['day']))) ?></td>
            <td class="num"><?= (int) $row['orders'] ?></td>
            <td class="num"><?= (int) $row['cancelled'] ?></td>
            <td class="num"><strong><?= e(peso($row['sales'])) ?></strong></td>
            <td><div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$byDay): ?>
          <tr><td colspan="5" class="muted">No days in range.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td>Total</td>
          <td class="num"><?= $rangeOrders ?></td>
          <td class="num"><?= $summary['cancelled'] ?></td>
          <td class="num"><?= e(peso($rangeSales)) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="dash-grid" style="margin-top:16px">
  <div class="card">
    <h2 class="card-title">Best-selling products — top 10</h2>
    <?php if (!$best): ?>
      <p class="muted">No sales in this range — pick a wider date range or wait for orders.</p>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Product</th><th class="num">Units</th><th class="num">Revenue</th><th class="num">Orders</th></tr>
        </thead>
        <tbody>
          <?php $totalUnits = array_sum(array_column($best, 'units')); ?>
          <?php foreach ($best as $i => $row): ?>
            <tr>
              <td class="muted"><?= $i + 1 ?></td>
              <td>
                <strong><?= e($row['name']) ?></strong>
                <?php if ($row['is_active'] !== null && !(int) $row['is_active']): ?>
                  <span class="badge badge-out">Discontinued</span>
                <?php endif; ?>
                <?php if ($totalUnits > 0): ?>
                  <div class="bar-track" style="margin-top:4px">
                    <div class="bar-fill" style="width:<?= (int) round($row['units'] / $totalUnits * 100) ?>%"></div>
                  </div>
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
      <h2 class="card-title">Order status summary</h2>
      <table class="data-table">
        <tbody>
          <?php
          $labels = ['NEW' => 'New', 'ACCEPTED' => 'Accepted', 'PREPARING' => 'Preparing',
                     'READY' => 'Ready', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled'];
          $total = array_sum($status);
          foreach ($labels as $key => $label): ?>
            <tr>
              <td><?= order_status_badge($key) ?> <?= e($label) ?></td>
              <td class="num"><strong><?= (int) $status[$key] ?></strong></td>
              <td class="num muted small"><?= $total > 0 ? number_format($status[$key] / $total * 100, 1) : '0.0' ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-bottom:16px">
      <h2 class="card-title">Customer statistics</h2>
      <table class="data-table">
        <tbody>
          <tr><td>Registered customers</td><td class="num"><strong><?= (int) $customers['customers_total'] ?></strong></td></tr>
          <tr><td>New this month</td><td class="num"><strong><?= (int) $customers['new_month'] ?></strong></td></tr>
          <tr><td>Ordered in range</td><td class="num"><strong><?= (int) $customers['buyers'] ?></strong></td></tr>
          <tr><td>First-time buyers in range</td><td class="num"><strong><?= (int) $customers['new_buyers'] ?></strong></td></tr>
        </tbody>
      </table>
      <?php if ($customers['top']): ?>
        <h3 class="card-title" style="margin-top:16px">Top spenders in range</h3>
        <table class="data-table">
          <tbody>
            <?php foreach ($customers['top'] as $t): ?>
              <tr>
                <td><?= e($t['display_name'] ?: $t['full_name']) ?></td>
                <td class="num"><?= (int) $t['orders_n'] ?> order(s)</td>
                <td class="num"><strong><?= e(peso($t['spent'])) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 class="card-title">
        Low-stock report
        <?php if ($lowStock): ?><span class="badge badge-low" style="display:inline"><?= count($lowStock) ?> item(s)</span><?php endif; ?>
      </h2>
      <?php if (!$lowStock): ?>
        <p class="muted">All products are above their thresholds. &#9989;</p>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Min</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($lowStock as $row): ?>
              <tr>
                <td>
                  <?= e($row['name']) ?>
                  <?php if ($row['state'] === 'OUT'): ?><span class="badge badge-out">Out</span>
                  <?php else: ?><span class="badge badge-low">Low</span><?php endif; ?>
                </td>
                <td class="num"><strong><?= (int) $row['quantity_available'] ?></strong></td>
                <td class="num muted"><?= (int) $row['low_stock_threshold'] ?></td>
                <td><a class="btn btn-sm btn-outline" href="<?= e(url('manager/inventory.php')) ?>">Restock</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
