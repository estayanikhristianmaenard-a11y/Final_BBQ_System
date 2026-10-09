<?php
/**
 * Checkout core — Phase 4.
 *
 * place_order() runs the ENTIRE checkout inside one InnoDB transaction:
 * validate → lock stock → authoritative prices → order → items → deduct →
 * movements → status history → COMMIT, or ROLLBACK on any failure.
 * The browser sends only payment/notes; prices, stock, and quantities are
 * always read from MySQL (the session cart holds product IDs and quantities).
 */

function order_number_prefix(): string
{
    return 'BB-' . date('ymd') . '-';
}

/**
 * Next sequential order number for today: BB-YYMMDD-0001.
 * The UNIQUE constraint on orders.order_number is the final guard; a
 * duplicate-key collision makes place_order() retry in a fresh transaction.
 */
function next_order_number(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_number LIKE ?');
    $stmt->execute([order_number_prefix() . '%']);
    $seq = (int) $stmt->fetchColumn() + 1;
    return order_number_prefix() . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Receipt number prefix: RC-YYMMDD- (architecture §11). */
function receipt_number_prefix(): string
{
    return 'RC-' . date('ymd') . '-';
}

/** Next sequential receipt number for today: RC-YYMMDD-0001 (unique-guarded). */
function next_receipt_number(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE receipt_number LIKE ?');
    $stmt->execute([receipt_number_prefix() . '%']);
    $seq = (int) $stmt->fetchColumn() + 1;
    return receipt_number_prefix() . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Attempt the checkout as a single transaction.
 *
 * @return array{ok: bool, retry: bool, message: string, order_number?: string, order_id?: int}
 */
function place_order_transaction(int $customerId, string $paymentMethod, string $notes): array
{
    $pdo = db();
    $cart = cart_raw();
    $failRetry = static function (string $message, bool $retry = false): array {
        return ['ok' => false, 'retry' => $retry, 'message' => $message];
    };

    try {
        $pdo->beginTransaction();

        // ---- 1. Validate every line against MySQL, locking inventory rows ----
        $lines = [];
        $subtotal = 0.0;

        foreach ($cart as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($qty < 1) {
                throw new RuntimeException('Invalid quantity in your cart.');
            }

            $stmt = $pdo->prepare('SELECT id, name, price, is_active FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product || !(int) $product['is_active']) {
                throw new RuntimeException('A product in your cart is no longer available.');
            }

            // Row lock: serializes concurrent checkouts on the same product
            $stmt = $pdo->prepare('SELECT quantity_available, is_available, low_stock_threshold FROM inventory WHERE product_id = ? FOR UPDATE');
            $stmt->execute([$productId]);
            $stock = $stmt->fetch();
            if (!$stock || !(int) $stock['is_available']) {
                throw new RuntimeException($product['name'] . ' is sold out right now.');
            }
            if ((int) $stock['quantity_available'] < $qty) {
                throw new RuntimeException(
                    'Insufficient stock for ' . $product['name'] . ': only '
                    . (int) $stock['quantity_available'] . ' left. Please adjust your cart.'
                );
            }

            // AUTHORITATIVE price — any price sent by the browser is ignored
            $price = (float) $product['price'];
            $lines[] = [
                'id' => $productId,
                'name' => $product['name'],
                'price' => $price,
                'qty' => $qty,
                'stock_before' => (int) $stock['quantity_available'],
                'threshold'    => (int) $stock['low_stock_threshold'],
                'available'    => (int) $stock['is_available'],
            ];
            $subtotal += $price * $qty;
        }

        if (!$lines) {
            throw new RuntimeException('Your cart is empty.');
        }

        // ---- 2. Order number + order row (server-computed totals) ----
        $orderNumber = next_order_number($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO orders (order_number, customer_id, status, subtotal, total, payment_method, notes)
             VALUES (?, ?, "NEW", ?, ?, ?, ?)'
        );
        $stmt->execute([$orderNumber, $customerId, $subtotal, $subtotal, $paymentMethod, $notes]);
        $orderId = (int) $pdo->lastInsertId();

        // ---- 3. Items (price/name snapshots) + guarded stock deduction ----
        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $deductStmt = $pdo->prepare(
            'UPDATE inventory SET quantity_available = quantity_available - ?
             WHERE product_id = ? AND quantity_available >= ?'
        );
        $movementStmt = $pdo->prepare(
            'INSERT INTO inventory_movements (product_id, movement_type, quantity_change, reference_order_id, performed_by, remarks)
             VALUES (?, "ORDER_DEDUCT", ?, ?, NULL, ?)'
        );

        foreach ($lines as $line) {
            $lineTotal = $line['price'] * $line['qty'];
            $itemStmt->execute([$orderId, $line['id'], $line['name'], $line['price'], $line['qty'], $lineTotal]);

            $deductStmt->execute([$line['qty'], $line['id'], $line['qty']]);
            if ($deductStmt->rowCount() !== 1) {
                throw new RuntimeException('Stock for ' . $line['name'] . ' changed while checking out. Please try again.');
            }
            $movementStmt->execute([$line['id'], -$line['qty'], $orderId, 'Order ' . $orderNumber]);

            // ---- LOW_STOCK alert when this deduction crosses the threshold (architecture §10) ----
            $qtyAfter = $line['stock_before'] - $line['qty'];
            if (stock_state($line['stock_before'], $line['threshold'], $line['available']) === 'IN'
                && stock_state($qtyAfter, $line['threshold'], $line['available']) !== 'IN') {
                $alertLabel = stock_state($qtyAfter, $line['threshold'], $line['available']) === 'OUT'
                    ? 'OUT OF STOCK' : 'LOW STOCK';
                $alertStmt = $pdo->query("SELECT id FROM users WHERE role = 'MANAGER' AND is_active = 1");
                foreach ($alertStmt->fetchAll(PDO::FETCH_COLUMN) as $managerId) {
                    notify_user(
                        (int) $managerId,
                        $orderId,
                        'LOW_STOCK',
                        $alertLabel . ': ' . $line['name'],
                        $line['name'] . ' dropped to ' . $qtyAfter . ' pcs after order ' . $orderNumber
                        . ' (minimum ' . $line['threshold'] . ').'
                    );
                }
            }
        }

        // ---- 4. Receipt (one per order, architecture step 7) ----
        $receiptNumber = next_receipt_number($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO receipts (order_id, receipt_number, total, payment_method)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, $receiptNumber, $subtotal, $paymentMethod]);

        // ---- 5. Notifications: customer + every active staff/manager (step 8) ----
        // Same PDO, same transaction: a ROLLBACK leaves no phantom rows.
        notify_new_order(
            ['id' => $orderId, 'order_number' => $orderNumber, 'customer_id' => $customerId, 'total' => $subtotal, 'payment_method' => $paymentMethod],
            count($lines)
        );

        // ---- 6. Status history: first entry (step 9) ----
        $stmt = $pdo->prepare(
            'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, remarks)
             VALUES (?, NULL, "NEW", NULL, "Order placed")'
        );
        $stmt->execute([$orderId]);

        $pdo->commit();
        return ['ok' => true, 'retry' => false, 'message' => 'Order placed.', 'order_number' => $orderNumber, 'order_id' => $orderId, 'receipt_number' => $receiptNumber];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Duplicate order/receipt number under a concurrency race → caller retries
        $msg = $e->getMessage();
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062
            && (str_contains($msg, 'uq_orders_number') || str_contains($msg, 'uq_receipts_number'))) {
            return $failRetry('Order number collision.', true);
        }
        if ($e instanceof RuntimeException) {
            return $failRetry($e->getMessage());
        }
        if (config('app.debug')) {
            return $failRetry('Checkout failed: ' . $e->getMessage());
        }
        return $failRetry('Could not place your order right now. Please try again.');
    }
}

/**
 * Public checkout entry point: validates input, retries once on an order-number
 * collision, and clears the cart only after a successful COMMIT.
 *
 * @return array{ok: bool, message: string, order_number?: string}
 */
function place_order(int $customerId, string $paymentMethod, string $notes): array
{
    $cart = cart_raw();
    if (!$cart) {
        return ['ok' => false, 'message' => 'Your cart is empty.'];
    }
    if (!in_array($paymentMethod, ['CASH', 'GCASH'], true)) {
        return ['ok' => false, 'message' => 'Please choose a valid payment method.'];
    }
    $notes = mb_substr(trim($notes), 0, 255);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $result = place_order_transaction($customerId, $paymentMethod, $notes);
        if ($result['ok']) {
            cart_clear();
            return $result;
        }
        if (empty($result['retry'])) {
            return $result; // validation/stock failure — cart untouched, DB rolled back
        }
    }
    return ['ok' => false, 'message' => 'Could not assign an order number. Please try again.'];
}

/* ------------------------------------------------------------------ *
 * Display helpers: status labels/colors and order queries
 * ------------------------------------------------------------------ */

function order_status_label(string $status): string
{
    switch ($status) {
        case 'NEW':       return 'New';
        case 'ACCEPTED':  return 'Accepted';
        case 'PREPARING': return 'Preparing';
        case 'READY':     return 'Ready';
        case 'COMPLETED': return 'Completed';
        case 'CANCELLED': return 'Cancelled';
        default:          return ucfirst(strtolower($status));
    }
}

function order_status_class(string $status): string
{
    switch ($status) {
        case 'NEW':       return 'st-new';
        case 'ACCEPTED':  return 'st-accepted';
        case 'PREPARING': return 'st-preparing';
        case 'READY':     return 'st-ready';
        case 'COMPLETED': return 'st-completed';
        case 'CANCELLED': return 'st-cancelled';
        default:          return 'st-new';
    }
}

/** Button label for a target status (KDS + order-queue actions). */
function order_status_action_label(string $target): string
{
    switch ($target) {
        case 'ACCEPTED':  return 'Accept order';
        case 'PREPARING': return 'Start preparing';
        case 'READY':     return 'Mark ready';
        case 'COMPLETED': return 'Complete order';
        case 'CANCELLED': return 'Cancel order';
        default:          return 'Set ' . order_status_label($target);
    }
}

/** Status badge HTML (escaped). */
function order_status_badge(string $status): string
{
    return '<span class="badge status ' . e(order_status_class($status)) . '">'
        . e(order_status_label($status)) . '</span>';
}

/** One customer order by order number, or null (ownership enforced). */
function find_customer_order(string $orderNumber, int $customerId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? AND customer_id = ?');
    $stmt->execute([$orderNumber, $customerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Order lines for one order. */
function order_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/** Status timeline entries (oldest first) with the staff member's name. */
function order_history(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT h.*, u.full_name AS changed_by_name
         FROM order_status_history h
         LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.order_id = ?
         ORDER BY h.created_at, h.id'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}
