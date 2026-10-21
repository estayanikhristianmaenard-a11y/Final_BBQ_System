<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
$GLOBALS['BB_CONFIG']['app']['debug'] = false;
try {
    if (($argv[1] ?? '') === '--save-url') {
        $input = json_decode(stream_get_contents(STDIN, 8193), true, 8, JSON_THROW_ON_ERROR);
        $base = demo_base_url(is_string($input['public_base_url'] ?? null) ? $input['public_base_url'] : '');
        if (!write_demo_settings(dirname(__DIR__) . '/config/demo.php', ['public_base_url'=>$base])) { throw new RuntimeException(); }
        echo "Saved public ordering URL: " . $base . "/customer/menu.php\n";
        exit;
    }
    $checks = ['email_configured'=>email_configured(), 'public_ordering_url'=>demo_ordering_url(), 'payment_simulator_enabled'=>demo_payment_enabled(),
        'paymongo_test_configured'=>paymongo_ready(),
        'payment_url_matches'=>rtrim((string) (paymongo_settings()['public_base_url'] ?? ''), '/') === rtrim((string) (demo_settings()['public_base_url'] ?? ''), '/')
            && demo_ordering_url() !== ''];
    if (in_array('--json', $argv, true)) { echo json_encode($checks, JSON_THROW_ON_ERROR) . "\n"; }
    else {
        echo 'Email sender: ' . ($checks['email_configured'] ? 'configured (real inbox verification required)' : 'not configured') . "\n";
        echo 'Ordering URL: ' . ($checks['public_ordering_url'] ?: 'not configured') . "\n";
        echo 'Payment simulator: ' . ($checks['payment_simulator_enabled'] ? 'enabled (demo only; no real money)' : 'disabled') . "\n";
    }
} catch (InvalidArgumentException $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
catch (Throwable $error) { fwrite(STDERR, "Demo setup failed. Check the local config directory and input.\n"); exit(1); }
