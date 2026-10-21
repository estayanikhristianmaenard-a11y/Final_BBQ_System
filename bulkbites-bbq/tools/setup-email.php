<?php
/** CLI only: receive credentials through stdin, never command-line arguments. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/email.php';
require dirname(__DIR__) . '/includes/email-setup.php';
$path = dirname(__DIR__) . '/config/mail.php';
try {
    // Check before reading stdin so an existing installation never prompts to replace it.
    $previous = is_file($path) ? require $path : [];
    if (is_array($previous) && email_configured($previous)) {
        echo "Email is already configured. Sign in as a manager and use Email to change the sender.\n";
        exit(0);
    }
    if (($argv[1] ?? '') === '--status') {
        echo "Email sender is not configured yet.\n";
        exit(2);
    }
    if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
        fwrite(STDERR, "Run SETUP-EMAIL.bat or tools/setup-email.ps1 for the hidden password prompt.\n");
        exit(1);
    }
    $payload = stream_get_contents(STDIN, 8193);
    if (strlen($payload) > 8192) { throw new InvalidArgumentException('Invalid setup input.'); }
    $input = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
    unset($payload);
    if (!is_array($input) || !is_string($input['sender'] ?? null) || !is_string($input['password'] ?? null)) {
        throw new InvalidArgumentException('Use the local setup launcher to enter the sender and App Password.');
    }
    $settings = gmail_settings($input['sender'], $input['password'], []);
    unset($input);
    $result = bootstrap_email_sender($settings, $path);
    unset($settings);
    if (!$result['ok']) {
        $message = match ($result['error_code']) {
            'already_configured' => 'Another setup completed. Sign in as a manager to manage the sender.',
            'setup_busy' => 'Another email setup is running. Wait for it to finish.',
            'write_failed' => 'Email setup could not save its configuration. Check the server config directory permissions. A test message may already have been sent.',
            'invalid_saved_configuration' => 'The existing email configuration is invalid. Ask the server administrator to repair it.',
            default => email_failure_message($result['error_code']),
        };
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
    echo "Sender verified and saved. Check the sender inbox or Spam for the test message.\n";
    echo "Open Staff/Manager login and request a code using an authorized manager email.\n";
    echo "Then verify Customer login and complete a test order to check the receipt email.\n";
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} catch (Throwable $error) {
    // Never print input, credentials, SMTP responses, or stack traces.
    fwrite(STDERR, "Email setup failed. Check the input and local server configuration, then try again.\n");
    exit(1);
}
