<?php
/**
 * Pluggable SMS delivery for OTP messages.
 *
 * Local XAMPP development uses DebugSmsSender (writes the code to
 * logs/otp_debug.log — no SMS service, no cost). To integrate a real provider
 * later: implement ProviderSmsSender with a cURL call to the provider API,
 * put the API credentials in config/database.php, then set
 * otp_delivery_mode => 'sms'. The login flow itself never changes.
 */

interface SmsSender
{
    /** Send a message to a phone number. Returns true on success. */
    public function send(string $phone, string $message): bool;
}

/** Local development sender — logs the OTP instead of sending an SMS. */
class DebugSmsSender implements SmsSender
{
    public function send(string $phone, string $message): bool
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . "] TO {$phone} => {$message}\n";
        return file_put_contents($dir . '/otp_debug.log', $line, FILE_APPEND) !== false;
    }
}

/** Real SMS provider adapter — NOT configured yet (returns false until implemented). */
class ProviderSmsSender implements SmsSender
{
    public function send(string $phone, string $message): bool
    {
        // TODO (future phase): cURL POST to the SMS provider API.
        // $apiKey = config('app.sms_api_key');  // read from config — never hardcode
        // ... send request, return true on HTTP 2xx ...
        return false;
    }
}

function sms_sender(): SmsSender
{
    return config('app.otp_delivery_mode', 'debug') === 'sms'
        ? new ProviderSmsSender()
        : new DebugSmsSender();
}

/** True when running in local debug delivery mode (OTP shown/logged, not texted). */
function otp_debug_mode(): bool
{
    return config('app.otp_delivery_mode', 'debug') !== 'sms';
}
