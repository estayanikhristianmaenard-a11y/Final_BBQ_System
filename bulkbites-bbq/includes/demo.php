<?php
/** Public demo configuration contains URLs only; credentials live in separate ignored files. */
function demo_base_url(string $value): string
{
    $value = rtrim(trim($value), '/');
    if (str_ends_with($value, '/customer/menu.php')) { $value = substr($value, 0, -strlen('/customer/menu.php')); }
    $parts = parse_url($value);
    if (strlen($value) > 400 || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || !filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        || !str_contains($parts['host'], '.') || filter_var($parts['host'], FILTER_VALIDATE_IP)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
        || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\\\\\s\x00-\x1f\x7f]/', rawurldecode($value))
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', rawurldecode($parts['path'] ?? ''))) {
        throw new InvalidArgumentException('Enter the public HTTPS base URL, without a query, fragment, or login details.');
    }
    return $value;
}
function demo_settings(): array { return config('demo', []); }
function demo_ordering_url(): string
{
    try { return demo_base_url((string) (demo_settings()['public_base_url'] ?? '')) . '/customer/menu.php'; }
    catch (InvalidArgumentException $error) { return ''; }
}
function write_demo_settings(string $path, array $settings): bool
{
    $temp = tempnam(dirname($path), 'demo-');
    if (!$temp) { return false; }
    try {
        @chmod($temp, 0600);
        if (file_put_contents($temp, "<?php\n// Local demo URL, managed by demo setup.\nreturn " . var_export($settings, true) . ";\n", LOCK_EX) === false
            || !rename($temp, $path)) { return false; }
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($path, true); }
        return true;
    } finally { if (is_file($temp)) { @unlink($temp); } }
}
