<?php
/**
 * bootstrap.php — required FIRST by every page and endpoint.
 *
 * Starts the hardened session, loads configuration, connects to MySQL, loads
 * shared helpers, and enforces idle/absolute session timeouts.
 */

// ---------------------------------------------------------------------------
// 1. Hardened session (before any output)
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,   // set true when served over HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('bulkbites_session');
    session_start();
}

// ---------------------------------------------------------------------------
// 2. Configuration
// ---------------------------------------------------------------------------
$configFile = dirname(__DIR__) . '/config/database.php';
if (!is_file($configFile)) {
    die('Missing config/database.php — copy config.example.php to config/database.php and edit it.');
}
$GLOBALS['BB_CONFIG'] = require $configFile;

// ---------------------------------------------------------------------------
// 3. Shared helpers (defines config(), db(), e(), ...)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/role_check.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/notifications.php'; // notify_* + advance_order_status (used by checkout)
require_once __DIR__ . '/checkout.php';
require_once __DIR__ . '/staff_feed.php'; // queue/counts/inventory reads (Phase 6)
require_once __DIR__ . '/manager_feed.php'; // statistics/report reads (Phase 7)

// ---------------------------------------------------------------------------
// 4. Error handling and timezone
// ---------------------------------------------------------------------------
$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

// ---------------------------------------------------------------------------
// 5. Session timeout (idle + absolute), enforced server-side
// ---------------------------------------------------------------------------
if (!empty($_SESSION['user_id'])) {
    $role    = $_SESSION['role'] ?? 'CUSTOMER';
    $idleMax = (int) config('app.session_idle.' . $role, 3600);
    $absMax  = (int) config('app.session_absolute.' . $role, 86400);
    $now     = time();
    $last    = (int) ($_SESSION['last_activity'] ?? $now);
    $born    = (int) ($_SESSION['login_at'] ?? $now);

    if (($now - $last) > $idleMax || ($now - $born) > $absMax) {
        destroy_session();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        flash('warning', 'Your session expired. Please log in again.');
    } else {
        $_SESSION['last_activity'] = $now;
    }
}
