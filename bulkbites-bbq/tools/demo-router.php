<?php
/** Public demo front door for PHP's local dev server. Never expose XAMPP's whole document root. */
if (PHP_SAPI !== 'cli-server') { http_response_code(403); exit; }
$root = realpath(dirname(__DIR__));
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!is_string($requestPath)) { http_response_code(400); exit; }
$path = rawurldecode($requestPath);
if (str_contains($path, '\\') || str_contains($path, "\0") || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) { http_response_code(403); exit; }
if ($path === '/') { $path = '/index.php'; }
// Private routes stay forbidden whether or not their local config file exists.
if (preg_match('~^/(config|includes|tools|tests|vendor|database)(?:/|$)~i', $path) || $path === '/database/schema.sql') { http_response_code(403); exit; }
$file = realpath($root . $path);
$normalizedRoot = str_replace('\\', '/', $root) . '/';
if (!$file || !str_starts_with(str_replace('\\', '/', $file), $normalizedRoot) || !is_file($file)) { http_response_code(404); exit; }
if (preg_match('~^/(assets|uploads)/[A-Za-z0-9_./-]+\.(css|js|svg|png|jpg|jpeg|webp|gif)$~iD', $path)) { return false; }
$allowed = in_array($path, ['/index.php','/login.php','/logout.php'], true)
    || preg_match('~^/(auth|customer|staff|manager|api)/[a-z0-9_-]+\.php$~D', $path);
if (!$allowed) { http_response_code(403); exit; }
$_SERVER['SCRIPT_NAME'] = $path;
$_SERVER['PHP_SELF'] = $path;
$_SERVER['BULKBITES_DEMO_REQUEST'] = true;
// Trust the forwarded scheme only for the configured public host on this loopback demo server.
$demoConfig = is_file($root . '/config/demo.php') ? require $root . '/config/demo.php' : [];
$publicHost = parse_url((string) ($demoConfig['public_base_url'] ?? ''), PHP_URL_HOST);
if (is_string($publicHost) && $publicHost !== '' && ($_SERVER['HTTP_HOST'] ?? '') === $publicHost
    && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {
    $_SERVER['HTTPS'] = 'on';
}
header('X-Content-Type-Options: nosniff');
header('X-BulkBites-Demo: 1');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');
require $file;
