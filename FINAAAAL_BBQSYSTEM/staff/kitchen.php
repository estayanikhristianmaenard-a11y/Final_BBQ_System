<?php
/**
 * Kitchen Display System — Phase 6 (architecture §5).
 * Color-coded columns NEW / IN PROGRESS / READY, one card per order with age,
 * items, notes, and single-tap status buttons. Plain forms work without JS
 * (posted to api/orders.php with a redirect back); kds.js upgrades them to
 * fetch + 10-second queue polling.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');

$user = current_user();
$counts = staff_order_counts();
$queue  = staff_active_queue();

$columns = [
    ['key' => 'NEW',       'title' => 'New',        'statuses' => ['NEW']],
    ['key' => 'IN PROGRESS', 'title' => 'In progress', 'statuses' => ['ACCEPTED', 'PREPARING']],
    ['key' => 'READY',     'title' => 'Ready',      'statuses' => ['READY']],
];

/** One KDS card (also the server-side fallback when JS is off). */
$render_card = static function (array $order) use ($user): void {
    $age = (int) $order['age_seconds'];
    $ageClass = $age >= 900 ? ' age-hot' : ($age >= 300 ? ' age-warm' : '');
    $minutes = intdiv($age, 60);
    $ageLabel = $minutes < 1 ? 'just now' : $minutes . 'm ' . ($age % 60) . 's';
    ?>
    <article class="kds-card<?= $ageClass ?>" data-order-id="<?= (int) $order['id'] ?>">
      <header class="kds-card-head">
        <strong class="kds-no"><?= e($order['order_number']) ?></strong>
        <span class="kds-age" data-created="<?= e($order['created_at']) ?>"><?= e($ageLabel) ?></span>
      </header>
      <div class="kds-meta">
        <span><?= e($order['customer_label']) ?></span>
        <span><?= e($order['payment_method']) ?> · <?= e(peso($order['total'])) ?></span>
      </div>
      <ul class="kds-items">
        <?php foreach ($order['items'] as $item): ?>
          <li><span class="kds-qty"><?= (int) $item['quantity'] ?>×</span> <?= e($item['product_name']) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($order['notes']): ?>
        <p class="kds-notes">&#128172; <?= e($order['notes']) ?></p>
      <?php endif; ?>
      <div class="kds-actions">
        <?php foreach (order_status_transitions()[$order['status']] ?? [] as $target): ?>
          <form method="post" action="<?= e(url('api/orders.php')) ?>"
                <?= $target === 'CANCELLED' ? 'data-confirm="Cancel this order? This cannot be undone."' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
            <input type="hidden" name="action" value="<?= e($target) ?>">
            <input type="hidden" name="return" value="staff/kitchen.php">
            <button class="btn btn-sm <?= $target === 'CANCELLED' ? 'btn-outline kds-btn-cancel' : 'btn-primary' ?>"
                    type="submit"><?= e(order_status_action_label($target)) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </article>
    <?php
};

$pageTitle = 'Kitchen Display — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(url('assets/css/kds.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/kds.css') ?>">

<section class="page-head">
  <div>
    <h1>Kitchen Display</h1>
    <p class="muted">Live order queue · refreshes every 10 seconds · tap a status button to advance an order.</p>
  </div>
  <div class="kds-legend muted small">
    <span><span class="kds-dot dot-new"></span> New</span>
    <span><span class="kds-dot dot-prep"></span> In progress</span>
    <span><span class="kds-dot dot-ready"></span> Ready</span>
  </div>
</section>

<div class="kds-totals" aria-label="Order counts">
  <span class="kds-total"><strong data-count="new"><?= (int) $counts['new'] ?></strong> new</span>
  <span class="kds-total"><strong data-count="in_progress"><?= (int) $counts['in_progress'] ?></strong> in progress</span>
  <span class="kds-total"><strong data-count="ready"><?= (int) $counts['ready'] ?></strong> ready</span>
  <span class="kds-total"><strong data-count="completed_today"><?= (int) $counts['completed_today'] ?></strong> completed today</span>
  <a class="btn btn-outline btn-sm" href="<?= e(url('staff/orders.php')) ?>">All orders</a>
</div>

<div id="kds-flash" class="flash flash-info" style="display:none" role="status"></div>

<div class="kds-board" id="kds-board" role="region" aria-label="Live order board" aria-live="off">
  <?php foreach ($columns as $column): ?>
    <section class="kds-col kds-col-<?= str_replace(' ', '-', strtolower($column['key'])) ?>" data-col="<?= e($column['key']) ?>">
      <h2 class="kds-col-head">
        <?= e($column['title']) ?>
        <span class="kds-col-count" data-col-count="<?= e($column['key']) ?>">
          <?= count(array_filter($queue, fn($o) => in_array($o['status'], $column['statuses'], true))) ?>
        </span>
      </h2>
      <div class="kds-cards">
        <?php foreach ($queue as $order): ?>
          <?php if (in_array($order['status'], $column['statuses'], true)) { $render_card($order); } ?>
        <?php endforeach; ?>
      </div>
      <p class="kds-empty" style="<?= count(array_filter($queue, fn($o) => in_array($o['status'], $column['statuses'], true))) ? 'display:none' : '' ?>">Nothing here. &#127770;</p>
    </section>
  <?php endforeach; ?>
</div>

<script src="<?= e(url('assets/js/kds.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/kds.js') ?>"
        defer data-kds-api="<?= e(url('api/orders.php')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
