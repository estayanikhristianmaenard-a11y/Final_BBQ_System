<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
if (!paymongo_ready()) { fwrite(STDERR, "Configure PayMongo test keys and the public base URL first.\n"); exit(1); }
$session = $argv[1] ?? '';
if (!preg_match('/^cs_[A-Za-z0-9]+$/D', $session)) { fwrite(STDERR, "Usage: php tools/reconcile-paymongo.php cs_SESSION_ID_FROM_PROVIDER\n"); exit(1); }
$result = paymongo_reconcile_session($session);
echo $result['message'] . "\n";
exit($result['ok'] ? 0 : 1);
