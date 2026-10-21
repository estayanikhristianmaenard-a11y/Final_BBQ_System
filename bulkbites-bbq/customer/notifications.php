<?php
/**
 * Customer notifications — order placements and status changes (architecture §10).
 * Unread rows are highlighted; "Mark read" / "Mark all read" are owner-checked
 * UPDATEs, and every action redirects (PRG) so refresh never repeats it.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');

$user = current_user();
$userId = (int) $user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'mark_all') {
        $n = mark_all_notifications_read($userId);
        flash('success', $n > 0 ? 'All notifications marked as read.' : 'Nothing new to mark.');
    } elseif ($action === 'mark_order') {
        $n = mark_order_notifications_read((int) ($_POST['order_id'] ?? 0), $userId);
        flash('success', $n > 0 ? 'Order updates marked as read.' : 'Nothing new to mark.');
    } elseif ($action === 'mark_one') {
        $id = (int) ($_POST['notification_id'] ?? 0);
        if (mark_notification_read($id, $userId)) {
            flash('success', 'Notification marked as read.');
        } else {
            flash('warning', 'That notification is already read or does not belong to you.');
        }
    }
    redirect(url('customer/orders.php'));
}

redirect(url('customer/orders.php'));
