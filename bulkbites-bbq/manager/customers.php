<?php
/**
 * Customer directory — Phase 7, MANAGER only (architecture §3: staff cannot
 * see customer lists). Read-only view: contact info, address, remarks, and
 * lifetime order totals straight from MySQL.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 60) {
    $q = mb_substr($q, 0, 60);
}
$customers = manager_customers_rows($q);
$totals = manager_customer_stats(date('Y-m-d', strtotime('-29 days')), date('Y-m-d'));

$pageTitle = 'Customers — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Customers</h1>
    <p class="muted">
      <?= (int) $totals['customers_total'] ?> registered ·
      <?= (int) $totals['buyers'] ?> ordered in the last 30 days ·
      <?= (int) $totals['new_month'] ?> new this month
    </p>
  </div>
  <form class="menu-toolbar" method="get" action="<?= e(url('manager/customers.php')) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email…" maxlength="60">
    <button class="btn btn-primary" type="submit">Search</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-outline" href="<?= e(url('manager/customers.php')) ?>">Clear</a>
    <?php endif; ?>
  </form>
</section>
<?php require dirname(__DIR__) . '/includes/settings-nav.php'; ?>

<?php if (!$customers): ?>
  <div class="empty-state card">
    <div class="empty-icon" aria-hidden="true"><svg class="nav-icon" focusable="false"><use href="<?= e(url('assets/images/nav-icons.svg#users')) ?>"></use></svg></div>
    <p><strong><?= $q !== '' ? 'No customers match “' . e($q) . '”.' : 'No customer accounts yet.' ?></strong></p>
    <p class="muted">Accounts are created automatically after a customer verifies their email code.</p>
  </div>
<?php else: ?>
  <div class="card queue-wrap">
    <table class="data-table queue-table">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Contact</th>
          <th class="num">Orders</th>
          <th class="num">Total spent</th>
          <th>Last order</th>
          <th>Member since</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $row): ?>
          <tr>
            <td>
              <strong><?= e($row['display_name'] ?: $row['full_name']) ?></strong>
              <?php if ($row['display_name'] && $row['display_name'] !== $row['full_name']): ?>
                <br><span class="muted small"><?= e($row['full_name']) ?></span>
              <?php endif; ?>
              <?php if ($row['address']): ?><br><span class="muted small"><?= e($row['address']) ?></span><?php endif; ?>
              <?php if ($row['remarks']): ?><br><span class="queue-notes">Notes: <?= e($row['remarks']) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($row['email']): ?><?= e($row['email']) ?><?php endif; ?>
              <?php if ($row['phone']): ?><div class="muted small"><?= e($row['phone']) ?></div><?php endif; ?>
            </td>
            <td class="num"><strong><?= (int) $row['orders_n'] ?></strong></td>
            <td class="num"><strong><?= e(peso($row['spent'])) ?></strong></td>
            <td class="muted small"><?= $row['last_order'] ? e(date('M j, Y · g:i A', strtotime($row['last_order']))) : '—' ?></td>
            <td class="muted small"><?= e(date('M j, Y', strtotime($row['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small" style="margin-top:10px">Showing <?= count($customers) ?> of up to 200 · totals exclude cancelled orders.</p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
