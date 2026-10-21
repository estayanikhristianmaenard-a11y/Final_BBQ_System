<?php
/** Restore and exercise a disposable database. Never write business data to the source. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ob_start();
require dirname(__DIR__) . '/tools/database-transfer.php';
$source = transfer_config();
$admin = transfer_connection($source);
$name = 'bulkbites_baseline_' . bin2hex(random_bytes(6));
$identifier = transfer_identifier($name);
$snapshot = getenv('BB_TEST_SNAPSHOT') ?: dirname(__DIR__) . '/database/current.sql';
$checks = 0;
$gaps = [];
function baseline_check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
    echo 'PASS: ' . $message . "\n";
}
function baseline_fingerprint(PDO $pdo, string $database): array {
    $schema = transfer_identifier($database);
    $tables = $pdo->query('SHOW FULL TABLES FROM ' . $schema . ' WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);
    $result = [];
    foreach ($tables as $table) {
        $qualified = $schema . '.' . transfer_identifier($table);
        $checksum = $pdo->query('CHECKSUM TABLE ' . $qualified . ' EXTENDED')->fetch(PDO::FETCH_NUM)[1];
        if ($checksum === null) { throw new RuntimeException('Table checksum unavailable.'); }
        $result[$table] = [(int) $pdo->query('SELECT COUNT(*) FROM ' . $qualified)->fetchColumn(), (string) $checksum];
    }
    return $result;
}
function baseline_parallel(array $arguments): array {
    global $name;
    $workers = [];
    foreach ($arguments as $args) {
        $process = proc_open(array_merge([PHP_BINARY, __DIR__ . '/payment-worker.php', $name], $args),
            [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) { throw new RuntimeException('Cannot start concurrency worker.'); }
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) { throw new RuntimeException('Concurrency worker failed: ' . $errors); }
        $results[] = json_decode($output, true, 8, JSON_THROW_ON_ERROR);
    }
    return $results;
}
$before = baseline_fingerprint($admin, $source['name']);
$created = false;
$exitCode = 0;
try {
    if (!is_file($snapshot) || filesize($snapshot) === 0) { throw new RuntimeException('Create a backup first.'); }
    $admin->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    transfer_run(transfer_binary('mysql'), ['--default-character-set=utf8mb4', '--database=' . $name], $source, $snapshot);
    baseline_check(baseline_fingerprint($admin, $name) === $before, 'Backup restores every table with identical row counts and data checksums');
    echo 'Backup SHA256: ' . hash_file('sha256', $snapshot) . "\n";
    echo 'Tables verified: ' . count($before) . "\n";

    require dirname(__DIR__) . '/includes/payment-migration.php';
    $admin->exec('USE ' . $identifier);
    $oldOrders = (int) $admin->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    migrate_payment_inventory($admin);
    migrate_payment_inventory($admin);
    baseline_check((int) $admin->query('SELECT COUNT(*) FROM orders')->fetchColumn() === $oldOrders, 'Payment migration is repeatable and preserves historical orders');

    // Point the application at the restored copy before opening its singleton connection.
    require dirname(__DIR__) . '/includes/bootstrap.php';
    $GLOBALS['BB_CONFIG']['db']['name'] = $name;
    $GLOBALS['BB_CONFIG']['mail'] = []; // Never send external messages from this suite.
    $GLOBALS['BB_CONFIG']['app']['online_payments_enabled'] = true; // Isolated service tests only.
    $pdo = db();
    $initialSales = (float) manager_range_summary(date('Y-m-d'), date('Y-m-d'))['sales'];
    // Legacy ledger cases deliberately create many orders; dedicated store cases below test real limits.
    $pdo->exec('UPDATE store_settings SET use_hours=0,ordering_status="OPEN",max_active_orders=1000,cooldown_seconds=0,max_item_qty=1000,max_order_qty=1000');
    baseline_check($pdo->query('SELECT DATABASE()')->fetchColumn() === $name, 'Business checks use only the disposable restored database');
    $pdo->prepare('INSERT INTO users (full_name, email, role) VALUES (?, ?, "CUSTOMER")')->execute(['Baseline Customer', 'baseline@example.invalid']);
    $customer = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO customers (user_id, display_name) VALUES (?, ?)')->execute([$customer, 'Baseline Customer']);
    $manager = (int) $pdo->query('SELECT id FROM users WHERE role = "MANAGER" AND is_active = 1 LIMIT 1')->fetchColumn();
    baseline_check($manager > 0, 'An active manager exists after restoration');
    $pdo->exec('INSERT INTO categories (name) VALUES ("Baseline Category")');
    $category = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO products (category_id, name, price) VALUES (?, "Baseline Product", 12.50)')->execute([$category]);
    $product = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO inventory (product_id, quantity_available, low_stock_threshold) VALUES (?, 10, 2)')->execute([$product]);
    $stock = static function () use ($pdo, $product): int {
        return (int) $pdo->query('SELECT quantity_available FROM inventory WHERE product_id = ' . $product)->fetchColumn();
    };
    $_SESSION['cart'] = [];
    baseline_check(stock_state(100, 10, 0) === 'IN', 'Paused ordering does not report physical stock as empty');
    baseline_check(stock_state(0, 10, 1) === 'OUT' && stock_state(0, 10, 0) === 'OUT', 'Zero quantity remains out of stock regardless of selling availability');
    baseline_check(stock_state(3, 5, 0) === 'LOW' && stock_state(3, 5, 1) === 'LOW', 'Low-stock thresholds are independent of selling availability');
    baseline_check(!cart_add($product, 0)['ok'], 'Cart rejects zero quantity');
    baseline_check(cart_add($product, 2)['ok'], 'Available products can be added to cart');
    $result = place_order($customer, 'CASH', 'Baseline order');
    baseline_check($result['ok'], 'Cash checkout succeeds');
    $order = (int) $result['order_id'];
    baseline_check($stock() === 8 && cart_count() === 0, 'Successful checkout deducts stock and clears the cart');
    $receipt = $pdo->query('SELECT * FROM receipts WHERE order_id = ' . $order)->fetch();
    baseline_check($receipt && (float) $receipt['total'] === 25.0, 'Receipt matches authoritative product prices');
    baseline_check(count(order_items($order)) === 1 && count(order_history($order)) === 1, 'Checkout records items and initial status history');
    baseline_check(find_customer_order($result['order_number'], $customer) !== null && find_customer_order($result['order_number'], $manager) === null, 'Order lookup enforces customer ownership');
    baseline_check(unread_notification_count($customer) > 0, 'Customer receives order notification');
    $otherUnread = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE order_id=' . $order . ' AND user_id=' . $manager . ' AND is_read=0')->fetchColumn();
    baseline_check(mark_order_notifications_read($order, $customer) > 0, 'Grouped mark-read updates this customer\'s order notices');
    baseline_check((int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE order_id=' . $order . ' AND user_id=' . $manager . ' AND is_read=0')->fetchColumn() === $otherUnread, 'Grouped mark-read preserves another recipient\'s unread notices');
    baseline_check(mark_order_notifications_read($order, $customer) === 0 && mark_order_notifications_read($order, $customer + 1000000) === 0, 'Grouped mark-read is repeatable and cannot reach another owner');
    $grouped = group_notification_updates([
        ['id'=>3,'order_id'=>10], ['id'=>2,'order_id'=>null], ['id'=>1,'order_id'=>10],
    ]);
    baseline_check(count($grouped) === 2 && array_column($grouped[0], 'id') === [3, 1], 'Notification grouping preserves latest-first order and unrelated notices');
    baseline_check(!place_order($customer, 'CASH', '')['ok'], 'Empty-cart replay does not create another order');
    $_SESSION['cart'] = [$product => 9];
    $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    baseline_check(!place_order($customer, 'CASH', '')['ok'] && $stock() === 8
        && (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === $countBefore, 'Insufficient stock rolls back checkout without partial orders');
    baseline_check(!advance_order_status($order, 'COMPLETED', $manager)['ok'], 'Order cannot skip preparation states');
    foreach (['ACCEPTED', 'PREPARING', 'READY'] as $status) {
        baseline_check(advance_order_status($order, $status, $manager)['ok'], 'Staff transition succeeds: ' . $status);
    }
    baseline_check(!advance_order_status($order, 'COMPLETED', $manager)['ok'], 'Unpaid cash pickup cannot complete');
    baseline_check(!collect_cash_payment($order, $customer)['ok'], 'Customer cannot confirm cash payment');
    baseline_check(collect_cash_payment($order, $manager)['ok'], 'Staff can confirm cash collection at pickup');
    baseline_check(collect_cash_payment($order, $manager)['ok'] && (int) $pdo->query('SELECT COUNT(*) FROM payment_events WHERE order_id = ' . $order)->fetchColumn() === 1, 'Cash confirmation retries do not duplicate payments');
    baseline_check(advance_order_status($order, 'COMPLETED', $manager)['ok'], 'Paid cash pickup can complete');
    baseline_check(!advance_order_status($order, 'COMPLETED', $manager)['ok'], 'Duplicate completion is rejected');
    baseline_check(count(order_history($order)) === 5, 'Every successful transition is audited once');
    baseline_check((int) $pdo->query('SELECT COUNT(*) FROM receipt_email_deliveries WHERE order_id = ' . $order)->fetchColumn() === 1, 'Completion creates exactly one receipt delivery record');
    $pdo->prepare('UPDATE products SET name = "Renamed Product", price = 99 WHERE id = ?')->execute([$product]);
    $line = order_items($order)[0];
    baseline_check($line['product_name'] === 'Baseline Product' && (float) $line['unit_price'] === 12.5, 'Historical receipt retains product name and price snapshots');
    $today = date('Y-m-d');
    $summary = manager_range_summary($today, $today);
    $query = $pdo->prepare('SELECT COALESCE(SUM(CASE WHEN event_type = "REFUND_RECORDED" THEN -amount ELSE amount END), 0) FROM payment_events WHERE is_test = 0 AND DATE(created_at) BETWEEN ? AND ?');
    $query->execute([$today, $today]);
    baseline_check(abs($summary['sales'] - (float) $query->fetchColumn()) < 0.001, 'Manager report reconciles with collected-payment events');

    $_SESSION['cart'] = [$product => 1];
    $cancelOrder = place_order($customer, 'CASH', 'Cancellation check');
    baseline_check($cancelOrder['ok'], 'Cancellation fixture checkout succeeds');
    $beforeCancel = $stock();
    baseline_check(advance_order_status((int) $cancelOrder['order_id'], 'CANCELLED', $manager)['ok'], 'Unaccepted order can be cancelled');
    baseline_check($stock() === $beforeCancel + 1, 'Early cancellation returns reserved stock');
    baseline_check(!advance_order_status((int) $cancelOrder['order_id'], 'CANCELLED', $manager)['ok'] && $stock() === $beforeCancel + 1, 'Duplicate cancellation never returns stock twice');
    $_SESSION['cart'] = [$product => 1];
    $online = place_order($customer, 'GCASH', 'Payment gate check');
    baseline_check($online['ok'] && !advance_order_status((int) $online['order_id'], 'ACCEPTED', $manager)['ok'], 'Unpaid online order cannot be accepted');
    $onlineId = (int) $online['order_id'];
    baseline_check(!record_verified_online_payment($onlineId, 'wrong-amount', 1, 'PHP', true)['ok'], 'Online confirmation rejects a mismatched amount');
    baseline_check(!record_verified_online_payment($onlineId, 'wrong-currency', 9900, 'USD', true)['ok'], 'Online confirmation rejects a mismatched currency');
    baseline_check(record_verified_online_payment($onlineId, 'provider-1', 9900, 'PHP', true)['ok'], 'Trusted backend confirmation records online test payment');
    $eventsBefore = (int) $pdo->query('SELECT COUNT(*) FROM payment_events')->fetchColumn();
    baseline_check(record_verified_online_payment($onlineId, 'provider-1', 9900, 'PHP', true)['ok']
        && (int) $pdo->query('SELECT COUNT(*) FROM payment_events')->fetchColumn() === $eventsBefore, 'Duplicate provider confirmation is idempotent');
    baseline_check(!record_verified_online_payment($onlineId, 'provider-other', 9900, 'PHP', true)['ok'], 'Different payment for an already paid order requires reconciliation');
    baseline_check(advance_order_status($onlineId, 'ACCEPTED', $manager)['ok'], 'Paid online order can be accepted');
    $summary = manager_range_summary($today, $today);
    baseline_check((float) $summary['test_payments'] >= 99 && abs((float) $summary['sales'] - ($initialSales + 25.0)) < 0.001, 'Demo payments are separated from real collected sales');
    baseline_check(advance_order_status($onlineId, 'CANCELLED', $manager)['ok'] && $stock() === 8, 'Paid order rejected before cooking returns stock');
    baseline_check($pdo->query('SELECT payment_status FROM orders WHERE id = ' . $onlineId)->fetchColumn() === 'REFUND_REQUIRED', 'Paid cancellation requires refund handling');
    baseline_check(!record_order_refund($onlineId, $customer, 'refund-denied')['ok'], 'Customer cannot record refunds');
    baseline_check(record_order_refund($onlineId, $manager, 'refund-1')['ok'], 'Manager can record a confirmed refund');
    baseline_check(!record_order_refund($onlineId, $manager, 'refund-1')['ok'], 'Duplicate refund cannot create a second event');

    $_SESSION['cart'] = [$product => 2];
    $expired = place_order($customer, 'GCASH', 'Expiry check');
    $expiredId = (int) $expired['order_id'];
    $pdo->exec('UPDATE orders SET reservation_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ' . $expiredId);
    baseline_check(expire_order_reservations() === 1 && $stock() === 8, 'Abandoned payment expires and returns stock');
    baseline_check(expire_order_reservations() === 0 && $stock() === 8, 'Repeated expiry is harmless');
    baseline_check(record_verified_online_payment($expiredId, 'late-provider', 19800, 'PHP', false)['ok'] && $stock() === 8, 'Late payment is recorded without taking released stock again');
    baseline_check($pdo->query('SELECT payment_status FROM orders WHERE id = ' . $expiredId)->fetchColumn() === 'REFUND_REQUIRED'
        && !advance_order_status($expiredId, 'ACCEPTED', $manager)['ok'], 'Late payment requires refund and never revives the order');
    baseline_check(abs((float) manager_range_summary($today, $today)['sales'] - ($initialSales + 223.0)) < 0.001, 'Money awaiting refund remains collected until an actual refund is recorded');
    baseline_check(record_order_refund($expiredId, $manager, 'late-refund')['ok'] && abs((float) manager_range_summary($today, $today)['sales'] - ($initialSales + 25.0)) < 0.001, 'Confirmed real refund reduces net collected sales');

    $_SESSION['cart'] = [$product => 1];
    $prepared = place_order($customer, 'CASH', 'Preparation cancellation');
    $preparedId = (int) $prepared['order_id'];
    advance_order_status($preparedId, 'ACCEPTED', $manager);
    advance_order_status($preparedId, 'PREPARING', $manager);
    $beforePreparedCancel = $stock();
    baseline_check(advance_order_status($preparedId, 'CANCELLED', $manager)['ok'] && $stock() === $beforePreparedCancel, 'Food already in preparation is not silently returned to stock');
    baseline_check($pdo->query('SELECT stock_state FROM orders WHERE id = ' . $preparedId)->fetchColumn() === 'WASTED', 'Prepared cancellation records stock as waste');
    $pdo->beginTransaction();
    $_SESSION['cart'] = []; // Nested payment checks must leave the outer transaction usable.
    baseline_check(!record_order_refund($expiredId, $manager, 'again')['ok'] && $pdo->inTransaction(), 'Failed nested operation rolls back its savepoint without ending caller transaction');
    $pdo->rollBack();
    $pdo->prepare('INSERT INTO products (category_id, name, price) VALUES (?, "Last stock race", 1.25)')->execute([$category]);
    $raceProduct = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO inventory (product_id, quantity_available) VALUES (?, 1)')->execute([$raceProduct]);
    $race = baseline_parallel([['checkout', (string) $customer, (string) $raceProduct], ['checkout', (string) $customer, (string) $raceProduct]]);
    baseline_check(count(array_filter($race, static fn(array $result): bool => $result['ok'])) === 1
        && (int) $pdo->query('SELECT quantity_available FROM inventory WHERE product_id = ' . $raceProduct)->fetchColumn() === 0,
        'Two concurrent checkouts for the last item produce one order with no overselling');
    $_SESSION['cart'] = [$product => 1];
    $raceOnline = place_order($customer, 'GCASH', 'Expiry and payment race');
    $raceOrder = (int) $raceOnline['order_id'];
    $beforeRaceStock = $stock();
    $pdo->exec('UPDATE orders SET reservation_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ' . $raceOrder);
    $raceResults = baseline_parallel([['expire'], ['online', (string) $raceOrder, 'race-payment', '9900']]);
    baseline_check($raceResults[0]['ok'] && $raceResults[1]['ok'] && $stock() === $beforeRaceStock + 1
        && $pdo->query('SELECT payment_status FROM orders WHERE id = ' . $raceOrder)->fetchColumn() === 'REFUND_REQUIRED',
        'Concurrent expiry and late payment return stock once and require a refund');
    require __DIR__ . '/paymongo-cases.php';
    require __DIR__ . '/store-cases.php';
    require __DIR__ . '/demo-payment-cases.php';
    require __DIR__ . '/business-review-cases.php';
    require __DIR__ . '/delivery-cases.php';
    foreach ($gaps as $gap) { echo 'WORKFLOW GAP: ' . $gap . "\n"; }
    echo $checks . ' baseline assertions passed; ' . count($gaps) . " workflow gaps recorded.\n";
} catch (Throwable $error) {
    $exitCode = 1;
    echo 'FAIL: ' . $error->getMessage() . "\n";
} finally {
    if ($created && $name !== $source['name'] && preg_match('/^bulkbites_baseline_[a-f0-9]{12}$/D', $name)) {
        $admin->exec('DROP DATABASE ' . $identifier);
        echo "Disposable baseline database removed.\n";
    }
    if (baseline_fingerprint($admin, $source['name']) !== $before) {
        $exitCode = 1;
        echo "FAIL: Source database changed during baseline checks.\n";
    } else { echo "Source database unchanged (all table checksums match).\n"; }
}
ob_end_flush();
exit($exitCode);
