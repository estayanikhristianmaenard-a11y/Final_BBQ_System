<?php
/** PayMongo Hosted Checkout v2, test mode only. Credentials and payloads never logged. */
function paymongo_settings(): array { return config('paymongo', []); }
function paymongo_ready(): bool
{
    $c = paymongo_settings();
    $url = parse_url((string) ($c['public_base_url'] ?? ''));
    return ($c['enabled'] ?? false) === true
        && preg_match('/^sk_test_[A-Za-z0-9_]+$/D', $c['secret_key'] ?? '') === 1
        && is_string($c['webhook_secret'] ?? null) && strlen($c['webhook_secret']) >= 16
        && is_array($url) && ($url['scheme'] ?? '') === 'https' && !empty($url['host'])
        && !isset($url['user']) && !isset($url['pass']) && !isset($url['query']) && !isset($url['fragment'])
        && !isset($url['port']) && !str_contains($c['public_base_url'], '\\');
}
function paymongo_request(string $method, string $path, ?array $payload = null, ?callable $transport = null): array
{
    if (!paymongo_ready()) { throw new RuntimeException('Online test checkout is not configured. Choose cash or contact the shop.'); }
    if (!(($method === 'POST' && $path === '/v2/checkout_sessions')
        || ($method === 'GET' && preg_match('~^/v1/checkout_sessions/cs_[A-Za-z0-9]+$~D', $path)))) {
        throw new RuntimeException('Invalid payment request.');
    }
    if ($transport !== null) { return $transport($method, $path, $payload); } // Tests only; never populated from a request.
    $curl = curl_init('https://api.paymongo.com' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => paymongo_settings()['secret_key'] . ':', CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    if ($payload !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR)); }
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException('PayMongo could not complete the request. Your order is not marked paid. Please check again later.');
    }
    try { $json = json_decode($body, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $error) { throw new RuntimeException('The payment provider returned an unreadable response.'); }
    if (!is_array($json['data'] ?? null)) { throw new RuntimeException('The payment provider returned an incomplete response.'); }
    return $json;
}
function paymongo_checkout_url(string $url): bool
{
    $parts = parse_url($url);
    return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'checkout.paymongo.com'
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']) && !str_contains($url, '\\');
}
function paymongo_create_checkout(int $orderId, int $customerId, ?callable $transport = null): array
{
    if (!paymongo_ready()) { return ['ok' => false, 'message' => 'Online test checkout is not configured.']; }
    // Persist the attempt BEFORE the network call. An ambiguous timeout cannot create a second payable session.
    $claim = order_transaction(static function (PDO $pdo) use ($orderId, $customerId): array {
        $order = locked_order($pdo, $orderId);
        if ((int) $order['customer_id'] !== $customerId) { throw new RuntimeException('Order not found.'); }
        if (reservation_due($order)) { expire_locked_order($pdo, $order); return ['ok' => false, 'message' => 'The payment window expired. Please place a new order.']; }
        if ($order['payment_method'] !== 'GCASH' || $order['payment_status'] !== 'PENDING' || $order['status'] !== 'NEW') {
            throw new RuntimeException('This order is not awaiting online payment.');
        }
        $query = $pdo->prepare('SELECT * FROM paymongo_checkouts WHERE order_id = ?');
        $query->execute([$orderId]);
        if ($saved = $query->fetch()) {
            if ($saved['checkout_url'] && $saved['state'] === 'READY' && paymongo_checkout_url($saved['checkout_url'])) {
                return ['ok' => true, 'checkout_url' => $saved['checkout_url'], 'existing' => true];
            }
            throw new RuntimeException('Checkout is being created or needs reconciliation. Do not pay through a new order. Contact the shop if this persists.');
        }
        $reference = 'BB-' . $orderId . '-' . bin2hex(random_bytes(12));
        $pdo->prepare('INSERT INTO paymongo_checkouts (order_id, reference_number, state) VALUES (?, ?, "CREATING")')->execute([$orderId, $reference]);
        $lines = $pdo->prepare('SELECT product_name, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
        $lines->execute([$orderId]);
        $items = [];
        foreach ($lines->fetchAll() as $line) {
            $items[] = ['name' => $line['product_name'], 'amount' => money_centavos($line['unit_price']), 'quantity' => (int) $line['quantity'], 'currency' => 'PHP'];
        }
        if (!$items) { throw new RuntimeException('The order has no items.'); }
        if (money_centavos($order['delivery_fee'] ?? '0') > 0) { $items[] = ['name'=>'Delivery fee','amount'=>money_centavos($order['delivery_fee']),'quantity'=>1,'currency'=>'PHP']; }
        return ['ok' => true, 'reference' => $reference, 'order_number' => $order['order_number'], 'items' => $items];
    });
    if (!$claim['ok'] || !empty($claim['existing'])) { return $claim; }
    try {
        $base = rtrim(paymongo_settings()['public_base_url'], '/');
        $return = $base . '/customer/order.php?ref=' . rawurlencode($claim['order_number']);
        $response = paymongo_request('POST', '/v2/checkout_sessions', ['data' => ['attributes' => [
            'line_items' => $claim['items'], 'payment_method_types' => ['gcash','card'],
            'reference_number' => $claim['reference'], 'success_url' => $return . '&payment_return=1',
            'cancel_url' => $return . '&payment_cancelled=1', 'description' => 'BulkBites BBQ TEST order ' . $claim['order_number'],
        ]]], $transport);
        $session = $response['data'];
        $attrs = $session['attributes'] ?? [];
        if (($attrs['livemode'] ?? null) !== false || ($session['type'] ?? '') !== 'checkout_session'
            || !preg_match('/^cs_[A-Za-z0-9]+$/D', $session['id'] ?? '')
            || !paymongo_checkout_url($attrs['checkout_url'] ?? '')) { throw new RuntimeException('Invalid test checkout response.'); }
        $saved = order_transaction(static function (PDO $pdo) use ($orderId, $session, $attrs): array {
            $order = locked_order($pdo, $orderId);
            $query = $pdo->prepare('SELECT session_id FROM paymongo_checkouts WHERE order_id = ? FOR UPDATE');
            $query->execute([$orderId]);
            $existing = $query->fetchColumn();
            if ($existing && $existing !== $session['id']) { throw new RuntimeException('Checkout requires manager reconciliation.'); }
            $pdo->prepare('UPDATE paymongo_checkouts SET session_id = ?, checkout_url = ?, state = "READY" WHERE order_id = ?')->execute([$session['id'], $attrs['checkout_url'], $orderId]);
            if (reservation_due($order)) { expire_locked_order($pdo, $order); $order['status'] = 'CANCELLED'; }
            if ($order['status'] !== 'NEW' || $order['payment_status'] !== 'PENDING') {
                return ['ok' => false, 'message' => 'Checkout was saved for reconciliation, but the order is no longer awaiting payment. Do not pay; check the order status.'];
            }
            return ['ok' => true, 'checkout_url' => $attrs['checkout_url']];
        });
        return $saved;
    } catch (Throwable $error) {
        db()->prepare('UPDATE paymongo_checkouts SET state = "UNKNOWN" WHERE order_id = ? AND state = "CREATING"')->execute([$orderId]);
        return ['ok' => false, 'message' => 'Checkout could not be confirmed. No payment has been recorded. Check the order later or contact the shop; do not create a duplicate payment.'];
    }
}
function paymongo_verify_signature(string $raw, string $header, ?int $now = null): bool
{
    if (!paymongo_ready() || strlen($header) > 512) { return false; }
    $parts = [];
    foreach (explode(',', $header) as $pair) {
        $split = explode('=', trim($pair), 2);
        if (count($split) !== 2 || isset($parts[$split[0]])) { return false; }
        $parts[$split[0]] = $split[1];
    }
    if (!preg_match('/^\d{10}$/D', $parts['t'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $parts['te'] ?? '')
        || abs(($now ?? time()) - (int) $parts['t']) > 300) { return false; }
    return hash_equals(hash_hmac('sha256', $parts['t'] . '.' . $raw, paymongo_settings()['webhook_secret']), $parts['te']);
}
/** Re-fetch the session server-to-server. Neither a redirect nor the posted amount is trusted. */
function paymongo_reconcile_session(string $sessionId, ?callable $transport = null): array
{
    $own=!db()->inTransaction();
    if (!preg_match('/^cs_[A-Za-z0-9]+$/D', $sessionId)) { return ['ok' => false, 'message' => 'Invalid checkout session.']; }
    try {
        $session = paymongo_request('GET', '/v1/checkout_sessions/' . $sessionId, null, $transport)['data'];
        $attrs = $session['attributes'] ?? [];
        if (($session['id'] ?? '') !== $sessionId || ($session['type'] ?? '') !== 'checkout_session' || ($attrs['livemode'] ?? null) !== false) {
            throw new RuntimeException('Test mode session mismatch.');
        }
        $query = db()->prepare('SELECT order_id, session_id, reference_number FROM paymongo_checkouts WHERE reference_number = ?');
        $query->execute([$attrs['reference_number'] ?? '']);
        $checkout = $query->fetch();
        if (!$checkout || ($checkout['session_id'] && $checkout['session_id'] !== $sessionId)) { throw new RuntimeException('Checkout does not match a local order.'); }
        $paid = array_values(array_filter($attrs['payments'] ?? [], static fn($payment): bool => ($payment['attributes']['status'] ?? '') === 'paid'));
        if (count($paid) === 0) { return ['ok' => true, 'paid' => false, 'message' => 'Payment is still pending. Please complete the test checkout or check again later.']; }
        if (count($paid) !== 1) { throw new RuntimeException('Payment requires reconciliation.'); }
        $payment = $paid[0]; $p = $payment['attributes']; $intent = $attrs['payment_intent'] ?? [];
        if (($p['livemode'] ?? null) !== false || !is_int($p['amount'] ?? null) || ($p['currency'] ?? '') !== 'PHP'
            || ($payment['type'] ?? '') !== 'payment' || !preg_match('/^pay_[A-Za-z0-9]+$/D', $payment['id'] ?? '')
            || ($intent['attributes']['status'] ?? '') !== 'succeeded' || ($intent['attributes']['amount'] ?? null) !== $p['amount']
            || ($intent['attributes']['currency'] ?? '') !== 'PHP' || ($p['payment_intent_id'] ?? '') !== ($intent['id'] ?? null)) {
            throw new RuntimeException('Payment details did not match the test checkout.');
        }
        $result=order_transaction(static function (PDO $pdo) use ($checkout, $sessionId, $payment, $p): array {
            $id = (int) $checkout['order_id']; locked_order($pdo, $id);
            $query = $pdo->prepare('SELECT session_id FROM paymongo_checkouts WHERE order_id = ? FOR UPDATE');
            $query->execute([$id]);
            $bound = $query->fetchColumn();
            if ($bound && $bound !== $sessionId) { throw new RuntimeException('Checkout session changed during verification.'); }
            $pdo->prepare('UPDATE paymongo_checkouts SET session_id = ?, state = "READY" WHERE order_id = ? AND (session_id IS NULL OR session_id = ?)')->execute([$sessionId, $id, $sessionId]);
            $result = record_verified_online_payment($id, $payment['id'], $p['amount'], 'PHP', true);
            if (!$result['ok']) { throw new RuntimeException('Payment could not be matched to this order.'); }
            $result['paid'] = true;
            return $result;
        });
        return payment_receipt_after_commit($result,(int)$checkout['order_id'],$own);
    } catch (Throwable $error) { return ['ok' => false, 'message' => 'Payment verification is temporarily unavailable or the details did not match. The order has not been accepted.']; }
}
function paymongo_handle_webhook(string $raw, string $signature, ?callable $transport = null): array
{
    if (!paymongo_ready()) { return ['status' => 503, 'ok' => false]; }
    if (strlen($raw) > 262144 || !paymongo_verify_signature($raw, $signature)) { return ['status' => 400, 'ok' => false]; }
    try {
        $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($body) || !is_array($body['data'] ?? null)) { return ['status' => 400, 'ok' => false]; }
        $event = $body['data'];
    }
    catch (Throwable $error) { return ['status' => 400, 'ok' => false]; }
    if (($event['type'] ?? '') !== 'event' || !preg_match('/^evt_[A-Za-z0-9]+$/D', $event['id'] ?? '') || ($event['attributes']['livemode'] ?? null) !== false) { return ['status' => 400, 'ok' => false]; }
    if (($event['attributes']['type'] ?? '') !== 'checkout_session.payment.paid') { return ['status' => 200, 'ok' => true]; }
    $session = $event['attributes']['data'] ?? [];
    $id = $session['id'] ?? '';
    if (($session['type'] ?? '') !== 'checkout_session' || !is_string($id) || !preg_match('/^cs_[A-Za-z0-9]+$/D', $id)) { return ['status' => 400, 'ok' => false]; }
    // Unknown sessions may belong to another app on the merchant account.
    $query = db()->prepare('SELECT order_id FROM paymongo_checkouts WHERE session_id = ? OR (session_id IS NULL AND reference_number = ?)');
    $query->execute([$id, $session['attributes']['reference_number'] ?? '']);
    if (!$query->fetchColumn()) { return ['status' => 200, 'ok' => true]; }
    $result = paymongo_reconcile_session($id, $transport);
    $confirmed = $result['ok'] && !empty($result['paid']);
    return ['status' => $confirmed ? 200 : 503, 'ok' => $confirmed];
}
