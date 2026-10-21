<?php
/** First-time setup helper. Only the local CLI entry point may invoke this. */
function bootstrap_email_sender(array $settings, string $path): array
{
    if (PHP_SAPI !== 'cli') {
        return ['ok' => false, 'error_code' => 'cli_only'];
    }
    $lock = @fopen($path . '.setup.lock', 'c');
    if ($lock === false) {
        return ['ok' => false, 'error_code' => 'write_failed'];
    }
    @chmod($path . '.setup.lock', 0600);
    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'error_code' => 'setup_busy'];
        }
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($path, true); }
        $previous = is_file($path) ? require $path : [];
        if (!is_array($previous)) {
            return ['ok' => false, 'error_code' => 'invalid_saved_configuration'];
        }
        if (email_configured($previous)) {
            return ['ok' => false, 'error_code' => 'already_configured'];
        }
        $delivery = deliver_system_email($settings['from_email'] ?? '',
            'BulkBites BBQ first-time email setup',
            "Your BulkBites BBQ sender connection test succeeded.\n\n"
            . "After setup saves successfully, authorized managers and customers can request email sign-in codes.\n"
            . 'This message is a setup test, not a login code.', $settings);
        if (!$delivery['ok']) { return $delivery; }
        if (!write_email_settings($path, $settings)) {
            return ['ok' => false, 'error_code' => 'write_failed'];
        }
        return ['ok' => true, 'error_code' => null];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        // Keep the lock inode so concurrent processes cannot bypass serialization.
    }
}
