<?php
/** Stock actions for the combined manager product page; bootstrap and role check precede this include. */
/** Notify other active managers when stock crosses from IN into LOW/OUT. */
function inventory_low_stock_alert(int $productId, string $beforeState, int $actorId): void
{
    $stmt = db()->prepare(
        'SELECT p.name, i.quantity_available, i.low_stock_threshold, i.is_available
         FROM inventory i JOIN products p ON p.id = i.product_id
         WHERE i.product_id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $afterState = stock_state((int) $row['quantity_available'], (int) $row['low_stock_threshold'], (int) $row['is_available']);
    // Alert only on a degradation: IN → LOW/OUT or LOW → OUT (never on improvement,
    // never repeatedly while already OUT).
    if ($afterState === 'IN' || $afterState === $beforeState || $beforeState === 'OUT') {
        return;
    }
    $managers = db()->query(
        "SELECT id FROM users WHERE role = 'MANAGER' AND is_active = 1"
    )->fetchAll(PDO::FETCH_COLUMN);
    $label = $afterState === 'OUT' ? 'OUT OF STOCK' : 'LOW STOCK';
    foreach ($managers as $managerId) {
        if ((int) $managerId === $actorId) {
            continue; // the acting manager already knows
        }
        notify_user(
            (int) $managerId,
            null,
            'LOW_STOCK',
            $label . ': ' . $row['name'],
            $row['name'] . ' is now ' . $afterState . ' — '
            . (int) $row['quantity_available'] . ' ' . 'left (minimum ' . (int) $row['low_stock_threshold'] . ').'
        );
    }
}

/** Read current row + derive state before an action. */
function inventory_row(int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT i.quantity_available, i.low_stock_threshold, i.is_available
         FROM inventory i WHERE i.product_id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['state'] = stock_state(
        (int) $row['quantity_available'],
        (int) $row['low_stock_threshold'],
        (int) $row['is_available']
    );
    return $row;
}

// --- POST actions (PRG) ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $productId = (int) ($_POST['product_id'] ?? 0);
    $back = url('manager/products.php') . '#stock';
    $pdo = db();

    if (in_array($action, ['restock', 'adjust'], true)) {
        $before = inventory_row($productId);
        if (!$before) {
            flash('error', 'Product not found.');
            redirect($back);
        }

        $qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>-999999,'max_range'=>999999]]);
        if ($qty === false || $qty === null) {
            flash('error', 'Enter a whole-number quantity between -999999 and 999999.');
            redirect($back);
        }
        $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 255);

        if ($qty === 0) {
            flash('error', 'Enter a non-zero quantity.');
            redirect($back);
        }
        if ($action === 'restock' && $qty < 0) {
            flash('error', 'Restock quantity must be positive.');
            redirect($back);
        }
        if ($action === 'adjust' && $qty > 0 && $remarks === '') {
            flash('error', 'Describe what the positive adjustment is for (e.g. “supplier correction”).');
            redirect($back);
        }

        $pdo->beginTransaction();
        try {
            // Guarded change: new balance can never go below zero
            $stmt = $pdo->prepare(
                'UPDATE inventory
                 SET quantity_available = quantity_available + ?,
                     updated_by = ?,
                     last_updated = NOW()
                 WHERE product_id = ? AND quantity_available + ? >= 0'
            );
            $stmt->execute([$qty, (int) $user['id'], $productId, $qty]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Stock is insufficient for that change (would go below zero).');
            }

            $type = $action === 'restock' ? 'RESTOCK' : 'ADJUSTMENT';
            $stmt = $pdo->prepare(
                'INSERT INTO inventory_movements (product_id, movement_type, quantity_change, performed_by, remarks)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$productId, $type, $qty, (int) $user['id'], $remarks !== '' ? $remarks : ($action === 'restock' ? 'Restock' : 'Adjustment')]);

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'Stock change failed — nothing was written.');
            redirect($back);
        }

        inventory_low_stock_alert($productId, $before['state'], (int) $user['id']);
        $after = inventory_row($productId);
        flash('success', 'Stock updated: ' . $before['quantity_available'] . ' → ' . $after['quantity_available'] . ' pcs.');
        redirect($back);
    }

    if ($action === 'threshold') {
        $before = inventory_row($productId);
        if (!$before) {
            flash('error', 'Product not found.');
            redirect($back);
        }
        $threshold = filter_var($_POST['low_stock_threshold'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>999999]]);
        if ($threshold === false || $threshold === null) {
            flash('error', 'Threshold must be a whole number between 0 and 999999.');
            redirect($back);
        }
        $stmt = $pdo->prepare('UPDATE inventory SET low_stock_threshold = ?, updated_by = ? WHERE product_id = ?');
        $stmt->execute([$threshold, (int) $user['id'], $productId]);
        inventory_low_stock_alert($productId, $before['state'], (int) $user['id']);
        flash($stmt->rowCount() > 0 ? 'success' : 'warning',
            $stmt->rowCount() > 0 ? 'Low-stock threshold set to ' . $threshold . '.' : 'Threshold unchanged.');
        redirect($back);
    }

    if ($action === 'toggle_availability') {
        $before = inventory_row($productId);
        if (!$before) {
            flash('error', 'Product not found.');
            redirect($back);
        }
        $stmt = $pdo->prepare(
            'UPDATE inventory SET is_available = 1 - is_available, updated_by = ? WHERE product_id = ?'
        );
        $stmt->execute([(int) $user['id'], $productId]);
        inventory_low_stock_alert($productId, $before['state'], (int) $user['id']);
        $after = inventory_row($productId);
        flash('success', ($after['is_available'] ? 'Marked available.' : 'Marked sold out.') . ' State is now ' . $after['state'] . '.');
        redirect($back);
    }

    flash('error', 'Unknown action.');
    redirect($back);
}

