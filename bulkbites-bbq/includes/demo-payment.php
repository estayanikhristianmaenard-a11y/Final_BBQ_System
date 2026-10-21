<?php
/** Local presentation simulator. Never contacts or confirms a real payment provider. */
function demo_payment_enabled(): bool { return config('app.demo_payments_enabled', true) === true; }
function simulate_order_payment(int $orderId, int $customerId, string $outcome): array
{
    if (!demo_payment_enabled()) { return ['ok'=>false,'message'=>'The payment simulator is disabled.']; }
    if (!in_array($outcome,['success','failed','cancelled'],true)) { return ['ok'=>false,'message'=>'Choose a valid demo outcome.']; }
    $own=!db()->inTransaction();
    $result=order_transaction(static function (PDO $pdo) use ($orderId,$customerId,$outcome): array {
        $order=locked_order($pdo,$orderId);
        if ((int)$order['customer_id']!==$customerId) { throw new RuntimeException('Order not found.'); }
        if ($order['payment_method']!=='DEMO' || !(int)$order['payment_is_test']) { throw new RuntimeException('Only demo orders can use the payment simulator.'); }
        $reference='test:demo:order:' . $orderId;
        if ($order['payment_status']==='PAID' && $order['payment_reference']===$reference) { queue_order_receipt($orderId);return ['ok'=>true,'receipt_queued'=>true,'message'=>'Demo payment was already recorded. No real money was collected.']; }
        if (reservation_due($order)) { expire_locked_order($pdo,$order); return ['ok'=>false,'message'=>'The payment window expired. Stock was released; please place a new order.']; }
        if ($order['status']!=='NEW' || $order['payment_status']!=='PENDING' || $order['stock_state']!=='RESERVED') { throw new RuntimeException('This order is no longer awaiting a demo payment.'); }
        if ($outcome!=='success') {
            $message=$outcome==='failed' ? 'Demo payment failed. Your order is unpaid; retry before the reservation expires.' : 'Demo payment was cancelled. Your order is unpaid; retry or cancel the order before the reservation expires.';
            $pdo->prepare('INSERT INTO order_status_history (order_id,old_status,new_status,changed_by,remarks) VALUES (?,"NEW","NEW",?,?)')->execute([$orderId,$customerId,$message]);
            return ['ok'=>false,'message'=>$message];
        }
        // Price and currency come from the saved order, never submitted browser fields.
        $pdo->prepare('INSERT INTO payment_events (order_id,event_type,amount,reference,performed_by,is_test) VALUES (?,"ONLINE_VERIFIED",?,?,?,1)')->execute([$orderId,$order['total'],$reference,$customerId]);
        $pdo->prepare('UPDATE orders SET payment_status="PAID",payment_is_test=1,paid_at=NOW(),payment_reference=?,reservation_expires_at=NULL WHERE id=?')->execute([$reference,$orderId]);
        $message='DEMO PAYMENT confirmed. No real money was collected. Waiting for shop acceptance.';
        $pdo->prepare('INSERT INTO order_status_history (order_id,old_status,new_status,changed_by,remarks) VALUES (?,"NEW","NEW",?,?)')->execute([$orderId,$customerId,$message]);
        notify_user($customerId,$orderId,'GENERAL','Demo payment confirmed',$message);
        queue_order_receipt($orderId);
        return ['ok'=>true,'receipt_queued'=>true,'message'=>$message];
    });
    return payment_receipt_after_commit($result,$orderId,$own);
}
