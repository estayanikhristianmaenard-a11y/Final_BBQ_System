<?php
/** Runs inside baseline.php's disposable database; never sends external mail. */
if (PHP_SAPI !== 'cli' || !isset($pdo, $customer, $category, $name) || !preg_match('/^bulkbites_baseline_[a-f0-9]{12}$/D', $name)) { exit(1); }
$pdo->exec('UPDATE store_settings SET use_hours=0,ordering_status="OPEN",max_active_orders=1000,cooldown_seconds=0');
$pdo->prepare('INSERT INTO products (category_id,name,price) VALUES (?,"Delivery test BBQ",30.00)')->execute([$category]);
$deliveryProduct = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO inventory (product_id,quantity_available,is_available) VALUES (?,30,1)')->execute([$deliveryProduct]);
$deliveryStock = static fn(): int => (int) $pdo->query('SELECT quantity_available FROM inventory WHERE product_id='.$deliveryProduct)->fetchColumn();
foreach ([['DELIVERY','','09171234567'],['DELIVERY','12 Test Street, Pasig City',''],['OTHER','12 Test Street, Pasig City','09171234567'],['DELIVERY','12 Test Street, Pasig City','invalid']] as [$type,$address,$phone]) {
    $_SESSION['cart']=[$deliveryProduct=>2];
    $invalid=place_order($customer,'CASH','', 'Demo Customer',$phone,$type,$address);
    baseline_check(!$invalid['ok'] && $deliveryStock()===30 && cart_raw()===[$deliveryProduct=>2], 'Invalid delivery details leave stock and cart unchanged: '.$type.' / '.$phone);
}
$_SESSION['cart']=[$deliveryProduct=>2];
$pickup=place_order($customer,'CASH','','Demo Customer','','PICKUP','Discard this address');
$pickupRow=locked_order($pdo,(int)$pickup['order_id']);
baseline_check($pickup['ok'] && $pickupRow['total']==='60.00' && $pickupRow['delivery_fee']==='0.00' && $pickupRow['delivery_address']==='', 'Pickup stays free and discards delivery address');
$_SESSION['cart']=[$deliveryProduct=>2];
$_POST['delivery_fee']='0'; $_POST['total']='1';
$delivery=place_order($customer,'CASH','','Demo Customer','09171234567','DELIVERY',"12 Test Street\nBarangay Test, Pasig City");
unset($_POST['delivery_fee'],$_POST['total']);
$deliveryId=(int)$delivery['order_id'];$row=locked_order($pdo,$deliveryId);
baseline_check($delivery['ok'] && $row['subtotal']==='60.00' && $row['delivery_fee']==='20.00' && $row['total']==='80.00', 'Delivery adds authoritative fixed fee despite spoofed browser totals');
baseline_check($pdo->query('SELECT total FROM receipts WHERE order_id='.$deliveryId)->fetchColumn()==='80.00', 'Receipt total includes delivery fee');
baseline_check(!advance_order_status($deliveryId,'COMPLETED',$manager)['ok'], 'Delivery cannot skip preparation or payment');
foreach (['ACCEPTED','PREPARING','READY'] as $status) { baseline_check(advance_order_status($deliveryId,$status,$manager)['ok'], 'Delivery advances to '.$status); }
baseline_check(order_estimate_text(locked_order($pdo,$deliveryId))==='Ready for delivery', 'Delivery Ready message does not instruct pickup');
baseline_check(!advance_order_status($deliveryId,'COMPLETED',$manager)['ok'], 'Unpaid cash delivery cannot be completed');
baseline_check(collect_cash_payment($deliveryId,$manager)['ok'] && advance_order_status($deliveryId,'COMPLETED',$manager)['ok'], 'Cash delivery completes after cash is received');
$content=receipt_email_content(array_merge($row,['receipt_number'=>'RC-TEST','issued_at'=>date('Y-m-d H:i:s'),'full_name'=>'Demo Customer','display_name'=>'Demo Customer']),order_items($deliveryId));
baseline_check(str_contains($content['text'],'Delivery fee: '.peso(20)) && str_contains($content['html'],'12 Test Street') && str_contains($content['text'],peso(80)), 'Email receipt shows address, fee and full total');
baseline_check(payment_status_label('UNPAID','READY','DELIVERY')==='Unpaid - cash on delivery', 'Delivery cash guidance says cash on delivery');
$GLOBALS['BB_CONFIG']['app']['demo_payments_enabled']=true;
$_SESSION['cart']=[$deliveryProduct=>1];
$demoDelivery=place_order($customer,'DEMO','','Demo Customer','09171234567','DELIVERY','12 Test Street, Pasig City');
baseline_check($demoDelivery['ok'] && simulate_order_payment((int)$demoDelivery['order_id'],$customer,'success')['ok'] && $pdo->query('SELECT amount FROM payment_events WHERE order_id='.(int)$demoDelivery['order_id'])->fetchColumn()==='50.00', 'Demo payment records food plus delivery fee');
$_SESSION['cart']=[$deliveryProduct=>1];
$onlineDelivery=place_order($customer,'GCASH','','Demo Customer','09171234567','DELIVERY','12 Test Street, Pasig City');
$onlineDeliveryId=(int)$onlineDelivery['order_id'];
baseline_check(!record_verified_online_payment($onlineDeliveryId,'delivery-short',3000,'PHP',true)['ok'], 'Online delivery rejects payment missing delivery fee');
baseline_check(record_verified_online_payment($onlineDeliveryId,'delivery-full',5000,'PHP',true)['ok'], 'Online delivery accepts exact full amount including fee');
baseline_check(cancel_customer_order($onlineDeliveryId,$customer)['ok'] && $pdo->query('SELECT payment_status FROM orders WHERE id='.$onlineDeliveryId)->fetchColumn()==='REFUND_REQUIRED', 'Paid delivery cancellation keeps refund workflow');

$_SESSION['cart']=[$deliveryProduct=>1];
$gatewayDelivery=place_order($customer,'GCASH','','Demo Customer','09171234567','DELIVERY','12 Test Street, Pasig City');
$GLOBALS['BB_CONFIG']['paymongo']=['enabled'=>true,'secret_key'=>'sk_test_fixture','webhook_secret'=>'fixture-signing-secret-only','public_base_url'=>'https://demo.example.test/bbq'];
$gatewayFeeSeen=false;
$gatewayResult=paymongo_create_checkout((int)$gatewayDelivery['order_id'],$customer,static function(string $method,string $path,?array $payload) use (&$gatewayFeeSeen): array {
    $lines=$payload['data']['attributes']['line_items'];
    $gatewayFeeSeen=count($lines)===2 && $lines[1]['name']==='Delivery fee' && $lines[1]['amount']===2000 && array_sum(array_map(static fn($line)=>$line['amount']*$line['quantity'],$lines))===5000;
    return ['data'=>['id'=>'cs_deliveryfixture','type'=>'checkout_session','attributes'=>['livemode'=>false,'checkout_url'=>'https://checkout.paymongo.com/deliveryfixture']]];
});
baseline_check($gatewayResult['ok'] && $gatewayFeeSeen, 'Hosted payment payload includes separate fixed fee and exact total');
