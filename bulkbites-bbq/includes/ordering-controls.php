<?php
/** Shared staff dashboard/kitchen controls. The parent page enforces personnel access. */
$orderingSettings=store_settings();
$orderingReturn=$orderingReturn ?? 'staff/' . basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
$orderingEnabled=$orderingSettings['ordering_status']==='OPEN';
?>
<section class="card ordering-control" aria-labelledby="ordering-control-title">
  <div><h2 id="ordering-control-title">New orders</h2>
    <p role="status"><strong data-ordering-label><?= e(store_status_text($orderingSettings)) ?></strong></p>
    <?php if($orderingHours=store_hours_text($orderingSettings)): ?><p class="hint"><?= e($orderingHours) ?>. Open ordering follows these hours.</p><?php endif; ?>
    <p class="hint">Closing stops new orders until you reopen. Existing orders stay available.</p>
  </div>
  <form method="post" action="<?= e(url('staff/ordering.php')) ?>" class="ordering-buttons" data-ordering-control>
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($orderingReturn) ?>">
    <input type="hidden" name="ordering_status" value="<?= $orderingEnabled ? 'CLOSED' : 'OPEN' ?>">
    <button class="btn <?= $orderingEnabled ? 'btn-outline' : 'btn-primary' ?>" type="submit"><?= $orderingEnabled ? 'Close ordering' : 'Open ordering' ?></button>
  </form>
</section>
