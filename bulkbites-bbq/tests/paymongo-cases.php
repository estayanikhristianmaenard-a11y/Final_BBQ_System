<?php
/** Included by the disposable baseline runner; never executes against an arbitrary database. */
if (PHP_SAPI !== 'cli' || !isset($pdo, $customer, $product, $name) || !preg_match('/^bulkbites_baseline_[a-f0-9]{12}$/D', $name)) { exit(1); }
$GLOBALS['BB_CONFIG']['paymongo'] = ['enabled' => true, 'secret_key' => 'sk_test_fixture',
    'webhook_secret' => 'fixture-signing-secret-only', 'public_base_url' => 'https://demo.example.test/bbq'];
baseline_check(paymongo_ready(), 'PayMongo test configuration is recognized');
$GLOBALS['BB_CONFIG']['paymongo']['secret_key'] = 'sk_live_forbidden';
baseline_check(!paymongo_ready(), 'Live PayMongo keys cannot enable the demo');
$GLOBALS['BB_CONFIG']['paymongo']['secret_key'] = 'sk_test_fixture';
$GLOBALS['BB_CONFIG']['paymongo']['public_base_url'] = 'https://user@demo.example.test';
baseline_check(!paymongo_ready(), 'Payment return base rejects embedded credentials');
$GLOBALS['BB_CONFIG']['paymongo']['public_base_url'] = 'https://demo.example.test/bbq';
$pdo->exec('UPDATE inventory SET quantity_available = 100 WHERE product_id = ' . $product);
$provider = []; $creates = 0; $requests = 0;
$transport = static function (string $method, string $path, ?array $payload) use (&$provider, &$creates, &$requests): array {
    $requests++;
    if ($method === 'POST') {
        $creates++;
        $a = $payload['data']['attributes'];
        baseline_check($a['payment_method_types'] === ['gcash','card'], 'Demo checkout excludes scannable QR Ph payments');
        baseline_check($a['line_items'][0]['amount'] === 9900 && $a['line_items'][0]['currency'] === 'PHP', 'Provider checkout uses server snapshot prices in centavos');
        baseline_check(str_starts_with($a['success_url'], 'https://demo.example.test/bbq/customer/order.php?ref='), 'Return URL comes from trusted configuration');
        $id = 'cs_fixture' . $creates;
        $provider[$id] = ['id' => $id, 'type' => 'checkout_session', 'attributes' => [
            'reference_number' => $a['reference_number'], 'livemode' => false,
            'checkout_url' => 'https://checkout.paymongo.com/fixture' . $creates,
            'payments' => [], 'payment_intent' => ['id' => 'pi_fixture' . $creates, 'attributes' => ['amount' => 9900, 'currency' => 'PHP', 'status' => 'awaiting_payment_method']],
        ]];
        return ['data' => $provider[$id]];
    }
    if (!str_starts_with($path, '/v1/checkout_sessions/')) { throw new RuntimeException('Incorrect provider retrieval endpoint.'); }
    $id = basename($path);
    if (!isset($provider[$id])) { throw new RuntimeException('Fixture session unavailable.'); }
    return ['data' => $provider[$id]];
};
$newPending = static function () use ($customer, $product): array {
    $_SESSION['cart'] = [$product => 1];
    $result = place_order($customer, 'GCASH', 'PayMongo integration fixture');
    if (!$result['ok']) { throw new RuntimeException('Cannot create gateway fixture.'); }
    return $result;
};
$markProviderPaid = static function (string $id) use (&$provider): void {
    $provider[$id]['attributes']['payment_intent']['attributes']['status'] = 'succeeded';
    $provider[$id]['attributes']['payments'] = [['id' => 'pay_' . substr($id, 3), 'type' => 'payment', 'attributes' => [
        'amount' => 9900, 'currency' => 'PHP', 'status' => 'paid', 'livemode' => false,
        'payment_intent_id' => $provider[$id]['attributes']['payment_intent']['id'],
    ]]];
};
$eventBody = static function (string $id, bool $live = false) use (&$provider): string {
    return json_encode(['data' => ['id' => 'evt_fixture', 'type' => 'event', 'attributes' => [
        'type' => 'checkout_session.payment.paid', 'livemode' => $live, 'data' => $provider[$id],
    ]]], JSON_THROW_ON_ERROR);
};
$sign = static function (string $body, ?int $timestamp = null): string {
    $timestamp ??= time();
    return 't=' . $timestamp . ',te=' . hash_hmac('sha256', $timestamp . '.' . $body, 'fixture-signing-secret-only') . ',li=';
};
$order = $newPending(); $id = (int) $order['order_id'];
baseline_check(!paymongo_create_checkout($id, $manager, $transport)['ok'] && $creates === 0, 'Another account cannot start customer checkout');
$checkout = paymongo_create_checkout($id, $customer, $transport);
baseline_check($checkout['ok'] && $creates === 1, 'PayMongo checkout session is persisted');
baseline_check(paymongo_create_checkout($id, $customer, $transport)['checkout_url'] === $checkout['checkout_url'] && $creates === 1, 'Checkout retry reuses the original payable session');
baseline_check(paymongo_reconcile_session('cs_fixture1', $transport)['paid'] === false, 'An unpaid provider session is not treated as paid');
$body = $eventBody('cs_fixture1');
baseline_check(paymongo_verify_signature($body, $sign($body)), 'Raw webhook signature verifies');
baseline_check(!paymongo_verify_signature($body . ' ', $sign($body)), 'Changed webhook bytes invalidate the signature');
baseline_check(!paymongo_verify_signature($body, $sign($body, time() - 301)), 'Stale webhook signatures are rejected');
baseline_check(paymongo_handle_webhook($body, 'invalid', $transport)['status'] === 400, 'Unsigned webhook cannot confirm payment');
baseline_check(paymongo_handle_webhook($body, $sign($body), $transport)['status'] === 503, 'Paid event with a still-pending provider response requests retry');
$markProviderPaid('cs_fixture1'); $body = $eventBody('cs_fixture1');
baseline_check(paymongo_handle_webhook($body, $sign($body), $transport)['status'] === 200, 'Signed test webhook verifies provider session and confirms payment');
baseline_check($pdo->query('SELECT payment_status FROM orders WHERE id = ' . $id)->fetchColumn() === 'PAID', 'Verified provider payment reaches the order');
$countEvents = (int) $pdo->query('SELECT COUNT(*) FROM payment_events WHERE order_id = ' . $id)->fetchColumn();
baseline_check(paymongo_handle_webhook($body, $sign($body), $transport)['status'] === 200
    && (int) $pdo->query('SELECT COUNT(*) FROM payment_events WHERE order_id = ' . $id)->fetchColumn() === $countEvents, 'Webhook redelivery cannot duplicate the payment');
$liveBody = $eventBody('cs_fixture1', true);
baseline_check(paymongo_handle_webhook($liveBody, $sign($liveBody), $transport)['status'] === 400, 'A signed live-mode event is rejected by the demo');

$badOrder = $newPending(); $badId = (int) $badOrder['order_id']; paymongo_create_checkout($badId, $customer, $transport);
$markProviderPaid('cs_fixture2');
$provider['cs_fixture2']['attributes']['payments'][0]['attributes']['amount'] = 1;
$body = $eventBody('cs_fixture2');
baseline_check(paymongo_handle_webhook($body, $sign($body), $transport)['status'] === 503
    && $pdo->query('SELECT payment_status FROM orders WHERE id = ' . $badId)->fetchColumn() === 'PENDING', 'Mismatched provider amount never marks an order paid');
$markProviderPaid('cs_fixture2');
$provider['cs_fixture2']['attributes']['livemode'] = true;
baseline_check(!paymongo_reconcile_session('cs_fixture2', $transport)['ok'], 'Server retrieval also rejects live-mode sessions');
$provider['cs_fixture2']['attributes']['livemode'] = false;
$provider['cs_fixture2']['attributes']['reference_number'] = 'foreign-reference';
baseline_check(!paymongo_reconcile_session('cs_fixture2', $transport)['ok'], 'Provider session must match the saved order reference');

$ambiguous = $newPending(); $ambiguousId = (int) $ambiguous['order_id']; $attempts = 0;
$timeout = static function () use (&$attempts): array { $attempts++; throw new RuntimeException('Simulated transport timeout'); };
baseline_check(!paymongo_create_checkout($ambiguousId, $customer, $timeout)['ok'], 'Creation timeout leaves a durable ambiguous attempt');
baseline_check(!paymongo_create_checkout($ambiguousId, $customer, $timeout)['ok'] && $attempts === 1, 'Ambiguous creation is never retried as a second payable session');
$reference = $pdo->query('SELECT reference_number FROM paymongo_checkouts WHERE order_id = ' . $ambiguousId)->fetchColumn();
$provider['cs_recovered'] = $provider['cs_fixture1'];
$provider['cs_recovered']['id'] = 'cs_recovered';
$provider['cs_recovered']['attributes']['reference_number'] = $reference;
$provider['cs_recovered']['attributes']['payments'][0]['id'] = 'pay_recovered';
$body = $eventBody('cs_recovered');
baseline_check(paymongo_handle_webhook($body, $sign($body), $transport)['status'] === 200
    && $pdo->query('SELECT payment_status FROM orders WHERE id = ' . $ambiguousId)->fetchColumn() === 'PAID', 'Webhook can recover a session whose creation response was lost');

$late = $newPending(); $lateId = (int) $late['order_id']; paymongo_create_checkout($lateId, $customer, $transport);
$markProviderPaid('cs_fixture3');
$pdo->exec('UPDATE orders SET reservation_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ' . $lateId);
$stockBefore = $stock();
$body = $eventBody('cs_fixture3');
baseline_check(paymongo_handle_webhook($body, $sign($body), $transport)['status'] === 200
    && $pdo->query('SELECT payment_status FROM orders WHERE id = ' . $lateId)->fetchColumn() === 'REFUND_REQUIRED'
    && $stock() === $stockBefore + 1, 'Gateway confirmation after expiry releases stock once and requires refund');
baseline_check(!paymongo_checkout_url('https://checkout.paymongo.com.evil.test/pay') && !paymongo_checkout_url('http://checkout.paymongo.com/pay'), 'Only HTTPS redirects to the PayMongo checkout host are allowed');
$cancelDuringCreation = $newPending(); $cancelId = (int) $cancelDuringCreation['order_id'];
$cancelTransport = static function ($method, $path, $payload) use ($transport, $cancelId, $manager): array {
    $response = $transport($method, $path, $payload);
    advance_order_status($cancelId, 'CANCELLED', $manager);
    return $response;
};
baseline_check(!paymongo_create_checkout($cancelId, $customer, $cancelTransport)['ok'], 'Cancellation during provider creation prevents a redirect to pay');
$evilOrder = $newPending();
$evilTransport = static function (): array {
    return ['data' => ['id'=>'cs_evil','type'=>'checkout_session','attributes'=>['livemode'=>false,'checkout_url'=>'https://evil.example.test/pay']]];
};
baseline_check(!paymongo_create_checkout((int) $evilOrder['order_id'], $customer, $evilTransport)['ok'], 'Unexpected checkout redirect host fails closed');
$GLOBALS['BB_CONFIG']['paymongo'] = [];
baseline_check(!paymongo_create_checkout($badId, $customer, $transport)['ok'], 'Missing credentials disable checkout without contacting the provider');
