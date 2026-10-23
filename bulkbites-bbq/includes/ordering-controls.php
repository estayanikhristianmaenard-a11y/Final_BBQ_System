<?php
/** Shared staff dashboard/kitchen controls. The parent page enforces personnel access. */
$orderingSettings=store_settings();
$orderingReturn=$orderingReturn ?? 'staff/' . basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
$orderingEnabled=store_is_open($orderingSettings);
$canOverrideHours=current_user()['role']==='MANAGER';
?>
<section class="card ordering-control" aria-labelledby="ordering-control-title">
  <div><h2 id="ordering-control-title">New orders</h2>
    <p role="status"><strong data-ordering-label><?= e(store_status_text($orderingSettings)) ?></strong></p>
    <?php if($orderingHours=store_hours_text($orderingSettings)): ?><p class="hint"><?= e($orderingHours) ?>.</p><?php endif; ?>
    <p class="hint" data-ordering-mode><?= e(store_ordering_mode_text($orderingSettings)) ?></p>
    <p class="hint">Existing orders stay available when ordering is closed.</p>
  </div>
  <form method="post" action="<?= e(url('staff/ordering.php')) ?>" class="ordering-buttons" data-ordering-control>
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($orderingReturn) ?>">
    <input type="hidden" name="ordering_status" value="<?= $orderingEnabled ? 'CLOSED' : 'OPEN' ?>">
    <button data-ordering-toggle class="btn <?= $orderingEnabled ? 'btn-outline' : 'btn-primary' ?>" type="submit"><?= $orderingEnabled ? 'Close ordering' : 'Open ordering' ?></button>
    <?php if($canOverrideHours): ?><button class="btn btn-outline btn-sm" type="submit" name="ordering_status" value="AUTO" data-ordering-auto<?= $orderingSettings['ordering_override']==='AUTO' ? ' hidden' : '' ?>>Use automatic hours</button><?php endif; ?>
  </form>
</section>
