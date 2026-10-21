<?php
/** Money conversion, reservation lifecycle, and payment guards. No public online-confirmation route. */
function money_centavos(string $amount): int
{
    if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
        throw new RuntimeException('Invalid monetary amount.');
    }
    return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
}
function centavos_decimal(int $amount): string
{
    if ($amount < 0 || $amount > 9999999999) { throw new RuntimeException('Order total is outside the allowed range.'); }
    return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
}
/** Savepoints keep a failed nested operation from leaking partial changes to its caller. */
function order_transaction(callable $operation): array
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    $savepoint = 'bb_' . bin2hex(random_bytes(5));
    if ($own) { $pdo->beginTransaction(); } else { $pdo->exec('SAVEPOINT ' . $savepoint); }
    try {
        $result = $operation($pdo);
        if ($own) { $pdo->commit(); } else { $pdo->exec('RELEASE SAVEPOINT ' . $savepoint); }
        return $result;
    } catch (Throwable $error) {
        if ($own && $pdo->inTransaction()) { $pdo->rollBack(); }
        elseif (!$own && $pdo->inTransaction()) { $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint); $pdo->exec('RELEASE SAVEPOINT ' . $savepoint); }
        return ['ok' => false, 'message' => $error instanceof PDOException ? 'The operation could not be saved. Please retry.' : $error->getMessage()];
    }
}
function locked_order(PDO $pdo, int $id): array
{
    $query = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
    $query->execute([$id]);
    $order = $query->fetch();
    if (!$order) { throw new RuntimeException('Order not found.'); }
    return $order;
}
function require_payment_staff(PDO $pdo, int $id, bool $managerOnly = false): void
{
    $query = $pdo->prepare('SELECT role FROM users WHERE id = ? AND is_active = 1');
    $query->execute([$id]);
    if (!in_array($query->fetchColumn(), $managerOnly ? ['MANAGER'] : ['STAFF', 'MANAGER'], true)) {
        throw new RuntimeException('Authorized personnel access is required.');
    }
}
/** Caller holds the order lock. Only a tracked reservation may be returned. */
function release_order_stock(PDO $pdo, array $order, ?int $actor, string $reason): void
{
    if ($order['stock_state'] !== 'RESERVED') { return; }
    $query = $pdo->prepare('SELECT product_id, SUM(quantity) AS quantity FROM order_items WHERE order_id = ? GROUP BY product_id ORDER BY product_id');
    $query->execute([$order['id']]);
    foreach ($query->fetchAll() as $line) {
        $stock = $pdo->prepare('UPDATE inventory SET quantity_available = quantity_available + ?, updated_by = ? WHERE product_id = ?');
        $stock->execute([$line['quantity'], $actor, $line['product_id']]);
        if ($stock->rowCount() !== 1) { throw new RuntimeException('Stock record missing; cancellation was not saved.'); }
        $pdo->prepare('INSERT INTO inventory_movements (product_id, movement_type, quantity_change, reference_order_id, performed_by, remarks) VALUES (?, "ADJUSTMENT", ?, ?, ?, ?)')
            ->execute([$line['product_id'], $line['quantity'], $order['id'], $actor, mb_substr($reason, 0, 255)]);
    }
    $pdo->prepare('UPDATE orders SET stock_state = "RELEASED" WHERE id = ?')->execute([$order['id']]);
}
function reservation_due(array $order): bool
{
    return $order['status'] === 'NEW' && in_array($order['payment_status'], ['UNPAID', 'PENDING'], true)
        && $order['reservation_expires_at'] !== null && strtotime($order['reservation_expires_at']) <= time();
}
function expire_locked_order(PDO $pdo, array $order): void
{
    release_order_stock($pdo, $order, null, 'Unpaid reservation expired: ' . $order['order_number']);
    $pdo->prepare('UPDATE orders SET status = "CANCELLED", payment_status = "EXPIRED", reservation_expires_at = NULL WHERE id = ?')->execute([$order['id']]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, remarks) VALUES (?, "NEW", "CANCELLED", "Unpaid reservation expired")')->execute([$order['id']]);
    notify_user((int) $order['customer_id'], (int) $order['id'], 'ORDER_STATUS', 'Order reservation expired', 'Your unpaid order reservation expired. Stock was released; please place a new order.');
}
function expire_order_reservations(int $limit = 50): int
{
    $ids = db()->query('SELECT id FROM orders WHERE status = "NEW" AND payment_status IN ("UNPAID","PENDING") AND reservation_expires_at <= NOW() ORDER BY id LIMIT ' . max(1, min(500, $limit)))->fetchAll(PDO::FETCH_COLUMN);
    $expired = 0;
    foreach ($ids as $id) {
        $result = order_transaction(static function (PDO $pdo) use ($id): array {
            $order = locked_order($pdo, (int) $id);
            if (!reservation_due($order)) { return ['ok' => true, 'expired' => false]; }
            expire_locked_order($pdo, $order);
            return ['ok' => true, 'expired' => true];
        });
        if (empty($result['ok'])) { throw new RuntimeException('Expired stock reservations could not be released. Please retry.'); }
        $expired += !empty($result['expired']) ? 1 : 0;
    }
    return $expired;
}
function collect_cash_payment(int $id, int $actor): array
{
    $own=!db()->inTransaction();
    $result=order_transaction(static function (PDO $pdo) use ($id, $actor): array {
        require_payment_staff($pdo, $actor);
        $order = locked_order($pdo, $id);
        if ($order['payment_method'] !== 'CASH' || $order['status'] !== 'READY') { throw new RuntimeException('Cash can only be collected for a ready cash order.'); }
        if ($order['payment_status'] === 'PAID') { queue_order_receipt($id);return ['ok' => true, 'receipt_queued'=>true, 'message' => 'Cash was already recorded.']; }
        if (!in_array($order['payment_status'], ['UNPAID', 'LEGACY_UNVERIFIED'], true)) { throw new RuntimeException('This payment cannot be collected.'); }
        $reference = 'cash:' . $id;
        $pdo->prepare('INSERT INTO payment_events (order_id,event_type,amount,reference,performed_by) VALUES (?,"CASH_COLLECTED",?,?,?)')->execute([$id, $order['total'], $reference, $actor]);
        $pdo->prepare('UPDATE orders SET payment_status = "PAID", paid_at = NOW(), paid_by = ?, payment_reference = ?, reservation_expires_at = NULL WHERE id = ?')->execute([$actor, $reference, $id]);
        notify_user((int) $order['customer_id'], $id, 'GENERAL', 'Cash payment recorded', 'The shop confirmed payment for order ' . $order['order_number'] . '.');
        queue_order_receipt($id);
        return ['ok' => true, 'receipt_queued'=>true, 'message' => 'Cash collection confirmed. Complete the order after handing over the food.'];
    });
    return payment_receipt_after_commit($result,$id,$own);
}
/** Trusted integration boundary: call ONLY after provider signature, mode and order identity verification. */
function record_verified_online_payment(int $id, string $reference, int $amountCentavos, string $currency, bool $isTest): array
{
    $own=!db()->inTransaction();
    $result=order_transaction(static function (PDO $pdo) use ($id, $reference, $amountCentavos, $currency, $isTest): array {
        if ($reference === '' || strlen($reference) > 110 || $currency !== 'PHP') { throw new RuntimeException('Invalid payment confirmation.'); }
        $order = locked_order($pdo, $id);
        if ($order['payment_method'] !== 'GCASH' || money_centavos((string) $order['total']) !== $amountCentavos) { throw new RuntimeException('Payment does not match the order.'); }
        $eventReference = ($isTest ? 'test:' : 'live:') . $reference;
        $existing = $pdo->prepare('SELECT order_id, is_test FROM payment_events WHERE reference = ?');
        $existing->execute([$eventReference]);
        $event = $existing->fetch();
        if ($event) {
            if ((int) $event['order_id'] !== $id) { throw new RuntimeException('Payment reference already belongs to another order.'); }
            if($order['payment_status']==='PAID') { queue_order_receipt($id); }
            return ['ok' => true, 'receipt_queued'=>$order['payment_status']==='PAID', 'message' => 'Payment already recorded.'];
        }
        if ($order['paid_at'] !== null) { throw new RuntimeException('A different payment is already recorded. Manager reconciliation is required.'); }
        if (reservation_due($order)) { expire_locked_order($pdo, $order); $order = locked_order($pdo, $id); }
        $usable = $order['status'] === 'NEW' && $order['stock_state'] === 'RESERVED' && $order['payment_status'] === 'PENDING';
        $paymentStatus = $usable ? 'PAID' : 'REFUND_REQUIRED';
        $pdo->prepare('INSERT INTO payment_events (order_id,event_type,amount,reference,is_test) VALUES (?,"ONLINE_VERIFIED",?,?,?)')->execute([$id, $order['total'], $eventReference, (int) $isTest]);
        $pdo->prepare('UPDATE orders SET payment_status = ?, paid_at = NOW(), payment_reference = ?, payment_is_test = ?, reservation_expires_at = NULL WHERE id = ?')->execute([$paymentStatus, $eventReference, (int) $isTest, $id]);
        notify_user((int) $order['customer_id'], $id, 'GENERAL', $isTest ? 'Test payment recorded' : 'Online payment recorded', $usable ? 'Payment confirmed. Waiting for shop acceptance.' : 'Payment arrived after the order became unavailable. Contact the shop for refund handling.');
        if($usable) { queue_order_receipt($id); }
        return ['ok' => true, 'receipt_queued'=>$usable, 'message' => $usable ? 'Payment confirmed.' : 'Payment recorded; refund required.'];
    });
    return payment_receipt_after_commit($result,$id,$own);
}
function record_order_refund(int $id, int $actor, string $reference): array
{
    return order_transaction(static function (PDO $pdo) use ($id, $actor, $reference): array {
        require_payment_staff($pdo, $actor, true);
        $order = locked_order($pdo, $id);
        if ($reference === '' || strlen($reference) > 100) { throw new RuntimeException('Enter a refund confirmation reference (up to 100 characters).'); }
        if ($order['payment_status'] === 'REFUNDED') { throw new RuntimeException('Refund already recorded.'); }
        if ($order['payment_status'] !== 'REFUND_REQUIRED') { throw new RuntimeException('This order is not awaiting a refund.'); }
        $pdo->prepare('INSERT INTO payment_events (order_id,event_type,amount,reference,performed_by,is_test) VALUES (?,"REFUND_RECORDED",?,?,?,?)')
            ->execute([$id, $order['total'], 'refund:' . ($order['payment_is_test'] ? 'test:' : 'live:') . $reference, $actor, $order['payment_is_test']]);
        $pdo->prepare('UPDATE orders SET payment_status = "REFUNDED", refunded_at = NOW() WHERE id = ?')->execute([$id]);
        notify_user((int) $order['customer_id'], $id, 'GENERAL', 'Refund recorded', 'The manager recorded a refund for order ' . $order['order_number'] . '.');
        return ['ok' => true, 'message' => 'Refund recorded. This records a completed refund; it does not transfer money.'];
    });
}
function payment_status_label(string $status, ?string $orderStatus = null, string $fulfillmentType = 'PICKUP'): string
{
    if ($orderStatus === 'CANCELLED') {
        if (in_array($status, ['UNPAID','PENDING','FAILED','EXPIRED'], true)) { return 'Cancelled - no payment due'; }
        if ($status === 'PAID') { return 'Cancelled - payment received; contact the shop about a refund'; }
    }
    if ($status === 'UNPAID' && $fulfillmentType === 'DELIVERY') { return 'Unpaid - cash on delivery'; }
    return ['LEGACY_UNVERIFIED' => 'Historical payment unverified', 'UNPAID' => 'Unpaid - cash at pickup', 'PENDING' => 'Awaiting online payment', 'PAID' => 'Paid', 'EXPIRED' => 'Payment window expired', 'REFUND_REQUIRED' => 'Refund required', 'REFUNDED' => 'Refunded'][$status] ?? $status;
}
function payment_method_label(string $method): string
{
    return ['CASH'=>'Cash','GCASH'=>'GCash / card','DEMO'=>'Demo payment'][$method] ?? $method;
}
function order_payment_prefix(array $order): string
{
    return $order['payment_method']==='DEMO' ? 'DEMO: ' : (!empty($order['payment_is_test']) ? 'TEST: ' : '');
}
function order_available_actions(array $order): array
{
    $actions = order_status_transitions()[$order['status']] ?? [];
    if ($order['payment_method'] !== 'CASH' && $order['payment_status'] !== 'PAID') { $actions = array_values(array_diff($actions, ['ACCEPTED','PREPARING','READY'])); }
    if ($order['payment_status'] !== 'PAID') { $actions = array_values(array_diff($actions, ['COMPLETED'])); }
    if ($order['status'] === 'READY' && $order['payment_method'] === 'CASH' && in_array($order['payment_status'], ['UNPAID','LEGACY_UNVERIFIED'], true)) { array_unshift($actions, 'COLLECT_CASH'); }
    return $actions;
}
