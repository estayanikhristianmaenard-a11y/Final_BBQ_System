<?php
/** QR entry point: browse immediately; operational accounts keep their dashboard. */
require_once __DIR__ . '/includes/bootstrap.php';
$visitor = current_user();
if ($visitor && $visitor['role'] !== 'CUSTOMER') {
    redirect(user_home($visitor['role']));
}
require __DIR__ . '/customer/menu.php';
