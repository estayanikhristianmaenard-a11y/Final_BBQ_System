<?php
/** Payment receipts are queued with the payment transaction; SMTP runs after commit. */
function payment_receipt_after_commit(array $result, int $orderId, bool $ownTransaction): array
{
    if (!empty($result['ok']) && !empty($result['receipt_queued']) && $ownTransaction) {
        $result['receipt_email_status']=send_order_receipt($orderId)['status'];
    }
    return $result;
}
function queue_order_receipt(int $orderId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT u.email FROM orders o JOIN users u ON u.id = o.customer_id WHERE o.id = ?');
    $stmt->execute([$orderId]);
    $email = $stmt->fetchColumn();
    $valid = is_string($email) && valid_login_email($email);
    $pdo->prepare('INSERT INTO receipt_email_deliveries (order_id, recipient, status, last_error)
        VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE order_id = VALUES(order_id)')
        ->execute([$orderId, $valid ? $email : null, $valid ? 'PENDING' : 'SKIPPED', $valid ? null : 'missing_email']);
}

function receipt_email_content(array $receipt, array $items): array
{
    $name = ($receipt['pickup_name'] ?? null) ?: ($receipt['display_name'] ?: $receipt['full_name']);
    $location = ($receipt['pickup_location'] ?? null) ?: 'Greensite Compound, Sta. Lucia, Pasig City';
    $date = date('M j, Y g:i A', strtotime($receipt['paid_at'] ?? $receipt['issued_at']));
    $completed=($receipt['status'] ?? 'COMPLETED')==='COMPLETED';
    $intro=$completed ? 'Your order is completed. Thank you for ordering!' : 'Payment confirmed. Follow your order page for order updates.';
    $testNotice = $receipt['payment_method']==='DEMO' ? 'DEMO PAYMENT - no real money collected. ' : (!empty($receipt['payment_is_test']) ? 'TEST PAYMENT - no real money collected. ' : '');
    $text = $testNotice . "BulkBites BBQ\n" . $location . "\n\n"
        . $intro . "\nReceipt: {$receipt['receipt_number']}\nOrder: {$receipt['order_number']}\n"
        . "Date: {$date}\nCustomer: {$name}\n\n";
    $rows = '';
    foreach ($items as $item) {
        $text .= $item['product_name'] . ' x ' . (int) $item['quantity'] . ' @ ' . peso($item['unit_price']) . ' = ' . peso($item['line_total']) . "\n";
        $cell='padding:12px 4px;border-bottom:1px solid #dfe3db;vertical-align:top;overflow-wrap:anywhere;word-break:break-word;';
        $rows .= '<tr><td style="' . $cell . '">' . e($item['product_name']) . '</td><td align="right" style="' . $cell . '">'
            . (int) $item['quantity'] . '</td><td align="right" style="' . $cell . '">' . e(peso($item['unit_price']))
            . '</td><td align="right" style="' . $cell . '">' . e(peso($item['line_total'])) . '</td></tr>';
    }
    $delivery = is_delivery($receipt);
    $fulfillment = fulfillment_label($receipt);
    $destination = $delivery ? ($receipt['delivery_address'] ?? '') : $location;
    $fee = $receipt['delivery_fee'] ?? '0.00';
    $subtotal = $receipt['subtotal'] ?? centavos_decimal(money_centavos($receipt['total']) - money_centavos($fee));
    $text .= "\nOrder type: " . $fulfillment . "\n" . $destination . "\nContact: " . ($receipt['pickup_contact'] ?? '')
        . "\nFood subtotal: " . peso($subtotal) . "\nDelivery fee: " . peso($fee);
    $text .= "\nTotal: " . peso($receipt['total']) . "\nPayment method: " . $receipt['payment_method'] . "\nOrder status: " . ($receipt['status'] ?? 'COMPLETED') . "\n";
    $content='<h1 style="margin:0 0 8px;font-size:24px;line-height:1.25;color:#242824">Your receipt</h1>'
        . '<p style="margin:0 0 20px;color:#60665e">' . e($intro) . '</p>'
        . ($testNotice ? '<p style="padding:12px;background:#fff7e7;border-left:3px solid #f4ac3d;font-size:13px">' . e($testNotice) . '</p>' : '')
        . '<p style="margin:0 0 20px;font-size:13px;overflow-wrap:anywhere"><strong>' . e($receipt['receipt_number']) . '</strong><br>'
        . 'Order: ' . e($receipt['order_number']) . '<br>' . e($date) . '<br>Customer: ' . e($name) . '</p>'
        . '<table width="100%" cellspacing="0" cellpadding="0" border="0" aria-label="Ordered items" style="width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px">'
        . '<thead><tr style="color:#60665e"><th scope="col" align="left" width="38%" style="padding:8px 4px;border-bottom:2px solid #dfe3db">Item</th>'
        . '<th scope="col" align="right" width="10%" style="padding:8px 4px;border-bottom:2px solid #dfe3db">Qty</th>'
        . '<th scope="col" align="right" width="26%" style="padding:8px 4px;border-bottom:2px solid #dfe3db">Each</th>'
        . '<th scope="col" align="right" width="26%" style="padding:8px 4px;border-bottom:2px solid #dfe3db">Amount</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p style="font-size:13px">Food subtotal: ' . e(peso($subtotal)) . '<br>Delivery fee: ' . e(peso($fee)) . '</p>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding:18px 4px;font-size:18px;font-weight:700">' . ($receipt['payment_method']==='DEMO' ? 'Demo total' : ($testNotice ? 'Test total' : 'Total paid')) . '</td>'
        . '<td align="right" style="padding:18px 4px;font-size:22px;font-weight:700;color:#bc4015">' . e(peso($receipt['total'])) . '</td></tr></table>'
        . '<p style="margin:0 0 20px;font-size:13px;color:#60665e">Payment: ' . e(payment_method_label($receipt['payment_method'])) . '</p>'
        . '<p style="margin:0;font-size:13px;color:#60665e;overflow-wrap:anywhere">' . e($fulfillment) . ' location<br><strong style="color:#242824">' . nl2br(e($destination)) . '</strong><br>Contact: ' . e($receipt['pickup_contact'] ?? '') . '</p>';
    $html=branded_email_html('Your receipt','Your payment receipt from BulkBites BBQ.',$content);
    return ['subject' => 'BulkBites BBQ receipt ' . $receipt['receipt_number'], 'text' => $text, 'html' => $html];
}

function send_order_receipt(int $orderId, bool $retryNow = false): array
{
    $pdo = db();
    if ($pdo->inTransaction()) return ['ok' => false, 'status' => 'PENDING'];
    $lockName = 'receipt-email:' . $orderId;
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) return ['ok' => false, 'status' => 'PENDING'];
    try {
        $stmt = $pdo->prepare('SELECT * FROM receipt_email_deliveries WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $delivery = $stmt->fetch();
        if (!$delivery) return ['ok' => false, 'status' => 'PENDING'];
        if (in_array($delivery['status'], ['SENT', 'SKIPPED'], true)) {
            return ['ok' => $delivery['status'] === 'SENT', 'status' => $delivery['status']];
        }
        if (!$retryNow && ((int) $delivery['attempts'] >= 5 || strtotime($delivery['next_attempt_at']) > time())) {
            return ['ok' => false, 'status' => $delivery['status']];
        }
        $stmt = $pdo->prepare('SELECT r.receipt_number, r.issued_at, r.total, r.payment_method,
            o.order_number, o.subtotal, o.fulfillment_type, o.delivery_fee, o.delivery_address, o.pickup_contact, o.payment_is_test, o.pickup_name, o.pickup_location, o.paid_at, o.status, u.full_name, c.display_name
            FROM receipts r JOIN orders o ON o.id = r.order_id
            JOIN users u ON u.id = o.customer_id LEFT JOIN customers c ON c.user_id = u.id
            WHERE o.id = ? AND o.payment_status = "PAID" AND o.status <> "CANCELLED"');
        $stmt->execute([$orderId]);
        $receipt = $stmt->fetch();
        if (!$receipt) {
            $pdo->prepare('UPDATE receipt_email_deliveries SET status="SKIPPED",last_error="order_no_longer_paid" WHERE order_id=?')->execute([$orderId]);
            return ['ok'=>false,'status'=>'SKIPPED'];
        }
        $stmt = $pdo->prepare('SELECT product_name, quantity, unit_price, line_total FROM order_items WHERE order_id = ? ORDER BY id');
        $stmt->execute([$orderId]);
        $content = receipt_email_content($receipt, $stmt->fetchAll());
        $pdo->prepare('UPDATE receipt_email_deliveries SET attempts = attempts + 1, next_attempt_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE order_id = ?')->execute([$orderId]);
        $result = deliver_system_email($delivery['recipient'], $content['subject'], $content['text'], null, $content['html']);
        $status = $result['ok'] ? 'SENT' : 'FAILED';
        $pdo->prepare('UPDATE receipt_email_deliveries SET status = ?, sent_at = ?, last_error = ? WHERE order_id = ?')
            ->execute([$status, $result['ok'] ? date('Y-m-d H:i:s') : null, $result['error_code'], $orderId]);
        return ['ok' => $result['ok'], 'status' => $status];
    } catch (Throwable $error) {
        error_log('[BulkBites] Receipt email deferred for retry.');
        return ['ok' => false, 'status' => 'PENDING'];
    } finally {
        try { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]); }
        catch (Throwable $error) { error_log('[BulkBites] Receipt email lock release deferred to connection close.'); }
    }
}

/** Automatic retries from the staff queue; also callable by a scheduled CLI job. */
function retry_receipt_emails(int $limit = 1, bool $retryNow = false): int
{
    $sql = "SELECT order_id FROM receipt_email_deliveries WHERE status IN ('PENDING','FAILED')";
    if (!$retryNow) $sql .= ' AND attempts < 5 AND next_attempt_at <= NOW()';
    $rows = db()->query($sql . ' ORDER BY created_at LIMIT ' . max(1, min(10, $limit)))->fetchAll(PDO::FETCH_COLUMN);
    $sent = 0;
    foreach ($rows as $id) if (send_order_receipt((int) $id, $retryNow)['ok']) $sent++;
    return $sent;
}
