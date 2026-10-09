<?php
/**
 * Session cart helpers — Phase 3 (UI/cart) feeding Phase 4 (checkout).
 *
 * The cart stores ONLY [product_id => quantity]. Prices, names, and stock are
 * always read from MySQL at render time, so nothing in the session can be
 * trusted as truth and no stock is deducted until checkout (Phase 4).
 */

function cart_raw(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

/** Total pieces across all cart lines (drives the header badge). */
function cart_count(): int
{
    $total = 0;
    foreach (cart_raw() as $qty) {
        $total += max(0, (int) $qty);
    }
    return $total;
}

/** Quantity of one product already in the cart (0 if absent). */
function cart_has(int $productId): int
{
    return (int) (cart_raw()[$productId] ?? 0);
}

/**
 * Fetch a product + stock row for cart operations (active only).
 * Returns null when the product does not exist or is deactivated.
 */
function cart_product_row(int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.price, p.image, p.is_active,
                i.quantity_available AS stock, i.is_available
         FROM products p
         JOIN inventory i ON i.product_id = p.id
         WHERE p.id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Add quantity to the cart, capped at available stock.
 *
 * @return array{ok: bool, message: string}
 */
function cart_add(int $productId, int $qty): array
{
    if ($qty < 1) {
        return ['ok' => false, 'message' => 'Choose at least 1 item.'];
    }

    $product = cart_product_row($productId);
    if (!$product || !(int) $product['is_active']) {
        return ['ok' => false, 'message' => 'That product is not available.'];
    }
    if (!(int) $product['is_available'] || (int) $product['stock'] < 1) {
        return ['ok' => false, 'message' => $product['name'] . ' is sold out right now.'];
    }

    $stock  = (int) $product['stock'];
    $current = cart_has($productId);
    $wanted  = $current + $qty;

    if ($current >= $stock) {
        return ['ok' => false, 'message' => 'All available stock (' . $stock . ') of ' . $product['name'] . ' is already in your cart.'];
    }
    if ($wanted > $stock) {
        $_SESSION['cart'][$productId] = $stock;
        return ['ok' => true, 'message' => $product['name'] . ' added — quantity limited to available stock (' . $stock . ').'];
    }

    $_SESSION['cart'][$productId] = $wanted;
    return ['ok' => true, 'message' => $qty . ' × ' . $product['name'] . ' added to your cart.'];
}

/**
 * Set an exact quantity for a cart line (clamped to stock).
 *
 * @return array{ok: bool, message: string}
 */
function cart_set_qty(int $productId, int $qty): array
{
    if (cart_has($productId) === 0) {
        return ['ok' => false, 'message' => 'That item is not in your cart.'];
    }
    if ($qty < 1) {
        return ['ok' => false, 'message' => 'Quantity must be at least 1. Use Remove to delete the item.'];
    }

    $product = cart_product_row($productId);
    if (!$product || !(int) $product['is_active'] || !(int) $product['is_available']) {
        unset($_SESSION['cart'][$productId]);
        return ['ok' => false, 'message' => 'Item removed because the product is no longer available.'];
    }

    $stock = (int) $product['stock'];
    if ($stock < 1) {
        return ['ok' => false, 'message' => $product['name'] . ' is sold out right now.'];
    }
    if ($qty > $stock) {
        $_SESSION['cart'][$productId] = $stock;
        return ['ok' => true, 'message' => 'Quantity limited to available stock (' . $stock . ').'];
    }

    $_SESSION['cart'][$productId] = $qty;
    return ['ok' => true, 'message' => 'Quantity updated.'];
}

function cart_remove(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

/** RESET ORDER — clears the whole cart (UI always confirms first). */
function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/**
 * Authoritative cart view: merges session quantities with live MySQL data.
 *
 * - missing product      → pruned from the session (notice)
 * - inactive/unavailable → kept but marked not buyable (excluded from subtotal)
 * - quantity > stock     → clamped down (notice)
 *
 * @return array{lines: array, notices: string[], items: int, subtotal: float, unavailable: int}
 */
function cart_view(): array
{
    $raw = cart_raw();
    if (!$raw) {
        return ['lines' => [], 'notices' => [], 'items' => 0, 'subtotal' => 0.0, 'unavailable' => 0];
    }

    $ids = array_map('intval', array_keys($raw));
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT p.id, p.name, p.price, p.image, p.is_active,
                i.quantity_available AS stock, i.is_available
         FROM products p
         JOIN inventory i ON i.product_id = p.id
         WHERE p.id IN ($ph)"
    );
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll() as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $lines = [];
    $notices = [];
    $items = 0;
    $subtotal = 0.0;
    $unavailable = 0;

    foreach ($raw as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        $product = $byId[$pid] ?? null;

        if (!$product) {
            unset($_SESSION['cart'][$pid]);
            $notices[] = 'An item was removed from your cart because it is no longer offered.';
            continue;
        }

        $buyable = true;
        $reason = '';
        if (!(int) $product['is_active'] || !(int) $product['is_available']) {
            $buyable = false;
            $reason = 'Unavailable';
        } elseif ((int) $product['stock'] < 1) {
            $buyable = false;
            $reason = 'Out of stock';
        } elseif ($qty > (int) $product['stock']) {
            $qty = (int) $product['stock'];
            $_SESSION['cart'][$pid] = $qty;
            $notices[] = $product['name'] . ' quantity was limited to available stock (' . $qty . ').';
        }

        if ($buyable) {
            $items += $qty;
            $subtotal += (float) $product['price'] * $qty;
        } else {
            $unavailable++;
        }

        $lines[] = [
            'id' => $pid,
            'name' => $product['name'],
            'price' => (float) $product['price'],
            'image' => $product['image'],
            'qty' => $qty,
            'stock' => (int) $product['stock'],
            'line_total' => (float) $product['price'] * $qty,
            'buyable' => $buyable,
            'reason' => $reason,
        ];
    }

    return [
        'lines' => $lines,
        'notices' => $notices,
        'items' => $items,
        'subtotal' => $subtotal,
        'unavailable' => $unavailable,
    ];
}
