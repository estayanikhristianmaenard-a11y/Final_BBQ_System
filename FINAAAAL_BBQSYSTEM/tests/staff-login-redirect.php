<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/functions.php';
$_SERVER['SCRIPT_NAME'] = '/Final_BBQ_System/FINAAAAL_BBQSYSTEM/auth/staff_login.php';
$_SESSION = [];
$cases = [
    ['MANAGER', 'manager/email.php', 'manager/email.php'],
    ['STAFF', 'manager/email.php', 'staff/dashboard.php'],
    ['CUSTOMER', 'manager/email.php', 'customer/dashboard.php'],
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
