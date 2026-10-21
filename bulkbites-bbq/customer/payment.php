<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
csrf_verify();
if (!in_array($_POST['action'] ?? '', ['pay','check'], true)) { http_response_code(400); exit('Invalid payment action.'); }
$ref = trim((string) ($_POST['ref'] ?? ''));
$order = find_customer_order($ref, (int) current_user()['id']);
if (!$order) { http_response_code(404); exit('Order not found.'); }
if (!rate_limit_hit('paymongo_action', 'customer:' . current_user()['id'], 10, 60)) {
    flash('warning', 'Please wait a minute before checking payment again.');
    redirect(url('customer/order.php') . '?ref=' . rawurlencode($ref));
}
if (($_POST['action'] ?? '') === 'check') {
    $query = db()->prepare('SELECT session_id FROM paymongo_checkouts WHERE order_id = ?');
    $query->execute([$order['id']]);
    $session = $query->fetchColumn();
    $result = $session ? paymongo_reconcile_session($session) : ['ok' => false, 'message' => 'Checkout is not ready for verification yet. Contact the shop if it remains unavailable.'];
} else {
    $result = paymongo_create_checkout((int) $order['id'], (int) current_user()['id']);
    if ($result['ok'] && isset($result['checkout_url'])) { redirect($result['checkout_url']); }
}
flash($result['ok'] ? 'success' : 'warning', $result['message'] ?? 'Payment is waiting for verification.');
redirect(url('customer/order.php') . '?ref=' . rawurlencode($ref));
