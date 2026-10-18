<?php
/** Run through email-login.mjs: isolated MySQL database + local TLS SMTP fixture. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ob_start(); // Keep CLI assertion output from interfering with session cookie headers.
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/email-migration.php';
$original = config('db');
$name = 'bulkbites_email_test_' . bin2hex(random_bytes(5));
$admin = new PDO('mysql:host=' . $original['host'] . ';charset=utf8mb4', $original['user'], $original['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
    echo 'PASS: ' . $label . "\n";
}
function captured_code(): string {
    return json_decode(file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/'), true)['code'];
}
function captured_messages(): array {
    return json_decode(file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/'), true)['messages'];
}
function release_cooldown(string $email): void {
    db()->prepare('UPDATE email_otp_records SET resend_after = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE email = ?')->execute([$email]);
}
function verify_login(string $email, string $code): array {
    $result = otp_verify($email, $code);
    return $result['ok'] ? complete_otp_login($email) : $result;
}
try {
    $admin->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $source = '`' . str_replace('`', '``', $original['name']) . '`';
    foreach (['users', 'customers', 'rate_limits', 'settings', 'orders', 'receipts', 'order_items', 'notifications', 'order_status_history'] as $table) {
        $admin->exec("CREATE TABLE `{$name}`.`{$table}` LIKE {$source}.`{$table}`");
    }
    // Exercise the upgrade from the old phone-only schema, including existing data.
    $admin->exec("ALTER TABLE `{$name}`.users DROP INDEX uq_users_email, DROP COLUMN email, MODIFY phone VARCHAR(20) NOT NULL");
    $admin->exec("ALTER TABLE `{$name}`.customers MODIFY email VARCHAR(100) DEFAULT NULL");
    $admin->exec("ALTER TABLE `{$name}`.users ADD CONSTRAINT chk_users_passwd CHECK (role = 'CUSTOMER' OR password_hash IS NOT NULL)");
    $admin->exec("INSERT INTO `{$name}`.users (full_name, phone, role) VALUES ('Legacy Customer', '09999999999', 'CUSTOMER')");
    $GLOBALS['BB_CONFIG']['db']['name'] = $name;
    $pdo = db();
    migrate_email_login($pdo);
    migrate_email_login($pdo);
    check(true, 'Database upgrade is repeatable');
    check((int) $pdo->query("SELECT COUNT(*) FROM users WHERE phone = '09999999999' AND email IS NULL")->fetchColumn() === 1, 'Upgrade preserves legacy accounts');
    check(!complete_otp_login('customer@example.com')['ok'], 'Account creation requires verified email');
    $GLOBALS['BB_CONFIG']['mail'] = [];
    check(!otp_request('customer@example.com', '127.0.0.1')['ok'], 'Unconfigured SMTP fails without pretending to send');
    $GLOBALS['BB_CONFIG']['mail'] = [
        'host' => 'localhost', 'port' => (int) getenv('BB_TEST_SMTP_PORT'), 'encryption' => 'ssl',
        'username' => 'sender@example.com', 'password' => 'fixture-password',
        'from_email' => 'sender@example.com', 'from_name' => 'BulkBites BBQ',
    ];
    $GLOBALS['BB_CONFIG']['app']['otp_request_ip_max_per_hour'] = 100;
    $GLOBALS['BB_CONFIG']['app']['otp_request_max_per_hour'] = 100;
    $GLOBALS['BB_CONFIG']['app']['otp_verify_max_per_hour'] = 100;
    $gmail = gmail_settings(' Store@Gmail.COM ', 'abcd efgh ijkl mnop', []);
    check($gmail['username'] === 'store@gmail.com' && $gmail['password'] === 'abcdefghijklmnop'
        && $gmail['host'] === 'smtp.gmail.com' && $gmail['encryption'] === 'tls', 'Manager setup supplies Gmail SMTP settings automatically');
    check(gmail_settings('store@gmail.com', '', $gmail)['password'] === $gmail['password'], 'Unchanged sender can retain its saved App Password');
    $rejected = false;
    try { gmail_settings('different@gmail.com', '', $gmail); } catch (InvalidArgumentException $error) { $rejected = true; }
    check($rejected, 'A new sender cannot reuse a different account credential');
    $rejected = false;
    try { gmail_settings('store@gmail.com', 'normal-password', []); } catch (InvalidArgumentException $error) { $rejected = true; }
    check($rejected, 'Normal passwords are rejected during Gmail setup');
    $configFile = tempnam(sys_get_temp_dir(), 'bbq-mail-settings-test-');
    try {
        check(write_email_settings($configFile, $gmail) && (require $configFile) === $gmail, 'Backend persists sender settings without manual code editing');
        $gmail['password'] = 'ponmlkjihgfedcba';
        check(write_email_settings($configFile, $gmail) && (require $configFile) === $gmail, 'Backend safely replaces saved sender settings');
    } finally { if (is_file($configFile)) { unlink($configFile); } }
    check(send_system_email('sender@example.com', 'Email connection test', 'This is a setup test, not a sign-in code.'), 'Manager test email uses verified SMTP delivery');
    foreach ([
        'SMTP Error: Could not authenticate. 535 5.7.8 Username and Password not accepted' => 'authentication',
        'stream_socket_enable_crypto(): certificate verify failed' => 'tls',
        'SMTP connect() failed. Connection refused' => 'connection',
        '550 5.4.5 Daily user sending limit exceeded' => 'limit',
        '550 Message rejected' => 'delivery',
    ] as $detail => $category) {
        check(email_failure_category($detail) === $category, 'Safe email error category: ' . $category);
    }
    file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/?authFail=1');
    $delivery = deliver_system_email('sender@example.com', 'Test', 'Test');
    check(!$delivery['ok'] && $delivery['error_code'] === 'authentication', 'Actual SMTP sign-in rejection produces actionable guidance');
    check(strpos(json_encode($delivery), 'fixture-password') === false, 'Diagnostics contain no credentials or SMTP transcript');
    file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/?authFail=0');
    check(!otp_request('bad-email', '127.0.0.1')['ok'], 'Invalid email rejected');
    check(otp_request(' Customer@Example.COM ', '127.0.0.1')['ok'], 'Real PHPMailer delivery succeeds over verified TLS SMTP');
    $email = 'customer@example.com';
    $code = captured_code();
    $row = otp_latest($email);
    check($row['otp_hash'] !== $code && password_verify($code, $row['otp_hash']), 'Only a hash is stored');
    check(!isset($_SESSION['debug_otp']), 'Code is never exposed through a debug session');
    check(!otp_request($email, '127.0.0.1')['ok'], 'Resend cooldown enforced');
    check(!otp_verify('other@example.com', $code)['ok'], 'Code cannot verify a different email');
    check(verify_login($email, $code)['ok'], 'Correct email code signs in and creates a customer');
    $id = $_SESSION['user_id'];
    $user = $pdo->query('SELECT * FROM users WHERE id = ' . (int) $id)->fetch();
    check($user['email'] === $email && $user['phone'] === null && $user['password_hash'] === null, 'Email customer needs no phone or permanent password');
    check(!otp_verify($email, $code)['ok'], 'Used code cannot be replayed');
    release_cooldown($email);
    check(otp_request($email, '127.0.0.1')['ok'], 'New code can be requested after cooldown');
    $code = captured_code();
    check(verify_login($email, $code)['ok'] && $_SESSION['user_id'] === $id, 'Returning email uses the same account');
    release_cooldown($email);
    otp_request($email, '127.0.0.1');
    $oldCode = captured_code();
    release_cooldown($email);
    otp_request($email, '127.0.0.1');
    $newCode = captured_code();
    $unconsumed = $pdo->query('SELECT COUNT(*) FROM email_otp_records WHERE consumed_at IS NULL')->fetchColumn();
    check((int) $unconsumed === 1, 'Resend invalidates all previous codes');
    release_cooldown($email);
    file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/?fail=1');
    check(!otp_request($email, '127.0.0.1')['ok'], 'SMTP rejection reports a delivery failure');
    check(password_verify($newCode, otp_latest($email)['otp_hash']) && otp_latest($email)['consumed_at'] === null, 'Failed resend preserves the last delivered code');
    file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/?fail=0');
    $wrong = $newCode === '000000' ? '111111' : '000000';
    for ($i = 0; $i < 3; $i++) { otp_verify($email, $wrong); }
    check(!otp_verify($email, $newCode)['ok'], 'Three wrong guesses invalidate the code');
    release_cooldown($email);
    otp_request($email, '127.0.0.1');
    $code = captured_code();
    $pdo->prepare('UPDATE email_otp_records SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE email = ?')->execute([$email]);
    check(!otp_verify($email, $code)['ok'], 'Expired code rejected');
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
    release_cooldown($email);
    check(!otp_request($email, '127.0.0.1')['ok'], 'Deactivated customer blocked');
    $pdo->prepare('INSERT INTO users (full_name, phone, email, role, password_hash) VALUES (?, ?, ?, "STAFF", ?)')
        ->execute(['Test Staff', '09123456789', 'staff@example.com', password_hash('test-password', PASSWORD_DEFAULT)]);
    check(!otp_request('staff@example.com', '127.0.0.1')['ok'], 'Email OTP cannot access staff accounts');
    $longEmail = str_repeat('a', 60) . '@' . str_repeat('b', 50) . '.' . str_repeat('c', 50) . '.example.com';
    check(otp_request($longEmail, '127.0.0.1')['ok'], 'Long email fits database and hashed rate-limit keys');
    provision_default_managers($pdo);
    $actual = $pdo->query("SELECT email FROM users WHERE role IN ('STAFF','MANAGER') AND is_active = 1 ORDER BY email")->fetchAll(PDO::FETCH_COLUMN);
    $expected = array_keys(default_manager_accounts());
    sort($expected);
    check($actual === $expected, 'Exactly the three requested managers are enabled by default');
    check((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'MANAGER' AND is_active = 1 AND password_hash IS NULL")->fetchColumn() === 3, 'Managers have no permanent password');
    check(!otp_request('uninvited@example.com', '127.0.0.1', 'STAFF')['ok'], 'Uninvited staff login is denied');
    check((int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'uninvited@example.com'")->fetchColumn() === 0, 'Staff login never self-registers accounts');
    foreach ($expected as $managerEmail) {
        check(otp_request($managerEmail, '127.0.0.1', 'STAFF')['ok'], 'Authorized manager can request an emailed code');
        $managerCode = captured_code();
        check(!otp_verify($managerEmail, $managerCode, 'CUSTOMER')['ok'], 'Manager code cannot be used on customer login');
        check(otp_verify($managerEmail, $managerCode, 'STAFF')['ok'] && complete_otp_login($managerEmail, 'STAFF')['ok']
            && $_SESSION['role'] === 'MANAGER', 'Verified manager email establishes manager access');
    }
    $managerId = $_SESSION['user_id'];
    $pdo->prepare("INSERT INTO users (full_name, email, role, password_hash) VALUES ('Added Staff', 'added@example.org', 'STAFF', NULL)")->execute();
    $staffId = (int) $pdo->lastInsertId();
    provision_default_managers($pdo);
    check((int) $pdo->query('SELECT is_active FROM users WHERE id = ' . $staffId)->fetchColumn() === 1, 'Repeating upgrade preserves subsequently added personnel');
    check(otp_request('added@example.org', '127.0.0.1', 'STAFF')['ok'], 'Manager-added staff can request a code');
    $staffCode = captured_code();
    check(otp_verify('added@example.org', $staffCode, 'STAFF')['ok'] && complete_otp_login('added@example.org', 'STAFF')['ok']
        && $_SESSION['role'] === 'STAFF', 'Staff sign-in never grants manager role');
    release_cooldown('added@example.org');
    otp_request('added@example.org', '127.0.0.1', 'STAFF');
    $staffCode = captured_code();
    check(otp_verify('added@example.org', $staffCode, 'STAFF')['ok'], 'Staff code verifies before deactivation check');
    $pdo->exec('UPDATE users SET is_active = 0 WHERE id = ' . $staffId);
    check(!complete_otp_login('added@example.org', 'STAFF')['ok'], 'Deactivation between verification and login prevents access');

    // Completed-order receipts use order-item snapshots, never catalog prices or profile contact email.
    migrate_receipt_emails($pdo);
    $pdo->exec("INSERT INTO users (full_name, email, role) VALUES ('Receipt Customer', 'receipt@example.com', 'CUSTOMER')");
    $receiptCustomerId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO customers (user_id, display_name, email) VALUES (?, ?, ?)')
        ->execute([$receiptCustomerId, 'Customer <script>bad</script>', 'unverified-profile@example.net']);
    $newOrder = static function(string $status = 'READY', ?int $customerId = null) use ($pdo, $receiptCustomerId): int {
        $ref = 'TEST-' . bin2hex(random_bytes(5));
        $pdo->prepare('INSERT INTO orders (order_number, customer_id, status, subtotal, total, payment_method) VALUES (?, ?, ?, 25, 25, "CASH")')
            ->execute([$ref, $customerId ?? $receiptCustomerId, $status]);
        $orderId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO receipts (order_id, receipt_number, total, payment_method) VALUES (?, ?, 25, "CASH")')->execute([$orderId, 'RC-' . $ref]);
        $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, 1, ?, 12.50, 2, 25)')
            ->execute([$orderId, 'BBQ <script>bad</script>']);
        return $orderId;
    };
    $orderId = $newOrder();
    $countBefore = count(captured_messages());
    $result = advance_order_status($orderId, 'COMPLETED', $managerId);
    check($result['ok'] && $result['receipt_email_status'] === 'SENT', 'Completing an order sends its receipt');
    $messages = captured_messages();
    $lastMessage = $messages[count($messages) - 1];
    check(str_contains($lastMessage, 'receipt@example.com') && !str_contains($lastMessage, 'unverified-profile@example.net'), 'Receipt goes only to the verified login email');
    check(str_contains($lastMessage, 'multipart/alternative') && str_contains($lastMessage, '12.50') && str_contains($lastMessage, '25.00')
        && str_contains($lastMessage, 'CASH') && str_contains($lastMessage, 'RC-TEST-'), 'Email includes itemized HTML and plain-text receipt with exact totals');
    $htmlPart = substr($lastMessage, strpos($lastMessage, 'Content-Type: text/html'));
    check(str_contains($htmlPart, '&lt;script&gt;') && !str_contains($htmlPart, '<script>'), 'HTML receipt escapes customer and product text');
    check(send_order_receipt($orderId)['ok'] && count(captured_messages()) === $countBefore + 1, 'Successful receipt is not resent on retry');
    check(!advance_order_status($orderId, 'COMPLETED', $managerId)['ok'] && count(captured_messages()) === $countBefore + 1, 'Duplicate completion does not send another receipt');
    $preparing = $newOrder('PREPARING');
    $countBefore = count(captured_messages());
    check(advance_order_status($preparing, 'READY', $managerId)['ok'] && count(captured_messages()) === $countBefore, 'Receipt is not emailed before completion');
    $cancelled = $newOrder('NEW');
    check(advance_order_status($cancelled, 'CANCELLED', $managerId)['ok'] && count(captured_messages()) === $countBefore, 'Cancelled orders do not email receipts');
    $failedOrder = $newOrder();
    file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/?fail=1');
    $result = advance_order_status($failedOrder, 'COMPLETED', $managerId);
    check($result['ok'] && $result['receipt_email_status'] === 'FAILED'
        && $pdo->query('SELECT status FROM orders WHERE id = ' . $failedOrder)->fetchColumn() === 'COMPLETED', 'SMTP failure preserves completed order and pending receipt');
    file_get_contents('http://127.0.0.1:' . getenv('BB_TEST_HTTP_PORT') . '/?fail=0');
    check(retry_receipt_emails(5, true) === 1, 'Failed receipt can be delivered by a later retry');
    $nestedOrder = $newOrder();
    $countBefore = count(captured_messages());
    $pdo->beginTransaction();
    $nestedResult = advance_order_status($nestedOrder, 'COMPLETED', $managerId);
    check($nestedResult['ok'] && count(captured_messages()) === $countBefore, 'Nested transaction never sends an uncommitted receipt');
    $pdo->rollBack();
    check((int) $pdo->query('SELECT COUNT(*) FROM receipt_email_deliveries WHERE order_id = ' . $nestedOrder)->fetchColumn() === 0, 'Rolling back completion also removes its receipt queue entry');
    $legacyId = (int) $pdo->query("SELECT id FROM users WHERE phone = '09999999999'")->fetchColumn();
    $legacyOrder = $newOrder('READY', $legacyId);
    check(advance_order_status($legacyOrder, 'COMPLETED', $managerId)['receipt_email_status'] === 'SKIPPED', 'Legacy customer without verified email is explicitly skipped');
    echo "{$checks} email login checks passed.\n";
} finally {
    // Only the randomly named database created by this test is removed.
    $admin->exec('DROP DATABASE `' . $name . '`');
    session_destroy();
    ob_end_flush();
}
