<?php
/** Verify the dedicated ngrok demo front door locally without keys or public exposure. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$cases = [
    ['/',200], ['/assets/css/style.css',200], ['/assets/images/products/bbq-render.webp',200],
    ['/database/current.sql',403], ['/database/schema.sql',403], ['/config/database.php',403],
    ['/config/paymongo.example.php',403], ['/includes/paymongo.php',403],
    ['/config/demo.php',403], ['/includes/paymongo-setup.php',403],
    ['/tools/setup-paymongo.php',403], ['/assets/vendor/qrcode-generator/qrcode.js',200],
    ['/tools/migrate-payments.php',403], ['/tests/paymongo-cases.php',403],
    ['/vendor/phpmailer/src/SMTP.php',403], ['/%2e%2e/README.md',403],
    ['/api/paymongo-webhook.php',405], ['/api/paymongo-webhook.php',[400,503],'{}'],
];
$failed = 0;
foreach ($cases as $case) {
    $curl = curl_init('http://127.0.0.1:8080' . $case[0]);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_TIMEOUT=>5]);
    if (isset($case[2])) { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, $case[2]); }
    $body = curl_exec($curl); $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    $ok = in_array($status, (array) $case[1], true) && is_string($body) && !preg_match('/Fatal error|Warning:|Uncaught /', $body);
    if (!$ok) { $failed++; }
    echo ($ok ? 'PASS' : 'FAIL') . ': demo ' . $case[0] . ' => ' . $status . "\n";
}
echo count($cases) . ' demo-router checks; ' . $failed . " failures. Unsigned webhook must be rejected.\n";
exit($failed ? 1 : 0);

