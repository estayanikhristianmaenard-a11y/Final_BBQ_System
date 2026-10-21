<?php
/**
 * Staff feed queries — Phase 6 (KDS, dashboard, inventory).
 * Pure read helpers shared by the staff pages and the api/orders.php poller.
 */

/** Product row shape used by queue/order pages: id => items of one order. */
function orders_with_items(array $filters, int $limit = 100): array
{
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filters['statuses'])) {
        $in = [];
        foreach ($filters['statuses'] as $i => $status) {
            $key = ':st' . $i;
            $in[] = $key;
            $params[$key] = $status;
        }
        $where[] = 'o.status IN (' . implode(', ', $in) . ')';
    }
    if (!empty($filters['outcome_today'])) {
        $where[] = 'EXISTS (SELECT 1 FROM order_status_history oh WHERE oh.order_id=o.id AND oh.new_status=o.status GROUP BY oh.order_id HAVING MAX(oh.created_at) >= CURDATE() AND MAX(oh.created_at) < CURDATE() + INTERVAL 1 DAY)';
    }
    if (!empty($filters['today'])) {
        $where[] = 'DATE(o.created_at) = CURDATE()';
    }
    if (!empty($filters['order_number'])) {
        $where[] = 'o.order_number = :onum';
        $params[':onum'] = $filters['order_number'];
    }
    if (!empty($filters['id'])) {
        $where[] = 'o.id = :oid';
        $params[':oid'] = (int) $filters['id'];
    }

    $sql = 'SELECT o.id, o.order_number, o.status, o.subtotal, o.total, o.payment_method, o.notes,
                   o.payment_status, o.payment_is_test, o.stock_state, o.reservation_expires_at,
                   o.customer_id, o.handled_by, o.created_at, o.pickup_name, o.pickup_contact, o.pickup_location, o.estimated_ready_at, o.fulfillment_type, o.delivery_fee, o.delivery_address,
                   u.full_name AS customer_name, cu.display_name AS customer_display,
                   h.full_name AS handler_name
            FROM orders o
            JOIN users u ON u.id = o.customer_id
            LEFT JOIN customers cu ON cu.user_id = o.customer_id
            LEFT JOIN users h ON h.id = o.handled_by'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . (!empty($filters['recent_first']) ? ' ORDER BY o.created_at DESC, o.id DESC' : ' ORDER BY FIELD(o.status, "NEW", "ACCEPTED", "PREPARING", "READY", "COMPLETED", "CANCELLED"), o.created_at ASC, o.id ASC')
         . ' LIMIT ' . max(1, min(500, $limit));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    if (!$orders) {
        return [];
    }

    // One items query for all fetched orders
    $ids = array_column($orders, 'id');
    $in = implode(',', array_map('intval', $ids));
    $itemsStmt = $pdo->query(
        "SELECT order_id, product_name, quantity, unit_price, line_total
         FROM order_items WHERE order_id IN ($in) ORDER BY id"
    );
    $byOrder = [];
    foreach ($itemsStmt->fetchAll() as $item) {
        $byOrder[(int) $item['order_id']][] = $item;
    }

    foreach ($orders as $i => $order) {
        $orders[$i]['items'] = $byOrder[(int) $order['id']] ?? [];
        $orders[$i]['customer_label'] = $order['pickup_name'] ?: ($order['customer_display'] ?: $order['customer_name']);
        $orders[$i]['age_seconds'] = max(0, time() - strtotime($order['created_at']));
    }
    return $orders;
}

/** Active kitchen queue: NEW / ACCEPTED / PREPARING / READY (oldest first). */
function staff_active_queue(): array
{
    return orders_with_items(['statuses' => ['NEW', 'ACCEPTED', 'PREPARING', 'READY']], 100);
}

/** Today's terminal transitions, in the database's configured Philippine time. */
function daily_order_outcomes(): array
{
    $counts = ['completed_today' => 0, 'cancelled_today' => 0];
    $rows = db()->query(
        'SELECT h.new_status, COUNT(*) AS n
         FROM orders o
         JOIN (
             SELECT order_id, new_status, MAX(created_at) AS changed_at
             FROM order_status_history
             WHERE new_status IN ("COMPLETED", "CANCELLED")
             GROUP BY order_id, new_status
         ) h ON h.order_id = o.id AND h.new_status = o.status
         WHERE h.changed_at >= CURDATE() AND h.changed_at < CURDATE() + INTERVAL 1 DAY
         GROUP BY h.new_status'
    )->fetchAll();
    foreach ($rows as $row) {
        $counts[$row['new_status'] === 'COMPLETED' ? 'completed_today' : 'cancelled_today'] = (int) $row['n'];
    }
    return $counts;
}

/** Counts for dashboard/KDS headers; new includes both payment stages. */
function staff_order_counts(): array
{
    $pdo = db();
    $counts = ['new' => 0, 'awaiting_acceptance' => 0, 'awaiting_payment' => 0, 'in_progress' => 0, 'ready' => 0] + daily_order_outcomes();

    $stmt = $pdo->query(
        'SELECT status, payment_method, payment_status, COUNT(*) AS n
         FROM orders GROUP BY status, payment_method, payment_status'
    );
    foreach ($stmt->fetchAll() as $row) {
        $n = (int) $row['n'];
        switch ($row['status']) {
            case 'NEW':
                $counts['new'] += $n;
                $stage = $row['payment_method'] !== 'CASH' && $row['payment_status'] !== 'PAID'
                    ? 'awaiting_payment' : 'awaiting_acceptance';
                $counts[$stage] += $n;
                break;
            case 'ACCEPTED':
            case 'PREPARING': $counts['in_progress'] += $n; break;
            case 'READY':     $counts['ready'] += $n; break;
        }
    }
    return $counts;
}

/**
 * Inventory rows with a physical state: OUT (qty 0),
 * LOW (0 < qty <= threshold), IN (above threshold) — architecture §9.
 */
function staff_inventory_rows(): array
{
    $rows = db()->query(
        'SELECT p.id, p.name, p.is_active, c.name AS category_name,
                i.quantity_available, i.unit, i.low_stock_threshold, i.is_available,
                i.last_updated, u.full_name AS updated_by_name
         FROM inventory i
         JOIN products p ON p.id = i.product_id
         JOIN categories c ON c.id = p.category_id
         LEFT JOIN users u ON u.id = i.updated_by
         ORDER BY c.name, p.name'
    )->fetchAll();

    foreach ($rows as $k => $row) {
        $rows[$k]['state'] = stock_state(
            (int) $row['quantity_available'],
            (int) $row['low_stock_threshold'],
            (int) $row['is_available']
        );
    }
    return $rows;
}

/** Physical stock state; selling availability is displayed separately (architecture §9). */
function stock_state(int $qty, int $threshold, int $available): string
{
    if ($qty <= 0) {
        return 'OUT';
    }
    if ($qty <= $threshold) {
        return 'LOW';
    }
    return 'IN';
}

/** Products currently LOW or OUT — dashboard panel. */
function staff_low_stock(): array
{
    $out = [];
    foreach (staff_inventory_rows() as $row) {
        if ($row['state'] !== 'IN') {
            $out[] = $row;
        }
    }
    return $out;
}
