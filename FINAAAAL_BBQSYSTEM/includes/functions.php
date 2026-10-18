<?php
/**
 * Core helper functions — loaded by bootstrap.php on every request.
 */

/** Read a dot-notation config value, e.g. config('app.otp_expiry_minutes', 5). */
function config(string $key, $default = null)
{
    $value = $GLOBALS['BB_CONFIG'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/** Singleton PDO connection (prepared statements, exceptions, no emulation). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Log the real cause server-side; never expose it over HTTP.
        error_log('[BulkBites] DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        if (config('app.debug')) {
            die('Database connection failed: ' . $e->getMessage());
        }
        die('Database connection failed. Check config/database.php.');
    }
    return $pdo;
}

/** Escape for safe HTML output (XSS protection). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format a peso amount: peso(30) => ₱30.00 */
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/**
 * Project base URL — works when htdocs is the server root AND when the project
 * lives in a subfolder (e.g. /FINAAAAL_BBQSYSTEM/). Pages only ever live at the
 * project root or exactly one level deep (auth/, customer/, staff/, manager/, api/).
 */
function base_url(): string
{
    $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $leaf = basename($dir);
    if (in_array($leaf, ['auth', 'customer', 'staff', 'manager', 'api'], true)) {
        $dir = dirname($dir); // one level deep → step back to the project root
    }
    return rtrim($dir, '/');
}

/** Build a URL inside the project: url('login.php') => /subfolder/login.php */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** Redirect and stop execution. */
function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

/** Queue a flash message for the next render (stored in the session). */
function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear all flash messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

/** Client IP for rate limiting (XAMPP always serves locally). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Is this an AJAX request? */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || (($_SERVER['HTTP_ACCEPT'] ?? '')) === 'application/json';
}

/** Mask a phone for display: 09171234567 => 09*****4567 */
function mask_phone(string $phone): string
{
    if (strlen($phone) < 7) {
        return $phone;
    }
    return substr($phone, 0, 2) . '*****' . substr($phone, -4);
}

/**
 * Fixed-window rate limiter. Returns true if the attempt is ALLOWED (and records
 * it), false if the caller has exceeded $max within the current window.
 */
function rate_limit_hit(string $action, string $identifier, int $max, int $windowSeconds): bool
{
    $windowStart = date('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);
    $pdo = db();

    $stmt = $pdo->prepare(
        'INSERT INTO rate_limits (action, identifier, window_start, attempts)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE attempts = attempts + 1'
    );
    $stmt->execute([$action, $identifier, $windowStart]);

    $sel = $pdo->prepare(
        'SELECT attempts FROM rate_limits WHERE action = ? AND identifier = ? AND window_start = ?'
    );
    $sel->execute([$action, $identifier, $windowStart]);

    return ((int) $sel->fetchColumn()) <= $max;
}

/**
 * The logged-in user, re-read from the database on every request so a
 * deactivated account is locked out immediately. Returns null when there is
 * no valid session user.
 */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, phone, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row || !(int) $row['is_active']) {
        force_logout('This account is no longer active. Please log in again.');
        return null;
    }

    $user = $row;
    return $user;
}

/** Fully destroy the current session (used by logout, expiry, deactivation). */
function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Destroy the session but start a fresh one so a message can still flash. */
function force_logout(string $message): void
{
    destroy_session();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    flash('warning', $message);
}

/** Home URL for a role (used for post-login redirects and nav links). */
function user_home(string $role): string
{
    switch ($role) {
        case 'MANAGER':
            return url('manager/dashboard.php');
        case 'STAFF':
            return url('staff/dashboard.php');
        default:
            return url('customer/dashboard.php');
    }
}

/** Consume the fixed email-setup destination only after a manager signs in. */
function staff_login_destination(string $role): string
{
    $next = $_SESSION['staff_login_next'] ?? null;
    unset($_SESSION['staff_login_next']);
    if ($role === 'MANAGER' && $next === 'manager/email.php') {
        return url('manager/email.php');
    }
    return user_home($role);
}
