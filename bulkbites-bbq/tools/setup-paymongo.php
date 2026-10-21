<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/paymongo-setup.php';
try {
    $base = demo_base_url((string) (demo_settings()['public_base_url'] ?? ''));
    if (($argv[1] ?? '') === '--status') {
        $ready = paymongo_ready() && rtrim(paymongo_settings()['public_base_url'],'/') === $base;
        echo $ready ? "PayMongo TEST is configured for the current public URL.\n" : "PayMongo TEST needs a key or a matching public webhook.\n";
        exit($ready ? 0 : 2);
    }
    if (function_exists('stream_isatty') && stream_isatty(STDIN)) { throw new InvalidArgumentException('Run SETUP-PAYMONGO.bat for the hidden test-key prompt.'); }
    $payload = stream_get_contents(STDIN,8193);
    if (strlen($payload)>8192) { throw new InvalidArgumentException('Invalid setup input.'); }
    $input = json_decode($payload,true,8,JSON_THROW_ON_ERROR); unset($payload);
    $key = is_string($input['secret_key'] ?? null) ? trim($input['secret_key']) : '';
    if ($key === '') { $key = (string) (paymongo_settings()['secret_key'] ?? ''); }
    unset($input);
    $result = configure_paymongo_test($key,$base,dirname(__DIR__) . '/config/paymongo.php'); unset($key);
    echo "PayMongo TEST configured. Webhook: " . $result['webhook_url'] . "\n";
    echo "Complete a TEST checkout and verify the signed payment before presenting. No payment was created by setup.\n";
} catch (InvalidArgumentException | RuntimeException $error) { fwrite(STDERR,$error->getMessage() . "\n"); exit(1); }
catch (Throwable $error) { fwrite(STDERR,"PayMongo setup failed. Credentials and provider responses have not been logged.\n"); exit(1); }
