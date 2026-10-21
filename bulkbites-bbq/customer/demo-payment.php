<?php
require dirname(__DIR__).'/includes/bootstrap.php';
require_role('CUSTOMER');
header('Cache-Control: no-store');
$ref=is_string($_GET['ref'] ?? null)?trim($_GET['ref']):'';
$order=find_customer_order($ref,(int)current_user()['id']);
if (!$order || $order['payment_method']!=='DEMO') { http_response_code(404);exit('Demo order not found.'); }
if (!demo_payment_enabled()) { http_response_code(403);exit('The payment simulator is disabled.'); }
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    csrf_verify();
    if (!rate_limit_hit('demo_payment_action','customer:'.current_user()['id'],10,60)) { $result=['ok'=>false,'message'=>'Please wait a minute before trying another demo payment.']; }
    else { $result=simulate_order_payment((int)$order['id'],(int)current_user()['id'],is_string($_POST['outcome'] ?? null)?$_POST['outcome']:''); }
    if(!empty($result['receipt_email_status'])) {
        $result['message'].=$result['receipt_email_status']==='SENT' ? ' Your receipt was emailed to your sign-in email.' : ' You can view the receipt from your order page; email delivery may still be pending.';
    }
    flash($result['ok']?'success':'warning',$result['message']);
    redirect(url('customer/order.php').'?ref='.rawurlencode($ref));
}
$payable=$order['status']==='NEW' && $order['payment_status']==='PENDING';
$pageTitle='Demo payment — BulkBites BBQ';require dirname(__DIR__).'/includes/header.php';
?>
<section class="page-head"><h1>Demo payment</h1><p class="muted">A payment simulation for your presentation. No real money, account, or payment details are needed.</p></section>
<div class="card auth-card demo-payment-card">
  <p class="notice"><strong>DEMO ONLY — no real money</strong><br>This is a simulator built into BulkBites, not a connection to GCash, a bank, or a payment gateway.</p>
  <h2><?= e($order['order_number']) ?></h2>
  <div class="summary-row summary-total"><span>Demo amount</span><strong><?= e(peso($order['total'])) ?></strong></div>
  <?php if($payable): ?>
    <p>Choose an outcome to demonstrate what happens next.</p>
    <form method="post" action="<?= e(url('customer/demo-payment.php').'?ref='.rawurlencode($ref)) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-primary btn-block" name="outcome" value="success" type="submit">Simulate successful payment</button>
      <button class="btn btn-outline btn-block mt" name="outcome" value="failed" type="submit">Simulate failed payment</button>
      <button class="btn btn-text btn-block mt" name="outcome" value="cancelled" type="submit">Cancel demo payment</button>
    </form>
    <p class="hint">Failure or cancellation leaves the order unpaid. You can retry before the reservation expires.</p>
  <?php else: ?><p role="status">This order is no longer awaiting a demo payment. Check its current status below.</p><?php endif; ?>
  <a class="btn btn-outline btn-block mt" href="<?= e(url('customer/order.php').'?ref='.rawurlencode($ref)) ?>">Back to order</a>
</div>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
