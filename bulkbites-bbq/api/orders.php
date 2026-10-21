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
    if (!str_starts_with($returnTo, 'staff/') && !($returnTo === 'manager/dashboard.php' && $user['role'] === 'MANAGER')) $returnTo = 'staff/kitchen.php';
    $known     = array_merge(array_keys(order_status_transitions()), ['COLLECT_CASH','RECORD_REFUND','SET_ESTIMATE']);

    $fail = function (string $message, int $code = 422) use ($returnTo): void {
        if (is_ajax()) {
            http_response_code($code);
            exit(json_encode(['ok' => false, 'message' => $message]));
        }
        flash('error', $message);
        redirect(url($returnTo));
    };

    if ($orderId < 1 || !in_array($action, $known, true)) {
        $fail('Unknown order action.');
    }

    $result = match ($action) {
        'SET_ESTIMATE' => set_order_estimate($orderId, (int)$user['id'], (int)($_POST['minutes'] ?? 0)),
        'COLLECT_CASH' => collect_cash_payment($orderId, (int) $user['id']),
        'RECORD_REFUND' => record_order_refund($orderId, (int) $user['id'], trim((string) ($_POST['reference'] ?? ''))),
        default => advance_order_status($orderId, $action, (int) $user['id']),
    };
    if (!$result['ok']) {
        $fail($result['message']);
    }
    $message = $result['message'];
    if (($result['new_status'] ?? '') === 'COMPLETED') {
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
            'status'  => $result['new_status'] ?? null,
            'old_status' => $result['old_status'] ?? null,
        ]));
    }
    flash('success', $message);
    redirect(url($returnTo));
}

// --- GET: queue payload -------------------------------------------------------
expire_order_reservations();
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
        'payment_label' => order_payment_prefix($order) . payment_status_label($order['payment_status'], $order['status'], $order['fulfillment_type'] ?? 'PICKUP'),
        'actions' => order_available_actions($order),
        'acceptance_wait' => order_acceptance_wait_text($order),
        'notes'         => (string) $order['notes'],
        'customer'      => (string) $order['customer_label'],
        'pickup_contact' => (string) $order['pickup_contact'],
        'fulfillment_type' => $order['fulfillment_type'],
        'delivery_address' => $order['delivery_address'],
        'delivery_fee' => $order['delivery_fee'],
        'estimate' => order_estimate_text($order),
        'created_at'    => $order['created_at'],
        'age_seconds'   => (int) $order['age_seconds'],
        'items'         => $items,
    ];
}

$summary = $user['role'] === 'MANAGER' ? manager_dashboard_stats() : null;
$ordering = store_settings();
echo json_encode([
    'ok'     => true,
    'counts' => staff_order_counts(),
    'orders' => $queue,
    'summary' => $summary === null ? null : array_intersect_key($summary, array_flip(['orders_today','active_now','sales_today','completed_today','cancelled_today'])),
    'ordering' => ['status'=>$ordering['ordering_status'], 'label'=>store_status_text($ordering)],
    'low_stock' => array_map(static fn($row) => ['name'=>$row['name'],'quantity'=>(int)$row['quantity_available']], staff_low_stock()),
]);
