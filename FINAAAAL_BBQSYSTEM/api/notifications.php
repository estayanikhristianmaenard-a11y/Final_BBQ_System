<?php
/**
 * Polling endpoint (architecture §10): returns the session user's unread
 * count + latest rows as JSON. A user can only ever read their own rows;
 * guests get a JSON 401 (require_login() detects AJAX via Accept/XHR headers).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_login();

$user = current_user();
$userId = (int) $user['id'];

$latest = [];
foreach (list_notifications($userId, 10) as $n) {
    $latest[] = [
        'id'           => (int) $n['id'],
        'type'         => $n['type'],
        'title'        => $n['title'],
        'message'      => $n['message'],
        'is_read'      => (int) $n['is_read'],
        'order_number' => $n['order_number'],
        'created_at'   => $n['created_at'],
    ];
}

echo json_encode([
    'ok'     => true,
    'unread' => unread_notification_count($userId),
    'latest' => $latest,
]);
