<?php
/**
 * Notifications + order status transitions — Phase 5.
 *
 * Every notification is an in-app row (architecture §10). Rows are written
 * INSIDE the caller's transaction (checkout, status change) so a rollback
 * never leaves phantom notifications.
 *
 * advance_order_status() is the single write path for status changes:
 * validate transition → UPDATE orders → INSERT order_status_history →
 * INSERT customer ORDER_STATUS notification, all in one transaction.
 * The Phase 6 KDS/staff pages will call this function.
 */

/** Allowed status transitions (architecture §5). Terminal states allow none. */
function order_status_transitions(): array
{
    return [
        'NEW'       => ['ACCEPTED', 'CANCELLED'],
        'ACCEPTED'  => ['PREPARING', 'CANCELLED'],
        'PREPARING' => ['READY', 'CANCELLED'],
        'READY'     => ['COMPLETED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];
}

/** Insert one notification row. Type must match the notifications ENUM. */
function notify_user(int $userId, ?int $orderId, string $type, string $title, string $message): void
{
    if (!in_array($type, ['NEW_ORDER', 'ORDER_STATUS', 'LOW_STOCK', 'GENERAL'], true)) {
        $type = 'GENERAL';
    }
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, order_id, type, title, message)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $orderId,
        $type,
        mb_substr(trim($title), 0, 120),
        mb_substr(trim($message), 0, 255),
    ]);
}

/**
 * NEW_ORDER notifications for a freshly placed order: one row for the
 * customer (order confirmation) + one row for every active staff/manager
 * (their KDS/dashboard picks these up in Phase 6).
 * Must be called INSIDE the checkout transaction.
 */
function notify_new_order(array $order, int $lineCount): void
{
    $pdo = db();
    $orderId   = (int) $order['id'];
    $orderNo   = (string) $order['order_number'];
    $total     = peso($order['total']);
    $payment   = strtoupper((string) $order['payment_method']);

    notify_user(
        (int) $order['customer_id'],
        $orderId,
        'NEW_ORDER',
        'Order ' . $orderNo . ' placed',
        'Thanks! We received your order ' . $orderNo . ' (' . $total . ', ' . $payment
        . '). Watch this page for status updates.'
    );

    $staff = $pdo->query("SELECT id FROM users WHERE role IN ('STAFF', 'MANAGER') AND is_active = 1")
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($staff as $staffId) {
        notify_user(
            (int) $staffId,
            $orderId,
            'NEW_ORDER',
            'New order ' . $orderNo,
            $lineCount . ' line(s) · ' . $total . ' · ' . $payment
        );
    }
}

/** Unread notification count for one user (drives the header bell badge). */
function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Latest notifications for one user, newest first (owner-scoped). */
function list_notifications(int $userId, int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT n.*, o.order_number
         FROM notifications n
         LEFT JOIN orders o ON o.id = n.order_id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC, n.id DESC
         LIMIT ' . max(1, min(200, $limit))
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Mark one notification read. Owner-checked: returns false if not yours. */
function mark_notification_read(int $notificationId, int $userId): bool
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0');
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

/** Mark every unread notification read for one user. Returns rows affected. */
function mark_all_notifications_read(int $userId): int
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return $stmt->rowCount();
}

/**
 * Advance an order's status safely: transition validation, audit history,
 * and the customer's ORDER_STATUS notification all happen atomically.
 *
 * @return array{ok: bool, message: string, old_status?: string, new_status?: string}
 */
function advance_order_status(int $orderId, string $newStatus, ?int $changedBy, string $remarks = ''): array
{
    $pdo = db();
    $transitions = order_status_transitions();
    $newStatus = strtoupper($newStatus);
    $remarks = mb_substr(trim($remarks), 0, 255);

    if (!isset($transitions[$newStatus])) {
        return ['ok' => false, 'message' => 'Unknown order status.'];
    }

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT id, order_number, customer_id, status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $oldStatus = strtoupper((string) $order['status']);
        if ($oldStatus === $newStatus) {
            throw new RuntimeException('Order is already ' . order_status_label($newStatus) . '.');
        }
        if (!in_array($newStatus, $transitions[$oldStatus] ?? [], true)) {
            throw new RuntimeException(
                'Invalid transition: ' . order_status_label($oldStatus) . ' → ' . order_status_label($newStatus) . '.'
            );
        }

        $stmt = $pdo->prepare('UPDATE orders SET status = ?, handled_by = COALESCE(?, handled_by) WHERE id = ?');
        $stmt->execute([$newStatus, $changedBy, $orderId]);

        $stmt = $pdo->prepare(
            'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, remarks)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $oldStatus, $newStatus, $changedBy, $remarks]);

        $label = order_status_label($newStatus);
        $message = 'Your order ' . $order['order_number'] . ' is now ' . $label . '.';
        if ($remarks !== '') {
            $message .= ' ' . $remarks;
        }
        notify_user(
            (int) $order['customer_id'],
            $orderId,
            'ORDER_STATUS',
            'Order ' . $order['order_number'] . ' — ' . $label,
            $message
        );

        if ($own) {
            $pdo->commit();
        }
        return ['ok' => true, 'message' => 'Status updated.', 'old_status' => $oldStatus, 'new_status' => $newStatus];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        return ['ok' => false, 'message' => 'Could not update the order status.'];
    }
}
