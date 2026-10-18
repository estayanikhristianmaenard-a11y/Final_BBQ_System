<?php
/** Receipt queue is committed with completion; SMTP runs only after commit. */
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
    $name = $receipt['display_name'] ?: $receipt['full_name'];
    $date = date('M j, Y g:i A', strtotime($receipt['issued_at']));
    $text = "BulkBites BBQ\nGreensite Compound, Sta. Lucia, Pasig City\n\n"
        . "Your order is completed. Thank you!\nReceipt: {$receipt['receipt_number']}\nOrder: {$receipt['order_number']}\n"
        . "Date: {$date}\nCustomer: {$name}\n\n";
    $rows = '';
    foreach ($items as $item) {
        $text .= $item['product_name'] . ' x ' . (int) $item['quantity'] . ' @ ' . peso($item['unit_price']) . ' = ' . peso($item['line_total']) . "\n";
        $rows .= '<tr><td style="padding:8px;border-bottom:1px solid #ddd">' . e($item['product_name']) . '</td><td style="padding:8px">'
            . (int) $item['quantity'] . '</td><td style="padding:8px">' . e(peso($item['unit_price']))
            . '</td><td style="padding:8px">' . e(peso($item['line_total'])) . '</td></tr>';
    }
    $text .= "\nTotal: " . peso($receipt['total']) . "\nPayment method: " . $receipt['payment_method'] . "\nStatus: COMPLETED\n";
    $html = '<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;color:#292524">'
        . '<div style="max-width:650px;margin:auto;padding:24px;border:1px solid #ddd">'
        . '<h1>BulkBites BBQ</h1><p>Greensite Compound, Sta. Lucia, Pasig City</p><h2>Your order is completed</h2>'
        . '<p>Receipt: <strong>' . e($receipt['receipt_number']) . '</strong><br>Order: ' . e($receipt['order_number'])
        . '<br>Date: ' . e($date) . '<br>Customer: ' . e($name) . '</p>'
        . '<table style="width:100%;border-collapse:collapse"><thead><tr><th align="left">Item</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead><tbody>'
        . $rows . '</tbody></table><h2>Total: ' . e(peso($receipt['total'])) . '</h2><p>Payment method: '
        . e($receipt['payment_method']) . '</p><p>Thank you for supporting BulkBites BBQ!</p></div></body></html>';
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
            o.order_number, u.full_name, c.display_name
            FROM receipts r JOIN orders o ON o.id = r.order_id
            JOIN users u ON u.id = o.customer_id LEFT JOIN customers c ON c.user_id = u.id
            WHERE o.id = ? AND o.status = "COMPLETED"');
        $stmt->execute([$orderId]);
        $receipt = $stmt->fetch();
        if (!$receipt) return ['ok' => false, 'status' => 'PENDING'];
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
