<?php
$notificationCustomer = $currentUser['role'] === 'CUSTOMER';
$notificationAction = $notificationCustomer ? 'customer/notifications.php' : 'staff/notifications.php';
$notificationUpdates = list_notifications((int) $currentUser['id'], 5);
$notificationUnread = unread_notification_count((int) $currentUser['id']);
?>
<details class="notification-menu">
  <summary aria-label="Notifications" title="Notifications">
    <svg class="nav-icon" aria-hidden="true"><use href="<?= e(url('assets/images/nav-icons.svg#bell')) ?>"></use></svg>
    <span class="notif-badge" id="notif-count"<?= $notificationUnread ? '' : ' style="display:none"' ?>><?= $notificationUnread ?></span>
  </summary>
  <div class="notification-panel">
    <h2>Recent updates</h2>
    <ul data-notification-list>
    <?php foreach ($notificationUpdates as $update): ?>
      <li<?= !(int) $update['is_read'] ? ' class="unread"' : '' ?>>
        <?php if ($update['order_number']): ?><a href="<?= e($notificationCustomer ? url('customer/order.php') . '?ref=' . rawurlencode($update['order_number']) : url('staff/orders.php') . '#order-' . (int) $update['order_id']) ?>"><?php endif; ?>
        <strong><?= e($update['title']) ?></strong><p><?= e($update['message']) ?></p>
        <?php if ($update['order_number']): ?></a><?php endif; ?>
      </li>
    <?php endforeach; ?>
    <?php if (!$notificationUpdates): ?><li>No updates yet.</li><?php endif; ?>
    </ul>
    <form method="post" action="<?= e(url($notificationAction)) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="mark_all">
      <button class="btn btn-outline btn-sm" type="submit">Mark all as read</button>
    </form>
  </div>
</details>
