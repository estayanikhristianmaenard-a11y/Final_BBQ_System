<?php
/** Real email delivery. SMTP credentials live in the ignored config/mail.php file. */
require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/email-template.php';

function email_settings(): array
{
    return config('mail', []);
}

function email_configured(?array $settings = null): bool
{
    $c = $settings ?? email_settings();
    return !empty($c['host']) && !empty($c['username']) && !empty($c['password'])
        && filter_var($c['from_email'] ?? '', FILTER_VALIDATE_EMAIL)
        && in_array($c['encryption'] ?? '', ['tls', 'ssl'], true);
}

function send_login_email(string $email, string $code, int $expiryMinutes): bool
{
    $content = login_email_content($code, $expiryMinutes);
    return deliver_system_email($email, $content['subject'], $content['text'], null, $content['html'])['ok'];
}

/** Used for automatic customer codes and the manager's explicit delivery test. */
function send_system_email(string $email, string $subject, string $body, ?array $settings = null): bool
{
    return deliver_system_email($email, $subject, $body, $settings)['ok'];
}

/** Return only safe error categories; never expose raw SMTP responses. */
function deliver_system_email(string $email, string $subject, string $body, ?array $settings = null, ?string $html = null): array
{
    $c = $settings ?? email_settings();
    if (!email_configured($c)) {
        return ['ok' => false, 'error_code' => 'configuration'];
    }
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $c['host'];
        $mail->Port = (int) ($c['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = $c['username'];
        $mail->Password = $c['password'];
        $mail->SMTPSecure = $c['encryption'];
        $mail->Timeout = 15;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($c['from_email'], $c['from_name'] ?? 'BulkBites BBQ');
        $mail->addAddress($email);
        $mail->Subject = $subject;
        if ($html !== null) {
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $body;
        } else {
            $mail->Body = $body;
        }
        $mail->send();
        return ['ok' => true, 'error_code' => null];
    } catch (\Throwable $error) {
        $smtpError = isset($mail) ? $mail->getSMTPInstance()->getError() : [];
        $detail = $error->getMessage() . ' ' . ($mail->ErrorInfo ?? '') . ' '
            . implode(' ', array_map('strval', $smtpError));
        $category = email_failure_category($detail);
        // Log only the category. Raw responses can contain recipient addresses.
        error_log('[BulkBites] SMTP delivery failed: ' . $category);
        return ['ok' => false, 'error_code' => $category];
    }
}

function email_failure_category(string $detail): string
{
    if (preg_match('/certificate|crypto|starttls|tls negotiation/i', $detail)) {
        return 'tls';
    }
    if (preg_match('/daily.*limit|quota|sending limit|too many login|4\.7\.28|5\.4\.5/i', $detail)) {
        return 'limit';
    }
    if (preg_match('/authenticat|username and password|5\.7\.(8|9|14)|535|534/i', $detail)) {
        return 'authentication';
    }
    if (preg_match('/connect|timed? out|network|resolve|php_network_getaddresses|refused|unreachable/i', $detail)) {
        return 'connection';
    }
    return 'delivery';
}

/** Manager-only guidance. Customer login continues to show a generic error. */
function email_failure_message(string $category): string
{
    return [
        'configuration' => 'Enter the sender Google account email and its App Password before testing.',
        'authentication' => 'Gmail rejected the sender sign-in. Create a new 16-letter Google App Password for the same sender email and try again. Your normal Google password will not work. Check that 2-Step Verification is enabled.',
        'connection' => 'This server could not reach Gmail. Check its internet connection and whether a firewall, antivirus, or network blocks smtp.gmail.com on port 587.',
        'tls' => 'The encrypted connection to Gmail failed. Check the PC date and time and the PHP trusted certificate bundle. Do not turn off certificate verification.',
        'limit' => 'Gmail reported a sending limit or temporary restriction. Wait before retrying and check the sender account for a notice from Google.',
        'delivery' => 'The email provider rejected the test message. Check the sender account for a Google security notice, then try a new App Password.',
    ][$category] ?? 'Email delivery failed. Check the sender account and try again.';
}

/** Build Gmail settings from the manager's form; passwords never return to HTML. */
function gmail_settings(string $email, string $password, array $previous): array
{
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid Google account email address.');
    }
    $password = preg_replace('/\s+/', '', $password);
    if ($password === '') {
        // Retain only a Gmail credential belonging to the same sender.
        if (($previous['host'] ?? '') !== 'smtp.gmail.com'
            || strtolower($previous['username'] ?? '') !== $email) {
            throw new InvalidArgumentException('Enter the Google App Password for this sender.');
        }
        $password = $previous['password'] ?? '';
    }
    if (!preg_match('/^[a-zA-Z]{16}$/D', $password)) {
        throw new InvalidArgumentException('Enter the 16-letter Google App Password, not your normal Google password.');
    }
    return [
        'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
        'username' => $email, 'password' => $password,
        'from_email' => $email, 'from_name' => 'BulkBites BBQ',
    ];
}

/** Atomically save credentials outside Git. The path is fixed by the server. */
function save_email_settings(array $settings): bool
{
    return write_email_settings(dirname(__DIR__) . '/config/mail.php', $settings);
}

function write_email_settings(string $path, array $settings): bool
{
    $temp = @tempnam(dirname($path), 'mail-');
    if ($temp === false) {
        return false;
    }
    try {
        @chmod($temp, 0600);
        $content = "<?php\n// Managed by Manager > Email. Never commit this file.\nreturn "
            . var_export($settings, true) . ";\n";
        if (@file_put_contents($temp, $content, LOCK_EX) === false || !@rename($temp, $path)) {
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        return true;
    } finally {
        if (is_file($temp)) {
            @unlink($temp);
        }
    }
}
