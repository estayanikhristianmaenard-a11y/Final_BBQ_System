<?php
/**
 * Orders API (architecture §5/§8) — STAFF + MANAGER only, HTTP 403 JSON on
 * violation for guests/others. Same session auth as the pages.
 *
 *   GET  → active kitchen queue (orders + items + age) and header counts
 *   POST → advance one order's status through advance_order_status()
 *          (CSRF-checked; JSON for AJAX, flash+redirect for plain forms)
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// --- Authz gates (always JSON, per architecture §3) ---------------------------
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Login required']));
}
$user = current_user();
if (!$user || !in_array($user['role'], ['STAFF', 'MANAGER'], true)) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Staff access only']));
}

// --- POST: status action ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $orderId   = (int) ($_POST['order_id'] ?? 0);
    $action    = strtoupper((string) ($_POST['action'] ?? ''));
    $returnTo  = (string) ($_POST['return'] ?? 'staff/kitchen.php');
    $known     = array_keys(order_status_transitions());

    $fail = function (string $message, int $code = 422) use ($returnTo): void {
        if (is_ajax()) {
            http_response_code($code);
            exit(json_encode(['ok' => false, 'message' => $message]));
        }
        flash('error', $message);
        redirect(url(str_starts_with($returnTo, 'staff/') ? $returnTo : 'staff/kitchen.php'));
    };

    if ($orderId < 1 || !in_array($action, $known, true)) {
        $fail('Unknown order action.');
    }

    $result = advance_order_status($orderId, $action, (int) $user['id']);
    if (!$result['ok']) {
        $fail($result['message']);
    }
    $message = 'Order is now ' . order_status_label($result['new_status']) . '.';
    if ($result['new_status'] === 'COMPLETED') {
        $message .= match ($result['receipt_email_status'] ?? 'PENDING') {
            'SENT' => ' The receipt was emailed to the customer.',
            'SKIPPED' => ' This older customer account has no verified email for the receipt.',
            default => ' The receipt email is pending and will be retried.',
        };
    }

    if (is_ajax()) {
        exit(json_encode([
            'ok'     => true,
            'message' => $message,
            'status'  => $result['new_status'],
            'old_status' => $result['old_status'],
        ]));
    }
    flash('success', $message);
    redirect(url(str_starts_with($returnTo, 'staff/') ? $returnTo : 'staff/kitchen.php'));
}

// --- GET: queue payload -------------------------------------------------------
retry_receipt_emails();
$queue = [];
foreach (staff_active_queue() as $order) {
    $items = [];
    foreach ($order['items'] as $item) {
        $items[] = [
            'name'      => $item['product_name'],
            'qty'       => (int) $item['quantity'],
            'unit_price' => $item['unit_price'],
            'line_total' => $item['line_total'],
        ];
    }
    $queue[] = [
        'id'            => (int) $order['id'],
        'order_number'  => $order['order_number'],
        'status'        => $order['status'],
        'total'         => $order['total'],
        'payment_method' => $order['payment_method'],
        'notes'         => (string) $order['notes'],
        'customer'      => (string) $order['customer_label'],
        'created_at'    => $order['created_at'],
        'age_seconds'   => (int) $order['age_seconds'],
        'items'         => $items,
    ];
}

echo json_encode([
    'ok'     => true,
    'counts' => staff_order_counts(),
    'orders' => $queue,
]);
