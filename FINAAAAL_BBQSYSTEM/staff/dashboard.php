<?php
/**
 * Staff dashboard — Phase 6 (architecture §4): order summary + low stock +
 * notifications. Read-only overview; actions live on the KDS/queue pages.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');

$user = current_user();
$userId = (int) $user['id'];
$counts = staff_order_counts();
$lowStock = staff_low_stock();
$notifications = list_notifications($userId, 5);
$unread = unread_notification_count($userId);

$pageTitle = 'Staff Dashboard — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Hello, <?= e($user['full_name']) ?> 👋</h1>
    <p class="muted">Signed in as <strong><?= e($user['role']) ?></strong> · <?= e(mask_phone($user['phone'])) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('staff/kitchen.php')) ?>">&#127859; Open Kitchen Display</a>
</section>

<div class="stat-grid">
  <a class="stat-card stat-new" href="<?= e(url('staff/kitchen.php')) ?>">
    <strong><?= (int) $counts['new'] ?></strong>
    <span>New orders</span>
    <em>waiting to be accepted</em>
  </a>
  <a class="stat-card stat-progress" href="<?= e(url('staff/kitchen.php')) ?>">
    <strong><?= (int) $counts['in_progress'] ?></strong>
    <span>In progress</span>
    <em>accepted / preparing</em>
  </a>
  <a class="stat-card stat-ready" href="<?= e(url('staff/orders.php')) ?>?status=READY">
    <strong><?= (int) $counts['ready'] ?></strong>
    <span>Ready</span>
    <em>awaiting handover</em>
  </a>
  <a class="stat-card stat-done" href="<?= e(url('staff/orders.php')) ?>?status=COMPLETED">
    <strong><?= (int) $counts['completed_today'] ?></strong>
    <span>Completed today</span>
    <em><?= (int) $counts['cancelled_today'] ?> cancelled today</em>
  </a>
</div>

<div class="dash-grid">
  <div class="card">
    <h2 class="card-title">
      Notifications
      <?php if ($unread > 0): ?><span class="notif-badge" style="display:inline"><?= $unread ?> new</span><?php endif; ?>
    </h2>
    <?php if (!$notifications): ?>
      <p class="muted">No notifications yet — new orders will appear here.</p>
    <?php else: ?>
      <ul class="notif-list">
        <?php foreach ($notifications as $n): ?>
          <li class="notif-item<?= (int) $n['is_read'] ? '' : ' unread' ?>">
            <span class="notif-dot" aria-hidden="true"></span>
            <div class="notif-body">
              <div class="notif-title"><?= e($n['title']) ?></div>
              <div class="notif-message"><?= e($n['message']) ?></div>
              <div class="notif-meta muted small"><?= e(date('M j, g:i A', strtotime($n['created_at']))) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <a class="btn btn-outline btn-block mt" href="<?= e(url('staff/notifications.php')) ?>">All notifications</a>
  </div>

  <div class="card">
    <h2 class="card-title">
      Inventory watch
      <?php if ($lowStock): ?><span class="badge badge-low" style="display:inline"><?= count($lowStock) ?> item(s) need attention</span><?php endif; ?>
    </h2>
    <?php if (!$lowStock): ?>
      <p class="muted">&#9989; All stock levels are healthy (above threshold).</p>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>Product</th><th class="num">Stock</th><th>State</th></tr></thead>
        <tbody>
          <?php foreach ($lowStock as $row): ?>
            <tr>
              <td><?= e($row['name']) ?></td>
              <td class="num"><?= (int) $row['quantity_available'] ?> / <?= (int) $row['low_stock_threshold'] ?> <?= e($row['unit']) ?></td>
              <td>
                <span class="badge <?= $row['state'] === 'OUT' ? 'badge-out' : 'badge-low' ?>">
                  <?= $row['state'] === 'OUT' ? 'Out of stock' : 'Low stock' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <a class="btn btn-outline btn-block mt" href="<?= e(url('staff/inventory.php')) ?>">View inventory</a>
  </div>
</div>

<div class="quick-links">
  <a class="btn btn-outline" href="<?= e(url('staff/kitchen.php')) ?>">&#127859; Kitchen Display</a>
  <a class="btn btn-outline" href="<?= e(url('staff/orders.php')) ?>">&#128203; Order queue</a>
  <a class="btn btn-outline" href="<?= e(url('staff/inventory.php')) ?>">&#128230; Inventory</a>
  <a class="btn btn-outline" href="<?= e(url('staff/notifications.php')) ?>">&#128276; Notifications</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
