<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') { http_response_code(405);header('Allow: GET');exit(json_encode(['ok'=>false])); }
$user=current_user();
if (!$user) { http_response_code(401);exit(json_encode(['ok'=>false,'message'=>'Sign in again to see updates.'])); }
if ($user['role']!=='CUSTOMER') { http_response_code(403);exit(json_encode(['ok'=>false])); }
$ref=is_string($_GET['ref'] ?? null)?trim($_GET['ref']):'';
expire_order_reservations();
$order=find_customer_order($ref,(int)$user['id']);
if (!$order) { http_response_code(404);exit(json_encode(['ok'=>false,'message'=>'Order not found.'])); }
echo json_encode(['ok'=>true,'revision'=>customer_order_revision($order)]);
