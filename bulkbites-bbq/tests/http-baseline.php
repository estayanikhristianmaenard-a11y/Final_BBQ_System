<?php
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
require dirname(__DIR__) . '/tools/database-transfer.php';
$config = transfer_config();
$pdo = transfer_connection($config);
$pdo->exec('USE ' . transfer_identifier($config['name']));
$base = getenv('BB_TEST_HTTP_BASE') ?: 'http://localhost/Final_BBQ_System/bulkbites-bbq/';
$failures = 0;
$count = 0;
function http_check(string $path, int $expected, ?string $cookie = null, ?array $post = null, bool $ajax = false): void {
    global $base, $failures, $count;
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
    if ($ajax) { curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']); }
    if ($cookie !== null) { curl_setopt($curl, CURLOPT_COOKIE, 'bulkbites_session=' . $cookie); }
    if ($post !== null) {
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $passed = $status === $expected && is_string($body) && !preg_match('/Fatal error|Warning:|Uncaught /', $body);
    $count++;
    if (!$passed) { $failures++; }
    echo ($passed ? 'PASS' : 'FAIL') . ': HTTP ' . $path . ' => ' . $status . ' (expected ' . $expected . ")\n";
}
foreach (['' => 200, 'login.php' => 200, 'auth/staff_login.php' => 200,
    'customer/menu.php' => 200, 'customer/cart.php' => 200, 'customer/checkout.php' => 302, 'manager/email.php' => 302, 'manager/demo.php' => 302, 'api/orders.php' => 401,
    'config/database.php' => 403, 'database/current.sql' => 403,
    'tools/setup-email.php' => 403, 'api/paymongo-webhook.php' => 405,
    'config/paymongo.php' => 403, 'manager/store.php'=>302, 'api/order-status.php'=>401, 'customer/demo-payment.php'=>302, 'staff/ordering.php'=>302] as $path => $status) {
    http_check($path, $status);
}
session_name('bulkbites_session');
// Public cart mutations still require a CSRF token; these requests make no changes.
http_check('customer/menu.php', 403, null, ['product_id' => 1, 'qty' => 1]);
http_check('customer/cart.php', 403, null, ['reset' => 1]);
http_check('customer/menu.php', 403, null, ['product_id'=>1,'qty'=>1], true);
http_check('customer/cart.php', 403, null, ['reset'=>1], true);
http_check('api/order-status.php',405,null,['ref'=>'missing']);
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
foreach (['CUSTOMER', 'MANAGER'] as $role) {
    $query = $pdo->prepare('SELECT id FROM users WHERE role = ? AND is_active = 1 LIMIT 1');
    $query->execute([$role]);
    $id = $query->fetchColumn();
    if (!$id) { throw new RuntimeException('Missing role fixture.'); }
    session_id('');
    session_start();
    $_SESSION = ['user_id' => (int) $id, 'role' => $role, 'last_activity' => time(), 'login_at' => time()];
    $cookie = session_id();
    session_write_close();
    try {
        if ($role === 'CUSTOMER') {
            foreach (['customer/menu.php', 'customer/cart.php', 'customer/orders.php'] as $path) { http_check($path, 200, $cookie); }
            http_check('customer/dashboard.php', 302, $cookie);
            http_check('customer/notifications.php', 302, $cookie);
            http_check('manager/dashboard.php', 403, $cookie);
            http_check('manager/demo.php', 403, $cookie);
            http_check('manager/store.php',403,$cookie);
            http_check('api/order-status.php?ref=missing',404,$cookie);
            http_check('manager/store.php',403,$cookie,['ordering_status'=>'CLOSED']);
            http_check('customer/demo-payment.php?ref=missing',404,$cookie);
            http_check('staff/ordering.php',403,$cookie,['ordering_status'=>'CLOSED']);
            http_check('staff/kitchen.php', 403, $cookie);
            http_check('api/orders.php', 403, $cookie);
        } else {
            http_check('manager/store.php',200,$cookie);
            http_check('manager/store.php',403,$cookie,['ordering_status'=>'CLOSED']);
            http_check('api/order-status.php?ref=missing',403,$cookie);
            http_check('customer/demo-payment.php?ref=missing',403,$cookie);
            http_check('staff/ordering.php',405,$cookie);
            http_check('staff/ordering.php',403,$cookie,['ordering_status'=>'CLOSED']);
            foreach (['manager/dashboard.php', 'manager/reports.php', 'manager/products.php', 'manager/categories.php', 'manager/users.php', 'manager/customers.php', 'manager/email.php', 'manager/demo.php', 'staff/orders.php', 'staff/inventory.php'] as $path) { http_check($path, 200, $cookie); }
            http_check('staff/kitchen.php',302,$cookie);
            http_check('manager/inventory.php', 302, $cookie);
            http_check('manager/products.php', 403, $cookie, ['action'=>'restock','product_id'=>1,'quantity'=>1]);
            http_check('manager/inventory.php', 403, $cookie, ['action'=>'restock','product_id'=>1,'quantity'=>1]);
            http_check('staff/notifications.php', 403, $cookie, ['action'=>'mark_all']);
            http_check('staff/dashboard.php', 302, $cookie);
            http_check('staff/notifications.php', 302, $cookie);
            http_check('customer/menu.php', 403, $cookie);
            http_check('customer/cart.php', 403, $cookie);
            http_check('customer/menu.php', 403, $cookie, ['product_id'=>1,'qty'=>1], true);
            http_check('customer/cart.php', 403, $cookie, ['reset'=>1], true);
            http_check('customer/checkout.php', 403, $cookie);
        }
    } finally {
        session_id($cookie);
        session_start();
        $_SESSION = [];
        session_destroy();
    }
}
echo $count . ' HTTP checks; ' . $failures . " failures. Temporary authenticated sessions destroyed.\n";
ob_end_flush();
exit($failures ? 1 : 0);
