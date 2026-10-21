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
function place_order_transaction(int $customerId, string $paymentMethod, string $notes, ?string $pickupName = null, string $pickupContact = '', string $fulfillmentType = 'PICKUP', string $deliveryAddress = ''): array
{
    $pdo = db();
    $cart = cart_raw();
    ksort($cart, SORT_NUMERIC); // Consistent inventory lock order across checkouts.
    $failRetry = static function (string $message, bool $retry = false): array {
        return ['ok' => false, 'retry' => $retry, 'message' => $message];
    };

    try {
        $pdo->beginTransaction();

        $settings=store_settings(true);
        if (!store_is_open($settings)) { throw new RuntimeException(store_status_text($settings) . '. Your cart is saved; please try again when ordering opens.'); }
        // Lock this account before counting orders: separate devices cannot bypass its limits.
        $account=$pdo->prepare('SELECT role,is_active FROM users WHERE id=? FOR UPDATE');$account->execute([$customerId]);$account=$account->fetch();
        if (!$account || $account['role']!=='CUSTOMER' || !(int)$account['is_active']) { throw new RuntimeException('An active customer account is required.'); }
        $active=$pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id=? AND status IN ("NEW","ACCEPTED","PREPARING","READY")');$active->execute([$customerId]);
        if ((int)$active->fetchColumn()>=(int)$settings['max_active_orders']) { throw new RuntimeException('You already have an active order. Check My orders before placing another.'); }
        $latest=$pdo->prepare('SELECT MAX(created_at) FROM orders WHERE customer_id=?');$latest->execute([$customerId]);$last=$latest->fetchColumn();
        if ($last && time()-strtotime($last)<(int)$settings['cooldown_seconds']) { throw new RuntimeException('Please wait ' . ((int)$settings['cooldown_seconds']-max(0,time()-strtotime($last))) . ' seconds before placing another order.'); }
        [$pickupName,$pickupContact]=pickup_details($pickupName,$pickupContact,$customerId);
        [$fulfillmentType,$deliveryAddress,$feeCentavos]=fulfillment_details($fulfillmentType,$deliveryAddress,$pickupContact);
        if (array_sum(array_map('intval',$cart))>(int)$settings['max_order_qty']) { throw new RuntimeException('Limit ' . $settings['max_order_qty'] . ' pieces per order. Contact the shop for larger orders.'); }

        // ---- 1. Validate every line against MySQL, locking inventory rows ----
        $lines = [];
        $subtotalCentavos = 0;

        foreach ($cart as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($qty < 1) {
                throw new RuntimeException('Invalid quantity in your cart.');
            }
            if ($qty>(int)$settings['max_item_qty']) { throw new RuntimeException('Limit ' . $settings['max_item_qty'] . ' pieces of each item per order.'); }

            $stmt = $pdo->prepare('SELECT p.id, p.name, p.price, p.is_active, c.is_active AS category_active FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id = ?');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product || !(int) $product['is_active'] || !(int) $product['category_active']) {
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
            $price = (string) $product['price'];
            $lines[] = [
                'id' => $productId,
                'name' => $product['name'],
                'price' => $price,
                'qty' => $qty,
                'stock_before' => (int) $stock['quantity_available'],
                'threshold'    => (int) $stock['low_stock_threshold'],
                'available'    => (int) $stock['is_available'],
            ];
            $subtotalCentavos += money_centavos($price) * $qty;
        }

        if (!$lines) {
            throw new RuntimeException('Your cart is empty.');
        }

        // ---- 2. Order number + order row (server-computed totals) ----
        $orderNumber = next_order_number($pdo);
        $subtotal = centavos_decimal($subtotalCentavos);
        $total = centavos_decimal($subtotalCentavos + $feeCentavos);
        $minutes = max(1, min(120, (int) config('app.' . ($paymentMethod === 'CASH' ? 'cash' : 'online') . '_reservation_minutes', $paymentMethod === 'CASH' ? 15 : 10)));
        $expires = date('Y-m-d H:i:s', time() + $minutes * 60);
        $stmt = $pdo->prepare(
            'INSERT INTO orders (order_number, customer_id, status, subtotal, total, payment_method, notes, payment_status, stock_state, reservation_expires_at)
             VALUES (?, ?, "NEW", ?, ?, ?, ?, ?, "RESERVED", ?)'
        );
        $stmt->execute([$orderNumber, $customerId, $subtotal, $total, $paymentMethod, $notes, $paymentMethod === 'CASH' ? 'UNPAID' : 'PENDING', $expires]);
        $orderId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE orders SET pickup_name=?,pickup_contact=?,pickup_location=?,fulfillment_type=?,delivery_address=?,delivery_fee=? WHERE id=?')->execute([$pickupName,$pickupContact,$settings['pickup_location'],$fulfillmentType,$deliveryAddress,centavos_decimal($feeCentavos),$orderId]);
        if ($paymentMethod==='DEMO') { $pdo->prepare('UPDATE orders SET payment_is_test=1 WHERE id=?')->execute([$orderId]); }

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
            $lineTotal = centavos_decimal(money_centavos($line['price']) * $line['qty']);
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
        $stmt->execute([$orderId, $receiptNumber, $total, $paymentMethod]);

        // ---- 5. Notifications: customer + every active staff/manager (step 8) ----
        // Same PDO, same transaction: a ROLLBACK leaves no phantom rows.
        notify_new_order(
            ['id' => $orderId, 'order_number' => $orderNumber, 'customer_id' => $customerId, 'total' => $total, 'payment_method' => $paymentMethod],
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
        if ($e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
            return $failRetry('Stock is being updated. Retrying checkout.', true);
        }
        if (($e instanceof RuntimeException || $e instanceof InvalidArgumentException) && !$e instanceof PDOException) {
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
function place_order(int $customerId, string $paymentMethod, string $notes, ?string $pickupName = null, string $pickupContact = '', string $fulfillmentType = 'PICKUP', string $deliveryAddress = ''): array
{
    expire_order_reservations();
    $cart = cart_raw();
    if (!$cart) {
        return ['ok' => false, 'message' => 'Your cart is empty.'];
    }
    if (!in_array($paymentMethod, ['CASH', 'GCASH', 'DEMO'], true)) {
        return ['ok' => false, 'message' => 'Please choose a valid payment method.'];
    }
    $notes = mb_substr(trim($notes), 0, 255);
    if ($paymentMethod==='DEMO' && !demo_payment_enabled()) { return ['ok'=>false,'message'=>'Demo payments are disabled. Choose another payment method.']; }
    if ($paymentMethod === 'GCASH' && !paymongo_ready()
        && !(PHP_SAPI === 'cli' && config('app.online_payments_enabled', false))) {
        return ['ok' => false, 'message' => 'Online checkout is not connected yet. Please choose cash at pickup.'];
    }

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $result = place_order_transaction($customerId, $paymentMethod, $notes, $pickupName, $pickupContact, $fulfillmentType, $deliveryAddress);
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

/** Only actionable new orders should warn staff about delayed acceptance. */
function order_acceptance_wait_text(array $order): string
{
    if (!in_array('ACCEPTED', order_available_actions($order), true)) return '';
    $created = strtotime((string)($order['created_at'] ?? ''));
    if ($created === false) return '';
    $seconds = max(0, time() - $created);
    return $seconds >= 300 ? 'Waiting ' . intdiv($seconds, 60) . ' min for acceptance' : '';
}

/** Button label for a target status (KDS + order-queue actions). */
function order_status_action_label(string $target, array $order = []): string
{
    switch ($target) {
        case 'ACCEPTED':  return 'Accept order';
        case 'PREPARING': return 'Start preparing';
        case 'READY':     return 'Mark ready';
        case 'COMPLETED': return $order ? (is_delivery($order) ? 'Confirm delivered' : 'Complete pickup') : 'Complete order';
        case 'CANCELLED': return 'Cancel order';
        case 'COLLECT_CASH': return 'Confirm cash received';
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
