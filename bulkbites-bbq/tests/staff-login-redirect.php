<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/functions.php';
$_SERVER['SCRIPT_NAME'] = '/Final_BBQ_System/bulkbites-bbq/auth/staff_login.php';
$_SESSION = [];
$cases = [
    ['MANAGER', 'manager/email.php', 'manager/email.php'],
    ['STAFF', 'manager/email.php', 'staff/kitchen.php'],
    ['CUSTOMER', 'manager/email.php', 'customer/menu.php'],
    ['MANAGER', 'https://example.com', 'manager/dashboard.php'],
    ['MANAGER', null, 'manager/dashboard.php'],
];
foreach ($cases as [$role, $next, $expected]) {
    $_SESSION['staff_login_next'] = $next;
    if (staff_login_destination($role) !== url($expected) || isset($_SESSION['staff_login_next'])) {
        throw new RuntimeException('Unsafe or incorrect login destination for ' . $role);
    }
}
echo "5 sign-in redirect checks passed: manager return, role restrictions, no external redirect, ordinary login, single-use destination.\n";
$_SERVER['SCRIPT_NAME'] = '/staff/kitchen.php';
if (url('assets/css/style.css') !== '/assets/css/style.css') {
    throw new RuntimeException('Root deployment URLs must use forward slashes.');
}
echo "Root deployment asset URL check passed.\n";
foreach ([
    ['CUSTOMER', 'customer/checkout.php', 'customer/checkout.php'],
    ['CUSTOMER', 'https://example.com', 'customer/menu.php'],
    ['CUSTOMER', 'manager/dashboard.php', 'customer/menu.php'],
    ['MANAGER', 'customer/checkout.php', 'manager/dashboard.php'],
    ['STAFF', 'customer/checkout.php', 'staff/kitchen.php'],
    ['CUSTOMER', null, 'customer/menu.php'],
] as [$role, $next, $expected]) {
    $_SESSION['customer_login_next'] = $next;
    if (customer_login_destination($role) !== url($expected) || isset($_SESSION['customer_login_next'])) {
        throw new RuntimeException('Unsafe customer return destination for ' . $role);
    }
}
echo "6 customer checkout redirect checks passed.\n";
