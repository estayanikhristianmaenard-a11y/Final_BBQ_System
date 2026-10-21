<?php
require dirname(__DIR__).'/includes/bootstrap.php';
require_role('STAFF','MANAGER');
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { http_response_code(405);header('Allow: POST');exit; }
csrf_verify();
$status=is_string($_POST['ordering_status'] ?? null)?$_POST['ordering_status']:'';
$return=is_string($_POST['return'] ?? null)?$_POST['return']:'';
if (!in_array($return,['staff/dashboard.php','staff/kitchen.php','staff/orders.php','manager/dashboard.php'],true)) { $return='staff/dashboard.php'; }
if($return==='manager/dashboard.php' && current_user()['role']!=='MANAGER') { $return='staff/kitchen.php'; }
$result=set_ordering_status($status,(int)current_user()['id']);
if(is_ajax()) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); if(!$result['ok']) http_response_code(422); exit(json_encode($result)); }
flash($result['ok']?'success':'error',$result['message']);
redirect(url($return));
