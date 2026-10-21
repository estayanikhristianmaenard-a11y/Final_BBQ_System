<?php
/** Internal concurrency worker; only randomized baseline databases are permitted. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!preg_match('/^bulkbites_baseline_[a-f0-9]{12}$/D', $argv[1] ?? '')) { exit(1); }
require dirname(__DIR__) . '/includes/bootstrap.php';
$GLOBALS['BB_CONFIG']['db']['name'] = $argv[1];
$GLOBALS['BB_CONFIG']['mail'] = [];
$GLOBALS['BB_CONFIG']['app']['online_payments_enabled'] = true;
try {
    switch ($argv[2] ?? '') {
        case 'demo': $result=simulate_order_payment((int)$argv[3],(int)$argv[4],'success');break;
        case 'cancel': $result=cancel_customer_order((int)$argv[3],(int)$argv[4]);break;
        case 'accept': $result=advance_order_status((int)$argv[3],'ACCEPTED',(int)$argv[4]);break;
        case 'checkout':
            $_SESSION['cart'] = [(int) $argv[4] => 1];
            $result = place_order((int) $argv[3], 'CASH', 'Concurrent checkout');
            break;
        case 'expire': $result = ['ok' => true, 'expired' => expire_order_reservations()]; break;
        case 'online': $result = record_verified_online_payment((int) $argv[3], $argv[4], (int) $argv[5], 'PHP', true); break;
        default: exit(1);
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
} finally { session_destroy(); }
