<?php
/** Small-store settings and pickup rules, shared by the UI and transactional checkout. */
function store_settings(bool $lock = false): array
{
    $row = db()->query('SELECT * FROM store_settings WHERE id = 1' . ($lock ? ' LOCK IN SHARE MODE' : ''))->fetch();
    if (!$row) { throw new RuntimeException('Store setup is missing. Run UPGRADE-PAYMENTS.bat.'); }
    $mode = db()->query("SELECT setting_value FROM settings WHERE setting_key='ordering_manual_override'")->fetchColumn();
    $row['ordering_override'] = in_array($mode, ['OPEN','CLOSED'], true) ? $mode : 'AUTO';
    $row['pickup_location'] = preg_replace('/Pasig City[.,\s]*$/i', 'Pasig City, Philippines', $row['pickup_location']);
    return $row;
}
function store_is_open(array $settings, ?DateTimeImmutable $now = null): bool
{
    if ($settings['ordering_status'] !== 'OPEN') { return false; }
    if (($settings['ordering_override'] ?? 'AUTO') === 'OPEN') { return true; }
    if (($settings['ordering_override'] ?? 'AUTO') === 'CLOSED') { return false; }
    if (!(int) $settings['use_hours']) { return true; }
    $time = ($now ?? new DateTimeImmutable('now', new DateTimeZone(config('app.timezone', 'Asia/Manila'))))->format('H:i');
    $start = substr((string) $settings['opens_at'], 0, 5); $end = substr((string) $settings['closes_at'], 0, 5);
    if ($start === '' || $end === '' || $start === $end) { return false; }
    return $start < $end ? $time >= $start && $time < $end : $time >= $start || $time < $end;
}
function store_status_text(array $settings): string
{
    if (store_is_open($settings)) { return 'Open for orders'; }
    return $settings['ordering_status'] === 'PAUSED' ? 'Ordering is temporarily paused' : 'Closed for new orders';
}
function store_ordering_mode_text(array $settings): string
{
    return match ($settings['ordering_override'] ?? 'AUTO') {
        'OPEN'=>'Manual open. Ordering stays open until you close it or resume automatic hours.',
        'CLOSED'=>'Manually closed. Reopen ordering or resume automatic hours.',
        default=>(int)$settings['use_hours'] ? 'Automatic hours. Managers can override them with Open or Close ordering.' : 'Daily hours are disabled. Use Open or Close ordering.',
    };
}
function store_hours_text(array $settings): string
{
    if (!(int) $settings['use_hours']) { return ''; }
    return 'Open daily: ' . date('g:i A', strtotime($settings['opens_at'])) . '–' . date('g:i A', strtotime($settings['closes_at']));
}
function validate_store_settings(array $input): array
{
    $status = $input['ordering_status'] ?? '';
    if (!is_string($status) || !in_array($status, ['OPEN','PAUSED','CLOSED'], true)) { throw new InvalidArgumentException('Choose Open, Paused, or Closed.'); }
    $result = ['ordering_status'=>$status, 'use_hours'=>!empty($input['use_hours']) ? 1 : 0];
    foreach (['opens_at','closes_at'] as $key) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || ($value !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value))) { throw new InvalidArgumentException('Enter valid opening and closing times.'); }
        $result[$key] = $value === '' ? null : $value;
    }
    if ($result['use_hours'] && (!$result['opens_at'] || !$result['closes_at'] || $result['opens_at'] === $result['closes_at'])) { throw new InvalidArgumentException('Set different opening and closing times to enable daily hours.'); }
    foreach (['prep_minutes'=>[1,180], 'max_active_orders'=>[1,10], 'cooldown_seconds'=>[0,3600], 'max_item_qty'=>[1,250], 'max_order_qty'=>[1,1500]] as $key=>$range) {
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value === null || $value < $range[0] || $value > $range[1]) { throw new InvalidArgumentException('Enter valid preparation time and order limits.'); }
        $result[$key] = $value;
    }
    if ($result['max_item_qty'] > $result['max_order_qty']) { throw new InvalidArgumentException('The per-item limit cannot exceed the total order limit.'); }
    foreach (['pickup_location'=>200, 'contact_details'=>120, 'delivery_area'=>200] as $key=>$length) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) > $length || preg_match('/[\x00-\x1f\x7f]/', $value)) { throw new InvalidArgumentException('Enter valid shop details (delivery area: up to 200 characters).'); }
        $result[$key] = trim($value);
    }
    if ($result['pickup_location'] === '') { throw new InvalidArgumentException('Enter the pickup location.'); }
    return $result;
}
/** Manager actions override hours; staff actions continue to follow the schedule. */
function set_ordering_status(string $status, int $actor): array
{
    if (!in_array($status,['OPEN','CLOSED','AUTO'],true)) return ['ok'=>false,'message'=>'Choose Open, Close, or automatic hours.'];
    return order_transaction(static function (PDO $pdo) use ($status,$actor): array {
        require_payment_staff($pdo,$actor);
        $role=$pdo->prepare('SELECT role FROM users WHERE id=?');$role->execute([$actor]);
        $manager=$role->fetchColumn()==='MANAGER';
        if ($status==='AUTO' && !$manager) throw new RuntimeException('Only a manager can resume automatic hours.');
        $settings=$pdo->query('SELECT * FROM store_settings WHERE id=1 FOR UPDATE')->fetch();
        if (!$settings) throw new RuntimeException('Store settings are missing.');
        $mode=$manager && $status!=='AUTO' ? $status : 'AUTO';
        $enabled=$status==='AUTO' ? 'OPEN' : $status;
        $pdo->prepare('UPDATE store_settings SET ordering_status=?,updated_by=? WHERE id=1')->execute([$enabled,$actor]);
        $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES ("ordering_manual_override",?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$mode]);
        $settings['ordering_status']=$enabled;$settings['ordering_override']=$mode;
        $message=$status==='AUTO' ? 'Automatic opening hours resumed.' : ($status==='CLOSED'
            ? 'New ordering is closed. Existing orders stay available.'
            : (store_is_open($settings) ? 'New ordering is open.' : 'Ordering is enabled for the scheduled hours. The shop is currently outside opening hours.'));
        return ['ok'=>true,'message'=>$message];
    });
}
function save_store_settings(array $input, int $manager): array
{
    try { $values = validate_store_settings($input); }
    catch (InvalidArgumentException $error) { return ['ok'=>false,'message'=>$error->getMessage()]; }
    return order_transaction(static function (PDO $pdo) use ($values,$manager): array {
        require_payment_staff($pdo,$manager,true);
        $fields = array_map(static fn($key)=>$key . ' = ?', array_keys($values));
        $pdo->prepare('UPDATE store_settings SET ' . implode(', ', $fields) . ', updated_by = ? WHERE id = 1')->execute([...array_values($values),$manager]);
        $pdo->exec("DELETE FROM settings WHERE setting_key='ordering_manual_override'");
        return ['ok'=>true,'message'=>'Store settings saved. Manual hours override cleared.'];
    });
}
function pickup_details(?string $name, string $contact, int $customerId): array
{
    // Existing internal callers can use the account name; browser checkout supplies a required name.
    if ($name === null) { $query=db()->prepare('SELECT full_name FROM users WHERE id = ?');$query->execute([$customerId]);$name=(string)$query->fetchColumn(); }
    $name=trim($name); $contact=trim($contact);
    if (mb_strlen($name)<2 || mb_strlen($name)>80 || preg_match('/[\x00-\x1f\x7f]/',$name)) { throw new InvalidArgumentException('Enter a customer name between 2 and 80 characters.'); }
    $digits=preg_replace('/\D/','',$contact);
    if ($contact !== '' && (!preg_match('/^[+0-9 ()-]{7,30}$/D',$contact) || strlen($digits)<7 || strlen($digits)>15)) { throw new InvalidArgumentException('Enter a valid contact number or leave it blank.'); }
    return [$name,$contact];
}
function order_estimate_text(array $order): string
{
    if ($order['status'] === 'READY') { return is_delivery($order) ? 'Ready for delivery' : 'Ready for pickup'; }
    if (in_array($order['status'], ['COMPLETED','CANCELLED'],true)) { return ''; }
    if (empty($order['estimated_ready_at'])) { return 'The shop confirms preparation time after accepting your order.'; }
    return strtotime($order['estimated_ready_at']) <= time() ? 'Preparation is taking longer. Please wait for the Ready update.' : 'Estimated ready around ' . date('g:i A',strtotime($order['estimated_ready_at'])) . '. Please wait for the Ready update.';
}
function set_order_estimate(int $orderId, int $staffId, int $minutes): array
{
    if ($minutes<1 || $minutes>180) { return ['ok'=>false,'message'=>'Enter 1–180 minutes from now.']; }
    return order_transaction(static function (PDO $pdo) use ($orderId,$staffId,$minutes): array {
        require_payment_staff($pdo,$staffId);$order=locked_order($pdo,$orderId);
        if (!in_array($order['status'],['ACCEPTED','PREPARING'],true)) { throw new RuntimeException('Estimates can be changed only for accepted or preparing orders.'); }
        $ready=date('Y-m-d H:i:s',time()+$minutes*60);
        $pdo->prepare('UPDATE orders SET estimated_ready_at = ? WHERE id = ?')->execute([$ready,$orderId]);
        $message='Estimated ready around ' . date('g:i A',strtotime($ready)) . '. Wait for the Ready update before pickup or delivery.';
        $pdo->prepare('INSERT INTO order_status_history (order_id,old_status,new_status,changed_by,remarks) VALUES (?,?,?,?,?)')->execute([$orderId,$order['status'],$order['status'],$staffId,$message]);
        notify_user((int)$order['customer_id'],$orderId,'ORDER_STATUS','Preparation estimate updated',$message);
        return ['ok'=>true,'message'=>'Preparation estimate updated.'];
    });
}
function cancel_customer_order(int $orderId, int $customerId): array
{
    return order_transaction(static function (PDO $pdo) use ($orderId,$customerId): array {
        $order=locked_order($pdo,$orderId);
        if ((int)$order['customer_id']!==$customerId) { throw new RuntimeException('Order not found.'); }
        if ($order['status']!=='NEW') { throw new RuntimeException('This order has already been handled. Contact the shop for help.'); }
        if (reservation_due($order)) { expire_locked_order($pdo,$order); return ['ok'=>true,'message'=>'The reservation already expired. Stock has been released.']; }
        release_order_stock($pdo,$order,$customerId,'Customer cancelled before acceptance');
        $paid=$order['payment_status']==='PAID';
        $pdo->prepare('UPDATE orders SET status="CANCELLED",reservation_expires_at=NULL,payment_status=? WHERE id=?')->execute([$paid?'REFUND_REQUIRED':$order['payment_status'],$orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id,old_status,new_status,changed_by,remarks) VALUES (?,"NEW","CANCELLED",?,"Customer cancelled before acceptance")')->execute([$orderId,$customerId]);
        $message=$paid?'Order cancelled. The shop must arrange and confirm your refund; cancellation does not send money automatically.':'Order cancelled. Reserved stock has been released.';
        notify_user($customerId,$orderId,'ORDER_STATUS','Order cancelled',$message);
        foreach($pdo->query('SELECT id FROM users WHERE role IN ("STAFF","MANAGER") AND is_active=1')->fetchAll(PDO::FETCH_COLUMN) as $staff) { notify_user((int)$staff,$orderId,'ORDER_STATUS','Customer cancelled ' . $order['order_number'],$message); }
        return ['ok'=>true,'message'=>$message];
    });
}
function customer_order_revision(array $order): string
{
    $query=db()->prepare('SELECT MAX(id) FROM order_status_history WHERE order_id=?');$query->execute([$order['id']]);
    return hash('sha256',json_encode([$order['status'],$order['payment_status'],$order['estimated_ready_at'],$order['reservation_expires_at'],$query->fetchColumn()]));
}

/** Fixed fee is calculated on the server, never taken from browser input. */
function delivery_fee_centavos(): int { return 2000; }
function is_delivery(array $order): bool { return ($order['fulfillment_type'] ?? 'PICKUP') === 'DELIVERY'; }
function fulfillment_label(array $order): string { return is_delivery($order) ? 'Delivery' : 'Pickup'; }
function fulfillment_details(string $type, string $address, string $contact): array
{
    if (!in_array($type, ['PICKUP','DELIVERY'], true)) { throw new InvalidArgumentException('Choose pickup or delivery.'); }
    if ($type === 'PICKUP') { return [$type, '', 0]; }
    $address = trim($address);
    if (mb_strlen($address) < 10 || mb_strlen($address) > 500 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $address)) {
        throw new InvalidArgumentException('Enter a complete delivery address (10–500 characters), including street and barangay.');
    }
    if (trim($contact) === '') { throw new InvalidArgumentException('A contact number is required for delivery.'); }
    return [$type, $address, delivery_fee_centavos()];
}

function delivery_area_text(array $settings): string
{
    $area=trim((string)($settings['delivery_area'] ?? ''));
    return $area !== '' ? 'Delivery area: ' . $area . '. Staff confirm the address before acceptance.' : 'Contact the shop to confirm your delivery address is covered before ordering.';
}
