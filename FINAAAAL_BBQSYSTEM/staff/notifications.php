<?php
/**
 * Staff notifications — Phase 6 (architecture §10). Same owner-checked
 * read/unread flow as the customer page; rows scope to the session user.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');

$user = current_user();
$userId = (int) $user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'mark_all') {
        $n = mark_all_notifications_read($userId);
        flash('success', $n > 0 ? 'All notifications marked as read.' : 'Nothing new to mark.');
    } elseif ($action === 'mark_one') {
        $id = (int) ($_POST['notification_id'] ?? 0);
        if (mark_notification_read($id, $userId)) {
            flash('success', 'Notification marked as read.');
        } else {
            flash('warning', 'That notification is already read or does not belong to you.');
        }
    }
    redirect(url('staff/notifications.php'));
}

$notifications = list_notifications($userId, 50);
$unread = unread_notification_count($userId);
$pageTitle = 'Notifications — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Notifications</h1>
    <p class="muted">New orders and status activity. <?= $unread > 0 ? '<strong>' . $unread . ' unread.</strong>' : 'You are all caught up.' ?></p>
  </div>
  <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(url('staff/notifications.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all">
      <button class="btn btn-outline" type="submit">Mark all as read</button>
    </form>
  <?php endif; ?>
</section>

<?php if (!$notifications): ?>
  <div class="empty-state card">
    <div class="empty-icon">&#128276;</div>
    <p><strong>No notifications yet.</strong></p>
    <p class="muted">Incoming customer orders will appear here and on the Kitchen Display.</p>
    <a class="btn btn-primary" href="<?= e(url('staff/kitchen.php')) ?>">Open Kitchen Display</a>
  </div>
<?php else: ?>
  <ul class="notif-list">
    <?php foreach ($notifications as $n): ?>
      <li class="notif-item<?= (int) $n['is_read'] ? '' : ' unread' ?>">
        <span class="notif-dot" aria-hidden="true"></span>
        <div class="notif-body">
          <div class="notif-title">
            <?= e($n['title']) ?>
            <?php if (!(int) $n['is_read']): ?><span class="badge notif-unread-badge">New</span><?php endif; ?>
          </div>
          <div class="notif-message"><?= e($n['message']) ?></div>
          <div class="notif-meta muted small">
            <?= e(date('M j, Y · g:i A', strtotime($n['created_at']))) ?>
            <?php if ($n['type'] === 'NEW_ORDER'): ?> · New order<?php endif; ?>
            <?php if ($n['type'] === 'ORDER_STATUS'): ?> · Status update<?php endif; ?>
          </div>
          <div class="notif-links">
            <?php if (!empty($n['order_number'])): ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('staff/orders.php')) ?>#order-<?= (int) $n['order_id'] ?>">View order</a>
            <?php endif; ?>
            <?php if (!(int) $n['is_read']): ?>
              <form method="post" action="<?= e(url('staff/notifications.php')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_one">
                <input type="hidden" name="notification_id" value="<?= (int) $n['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit">Mark read</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
