<?php
// Intentionally no session, login, or CSRF: provider authentication is the raw-body signature.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('{"ok":false}'); }
$root = dirname(__DIR__);
$GLOBALS['BB_CONFIG'] = require $root . '/config/database.php';
$GLOBALS['BB_CONFIG']['paymongo'] = is_file($root . '/config/paymongo.php') ? require $root . '/config/paymongo.php' : [];
require $root . '/includes/functions.php';
require $root . '/includes/payments.php';
require $root . '/includes/notifications.php';
require $root . '/includes/paymongo.php';
date_default_timezone_set(config('app.timezone', 'Asia/Manila'));
ini_set('display_errors', '0');
try {
    $raw = file_get_contents('php://input', false, null, 0, 262145);
    $result = paymongo_handle_webhook($raw ?: '', $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '');
    http_response_code($result['status']); echo json_encode(['ok' => $result['ok']]);
} catch (Throwable $error) { http_response_code(503); echo '{"ok":false}'; }
