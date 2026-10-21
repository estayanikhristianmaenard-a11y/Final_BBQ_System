<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');
$error=null;
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    csrf_verify(); $result=save_store_settings($_POST,(int)current_user()['id']);
    if($result['ok']) { flash('success',$result['message']);redirect(url('manager/store.php')); }
    $error=$result['message'];
}
$settings=store_settings();$values=($_SERVER['REQUEST_METHOD'] ?? '')==='POST'?array_replace($settings,array_filter($_POST,'is_string')):$settings;
$pageTitle='Store settings — BulkBites BBQ';require dirname(__DIR__).'/includes/header.php';
?>
<section class="page-head"><div><h1>Settings</h1><p class="muted">Control new orders, shop details, and simple order limits.</p></div></section>
<?php require dirname(__DIR__) . '/includes/settings-nav.php'; ?>
<p class="notice" role="status"><?= e(store_status_text($settings)) ?>. Existing orders continue when new ordering is closed or paused.</p>
<?php if($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="store-settings" action="<?= e(url('manager/store.php')) ?>">
<?= csrf_field() ?>
<div class="dash-grid">
<section class="card"><h2>Ordering and delivery</h2>
  <div class="form-group"><label for="ordering-status">New orders</label><select id="ordering-status" name="ordering_status"><?php foreach(['OPEN'=>'Open','PAUSED'=>'Paused','CLOSED'=>'Closed'] as $key=>$label): ?><option value="<?= e($key) ?>"<?= $values['ordering_status']===$key?' selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
  <div class="form-group"><label for="pickup-location">Pickup location</label><input id="pickup-location" name="pickup_location" maxlength="200" value="<?= e($values['pickup_location']) ?>" required></div>
  <div class="form-group"><label for="shop-contact">Shop contact <span class="muted">(optional)</span></label><input id="shop-contact" name="contact_details" maxlength="120" value="<?= e($values['contact_details']) ?>"><p class="hint">A phone number or a short instruction customers can use to contact you.</p></div>
  <div class="form-group"><label for="delivery-area">Delivery area <span class="muted">(optional)</span></label><input id="delivery-area" name="delivery_area" maxlength="200" value="<?= e($values['delivery_area'] ?? '') ?>"><p class="hint">List the barangays or nearby areas covered by the fixed ₱20 fee. Leave blank to ask customers to contact the shop first. This is a notice; staff confirm coverage before accepting.</p></div>
  <div class="form-group"><label for="prep-minutes">Estimated preparation after acceptance (minutes)</label><input id="prep-minutes" type="number" name="prep_minutes" min="1" max="180" value="<?= (int)$values['prep_minutes'] ?>" required><p class="hint">An estimate, not a promise. Staff can adjust each accepted order.</p></div>
</section>
<section class="card"><h2>Daily hours</h2>
  <label class="human-box"><input type="checkbox" name="use_hours" value="1"<?= !empty($_POST ? ($_POST['use_hours'] ?? null) : $settings['use_hours'])?' checked':'' ?>>Use daily opening hours</label>
  <p class="hint">Philippine time. Closing earlier than opening means the shop closes the next morning. Uncheck for manual Open/Closed control.</p>
  <div class="form-group"><label for="opens-at">Opens at</label><input id="opens-at" type="time" name="opens_at" value="<?= e(substr((string)$values['opens_at'],0,5)) ?>"></div>
  <div class="form-group"><label for="closes-at">Closes at</label><input id="closes-at" type="time" name="closes_at" value="<?= e(substr((string)$values['closes_at'],0,5)) ?>"></div>
  <details class="advanced-section"<?= $error ? ' open' : '' ?>><summary>Advanced order limits</summary>
  <?php foreach(['max_active_orders'=>['Active orders per customer',1,10], 'cooldown_seconds'=>['Seconds between new orders',0,3600], 'max_item_qty'=>['Maximum pieces per item',1,250], 'max_order_qty'=>['Maximum pieces per order',1,1500]] as $key=>$field): ?>
  <div class="form-group"><label for="<?= e($key) ?>"><?= e($field[0]) ?></label><input type="number" id="<?= e($key) ?>" name="<?= e($key) ?>" min="<?= $field[1] ?>" max="<?= $field[2] ?>" value="<?= (int)$values[$key] ?>" required></div>
  <?php endforeach; ?>
  <p class="hint">Limits are checked when an order is placed, including from separate devices. Ask customers to contact the shop for bulk orders.</p>
  </details>
</section>
</div><button class="btn btn-primary mt" type="submit">Save store settings</button>
</form>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
