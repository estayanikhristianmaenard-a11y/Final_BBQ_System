<?php
/**
 * Inventory monitoring — Phase 6, READ-ONLY for staff (architecture matrix:
 * staff 👁 view, manager ✅ full control). Stock adjustments/restock/threshold
 * edits are manager functionality and are deliberately NOT exposed here.
 * Low-stock indicators derive from quantity vs threshold vs availability.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');

$rows = staff_inventory_rows();
$lowCount = count(array_filter($rows, fn($r) => $r['state'] !== 'IN'));

$pageTitle = 'Inventory — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Inventory</h1>
    <p class="muted">Live stock levels, thresholds, and availability · updated in real time from the products database.</p>
  </div>
  <div class="inv-summary">
    <?php if ($lowCount > 0): ?>
      <span class="badge badge-low"><?= $lowCount ?> item(s) need attention</span>
    <?php else: ?>
      <span class="badge badge-in">All stock healthy</span>
    <?php endif; ?>
  </div>
</section>

<div class="flash flash-info">
  &#128066; Read-only view — restocking, thresholds, and availability toggles are managed by the manager.
</div>

<div class="card">
  <table class="data-table inv-table">
    <thead>
      <tr>
        <th>Product</th>
        <th>Category</th>
        <th class="num">Available</th>
        <th class="num">Threshold</th>
        <th>State</th>
        <th>Availability</th>
        <th>Last updated</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <strong><?= e($row['name']) ?></strong>
            <?php if (!(int) $row['is_active']): ?><span class="badge badge-out">Inactive</span><?php endif; ?>
          </td>
          <td class="muted"><?= e($row['category_name']) ?></td>
          <td class="num"><strong><?= (int) $row['quantity_available'] ?></strong> <?= e($row['unit']) ?></td>
          <td class="num muted"><?= (int) $row['low_stock_threshold'] ?></td>
          <td>
            <?php if ($row['state'] === 'IN'): ?>
              <span class="badge badge-in">In stock</span>
            <?php elseif ($row['state'] === 'LOW'): ?>
              <span class="badge badge-low">Low stock</span>
            <?php else: ?>
              <span class="badge badge-out">Out of stock</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int) $row['is_available']): ?>
              <span class="badge badge-in">Available</span>
            <?php else: ?>
              <span class="badge badge-out">Unavailable</span>
            <?php endif; ?>
          </td>
          <td class="muted small">
            <?= e(date('M j · g:i A', strtotime($row['last_updated']))) ?>
            <?php if ($row['updated_by_name']): ?><br>by <?= e($row['updated_by_name']) ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
