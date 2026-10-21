<?php
/**
 * Manager feed queries — Phase 7 (dashboard statistics, reports, CRUD lists).
 *
 * Every number shown on the manager pages comes from these live MySQL
 * aggregates (SUM/COUNT/GROUP BY) — no hardcoded or placeholder statistics.
 * Cashflow totals use confirmed payment events minus recorded refunds.
 * Demo and unverified historical payments are excluded from real collections.
 */

/** Clamp/validate a Y-m-d date string; returns null when invalid. */
function manager_date(?string $value): ?string
{
    $value = trim((string) $value);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }
    return $value;
}

/** Cashflow uses audit-event timestamps; demo payments never count as real sales. */
function payment_collection_summary(string $from, string $to): array
{
    $stmt = db()->prepare('SELECT
        COALESCE(SUM(CASE WHEN e.event_type <> "REFUND_RECORDED" AND e.is_test = 0 THEN e.amount ELSE 0 END),0) AS collected,
        COALESCE(SUM(CASE WHEN e.event_type = "REFUND_RECORDED" AND e.is_test = 0 THEN e.amount ELSE 0 END),0) AS refunds,
        COALESCE(SUM(CASE WHEN e.event_type = "CASH_COLLECTED" AND e.is_test = 0 THEN e.amount ELSE 0 END),0) AS cash,
        COALESCE(SUM(CASE WHEN e.event_type = "ONLINE_VERIFIED" AND e.is_test = 0 THEN e.amount ELSE 0 END),0) AS gcash,
        COALESCE(SUM(e.event_type = "CASH_COLLECTED" AND e.is_test = 0),0) AS cash_n,
        COALESCE(SUM(e.event_type = "ONLINE_VERIFIED" AND e.is_test = 0),0) AS gcash_n,
        COALESCE(SUM(CASE WHEN e.event_type <> "REFUND_RECORDED" AND e.is_test = 1 THEN e.amount ELSE 0 END),0) AS test_payments
        FROM payment_events e WHERE DATE(e.created_at) BETWEEN ? AND ?');
    $stmt->execute([$from, $to]);
    $row = $stmt->fetch();
    $row['sales'] = (float) $row['collected'] - (float) $row['refunds'];
    $pending = db()->prepare('SELECT
        COALESCE(SUM(CASE WHEN payment_status IN ("UNPAID","PENDING") AND status <> "CANCELLED" AND payment_is_test = 0 THEN total ELSE 0 END),0) AS unpaid,
        COALESCE(SUM(CASE WHEN payment_status = "REFUND_REQUIRED" AND payment_is_test = 0 THEN total ELSE 0 END),0) AS refund_required,
        COALESCE(SUM(CASE WHEN payment_status = "LEGACY_UNVERIFIED" THEN total ELSE 0 END),0) AS legacy_unverified
        FROM orders WHERE DATE(created_at) BETWEEN ? AND ?');
    $pending->execute([$from, $to]);
    return array_merge($row, $pending->fetch());
}

/**
 * Resolve a report date range from GET params: from/to (Y-m-d).
 * Defaults to the last 30 days ending today; swaps when from > to.
 */
function manager_report_range(array $get): array
{
    $to = manager_date($get['to'] ?? null) ?? date('Y-m-d');
    $from = manager_date($get['from'] ?? null) ?? date('Y-m-d', strtotime($to . ' -29 days'));
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    return [$from, $to];
}

/** Headline dashboard numbers (today + all time + customers + stock). */
function manager_dashboard_stats(): array
{
    $pdo = db();

    $orders = $pdo->query(
        'SELECT COUNT(*) AS orders_total,
                SUM(DATE(created_at) = CURDATE()) AS orders_today,
                SUM(status IN ("NEW","ACCEPTED","PREPARING","READY")) AS active_now
         FROM orders'
    )->fetch();

    $orders = array_merge($orders, daily_order_outcomes());

    $cust = $pdo->query(
        'SELECT COUNT(*) AS customers_total,
                SUM(created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS customers_new_month
         FROM users WHERE role = "CUSTOMER"'
    )->fetch();

    $buyers = $pdo->query(
        'SELECT COUNT(DISTINCT customer_id) FROM orders WHERE status <> "CANCELLED"'
    )->fetchColumn();

    $low = $pdo->query(
        'SELECT COUNT(*) FROM inventory
         WHERE quantity_available = 0
            OR quantity_available <= low_stock_threshold
            OR is_available = 0'
    )->fetchColumn();

    $todayMoney = payment_collection_summary(date('Y-m-d'), date('Y-m-d'));
    $yesterdayMoney = payment_collection_summary(date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')));
    $allMoney = payment_collection_summary('1000-01-01', '9999-12-31');
    return [
        'orders_today'        => (int) $orders['orders_today'],
        'orders_total'        => (int) $orders['orders_total'],
        'active_now'          => (int) $orders['active_now'],
        'completed_today'     => (int) $orders['completed_today'],
        'cancelled_today'     => (int) $orders['cancelled_today'],
        'sales_today'         => (float) $todayMoney['sales'],
        'sales_yesterday'     => (float) $yesterdayMoney['sales'],
        'sales_all'           => (float) $allMoney['sales'],
        'customers_total'     => (int) $cust['customers_total'],
        'customers_new_month' => (int) $cust['customers_new_month'],
        'customers_buying'    => (int) $buyers,
        'low_stock'           => (int) $low,
    ];
}

/**
 * Sales summary: one row per calendar day in [from, to] (zero-filled),
 * so charts/tables never hide gaps. Collections use payment/refund event dates.
 */
function manager_sales_by_day(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT DATE(created_at) AS day,
                COUNT(*) AS orders,
                SUM(status = "CANCELLED") AS cancelled
         FROM orders
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY day'
    );
    $stmt->execute([$from, $to]);

    $byDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $byDay[$row['day']] = $row;
    }

    $payments = db()->prepare('SELECT DATE(created_at) AS day,
        SUM(CASE WHEN event_type = "REFUND_RECORDED" THEN -amount ELSE amount END) AS net
        FROM payment_events WHERE is_test = 0 AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)');
    $payments->execute([$from, $to]);
    $net = $payments->fetchAll(PDO::FETCH_KEY_PAIR);
    $rows = [];
    for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
        $key = date('Y-m-d', $d);
        $row = $byDay[$key] ?? ['day' => $key, 'orders' => 0, 'sales' => 0, 'cancelled' => 0];
        $row['sales'] = (float) ($net[$key] ?? 0);
        $rows[] = $row;
    }
    return $rows;
}

/** Order counts by status within a date range. */
function manager_status_counts(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT status, COUNT(*) AS n
         FROM orders
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY status'
    );
    $stmt->execute([$from, $to]);

    $counts = ['NEW' => 0, 'ACCEPTED' => 0, 'PREPARING' => 0, 'READY' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['n'];
    }
    return $counts;
}

/** Order counts by placement date plus collections by payment/refund event date. */
function manager_range_summary(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS orders,
                SUM(status = "CANCELLED") AS cancelled
         FROM orders
         WHERE DATE(created_at) BETWEEN ? AND ?'
    );
    $stmt->execute([$from, $to]);
    $row = $stmt->fetch();

    $money = payment_collection_summary($from, $to);
    return array_merge($money, [
        'orders'    => (int) $row['orders'],
        'cancelled' => (int) $row['cancelled'],
        'sales'     => (float) $money['sales'],
        'avg_order' => ((int) $money['cash_n'] + (int) $money['gcash_n']) > 0 ? (float) $money['collected'] / ((int) $money['cash_n'] + (int) $money['gcash_n']) : 0.0,
        'cash'      => (float) $money['cash'],
        'gcash'     => (float) $money['gcash'],
        'cash_n'    => (int) $money['cash_n'],
        'gcash_n'   => (int) $money['gcash_n'],
    ]);
}

/**
 * Best sellers in a range, ranked by units sold then revenue.
 * Joins products for the CURRENT name (order_items keeps the price snapshot);
 * only paid, non-cancelled, non-demo orders contribute to product rankings.
 */
function manager_best_sellers(string $from, string $to, int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT oi.product_id,
                COALESCE(p.name, oi.product_name) AS name,
                p.is_active,
                SUM(oi.quantity) AS units,
                SUM(oi.line_total) AS revenue,
                COUNT(DISTINCT oi.order_id) AS orders_n
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE o.payment_status = "PAID" AND o.payment_is_test = 0 AND o.status <> "CANCELLED"
           AND DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY oi.product_id, p.name, oi.product_name, p.is_active
         ORDER BY units DESC, revenue DESC, name ASC
         LIMIT ' . max(1, min(50, $limit))
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

/**
 * Customer statistics for a date range: totals + per-customer spending.
 */
function manager_customer_stats(string $from, string $to): array
{
    $pdo = db();

    $totals = $pdo->query(
        'SELECT COUNT(*) AS customers_total,
                SUM(created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS new_month
         FROM users WHERE role = "CUSTOMER"'
    )->fetch();

    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT o.customer_id) AS buyers,
                COUNT(DISTINCT CASE WHEN DATE(u.created_at) BETWEEN ? AND ? THEN o.customer_id END) AS new_buyers
         FROM orders o
         JOIN users u ON u.id = o.customer_id
         WHERE o.payment_status = "PAID" AND o.payment_is_test = 0 AND o.status <> "CANCELLED" AND DATE(o.created_at) BETWEEN ? AND ?'
    );
    $stmt->execute([$from, $to, $from, $to]);
    $range = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT u.id, u.full_name, u.phone,
                c.display_name,
                COUNT(o.id) AS orders_n,
                SUM(CASE WHEN o.payment_status = "PAID" AND o.payment_is_test = 0 AND o.status <> "CANCELLED" THEN o.total ELSE 0 END) AS spent
         FROM orders o
         JOIN users u ON u.id = o.customer_id
         LEFT JOIN customers c ON c.user_id = u.id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY u.id, u.full_name, u.phone, c.display_name
         ORDER BY spent DESC, orders_n DESC
         LIMIT 10'
    );
    $stmt->execute([$from, $to]);

    return [
        'customers_total' => (int) $totals['customers_total'],
        'new_month'       => (int) $totals['new_month'],
        'buyers'          => (int) $range['buyers'],
        'new_buyers'      => (int) $range['new_buyers'],
        'top'             => $stmt->fetchAll(),
    ];
}

/**
 * Customer list rows for manager/customers.php with lifetime order totals
 * (contact info + order counts). Optional name/phone search.
 */
function manager_customers_rows(string $q = ''): array
{
    $sql = 'SELECT u.id, u.full_name, u.phone, u.created_at,
                   c.display_name, c.email, c.address, c.remarks,
                   COUNT(o.id) AS orders_n,
                   COALESCE(SUM(CASE WHEN o.payment_status = "PAID" AND o.payment_is_test = 0 AND o.status <> "CANCELLED" THEN o.total END), 0) AS spent,
                   MAX(o.created_at) AS last_order
            FROM users u
            LEFT JOIN customers c ON c.user_id = u.id
            LEFT JOIN orders o ON o.customer_id = u.id
            WHERE u.role = "CUSTOMER"';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (u.full_name LIKE ? OR u.phone LIKE ? OR c.display_name LIKE ? OR c.email LIKE ?)';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like];
    }
    $sql .= ' GROUP BY u.id, u.full_name, u.phone, u.created_at,
                      c.display_name, c.email, c.address, c.remarks
              ORDER BY spent DESC, orders_n DESC, u.full_name ASC
              LIMIT 200';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Staff/manager accounts with how many orders each has handled. */
function manager_user_rows(): array
{
    return db()->query(
        'SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.created_at,
                (SELECT COUNT(*) FROM orders WHERE handled_by = u.id) AS handled_n
         FROM users u
         WHERE u.role IN ("STAFF", "MANAGER") AND u.email IS NOT NULL
         ORDER BY FIELD(u.role, "MANAGER", "STAFF"), u.full_name'
    )->fetchAll();
}

/** Recent stock movements (audit trail) for the inventory page. */
function manager_recent_movements(int $limit = 15): array
{
    $limit = max(1, min(50, $limit));
    return db()->query(
        "SELECT m.id, m.movement_type, m.quantity_change, m.remarks, m.created_at,
                p.name AS product_name, u.full_name AS performed_by_name
         FROM inventory_movements m
         JOIN products p ON p.id = m.product_id
         LEFT JOIN users u ON u.id = m.performed_by
         ORDER BY m.id DESC
         LIMIT $limit"
    )->fetchAll();
}

/** Latest orders across all time for the dashboard "recent transactions". */
function manager_recent_orders(int $limit = 8): array
{
    $limit = max(1, min(50, $limit));
    return db()->query(
        "SELECT o.id, o.order_number, o.status, o.total, o.payment_method, o.created_at,
                u.full_name AS customer_name
         FROM orders o
         JOIN users u ON u.id = o.customer_id
         ORDER BY o.id DESC
         LIMIT $limit"
    )->fetchAll();
}
