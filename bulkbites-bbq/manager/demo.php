<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');
header('Cache-Control: no-store');
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    try {
        $base = demo_base_url(is_string($_POST['public_base_url'] ?? null) ? $_POST['public_base_url'] : '');
        if (!write_demo_settings(dirname(__DIR__) . '/config/demo.php', ['public_base_url'=>$base])) {
            throw new RuntimeException('Could not save the demo URL. Check config directory permissions.');
        }
        flash('success', 'Ordering link updated. Download a fresh QR if the address changed.');
        redirect(url('manager/demo.php'));
    } catch (InvalidArgumentException | RuntimeException $exception) { $error = $exception->getMessage(); }
}
$orderingUrl = demo_ordering_url();
$publicBase = demo_settings()['public_base_url'] ?? '';
$pageTitle = 'Ordering QR & demo setup — BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-head"><div><h1>Ordering QR &amp; demo setup</h1><p class="muted">Share your menu and check what is ready for the presentation.</p></div></section>
<?php require dirname(__DIR__) . '/includes/settings-nav.php'; ?>
<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="dash-grid">
  <section class="card">
    <h2>Customer ordering QR</h2>
    <?php if ($orderingUrl): ?>
      <canvas id="ordering-qr" class="ordering-qr" data-ordering-url="<?= e($orderingUrl) ?>" aria-label="QR code opening the customer ordering menu"></canvas>
      <p class="share-url"><a href="<?= e($orderingUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($orderingUrl) ?></a></p>
      <p>Scan to choose your food and order for pickup.</p>
      <div class="form-actions"><a id="download-qr" class="btn btn-primary" download="bulkbites-ordering-qr.png" hidden>Download QR PNG</a><button class="btn btn-outline" id="print-qr" type="button">Print QR</button></div>
      <p id="qr-status" class="hint" role="status"></p>
      <noscript><p>The menu link above works without JavaScript. Enable JavaScript to generate the downloadable QR.</p></noscript>
    <?php else: ?><p>Start the public demo tunnel, then save its HTTPS address below to generate the QR.</p><?php endif; ?>
    <p class="hint">This QR opens the menu. It does not request or confirm a payment.</p>
  </section>
  <section class="card">
    <h2>Demo readiness</h2>
    <ul class="readiness-list">
      <li><strong>Menu and cart:</strong> available</li>
      <li><strong>Email sign-in:</strong> <?= email_configured() ? 'sender configured; verify delivery to a real inbox' : 'connect the sender first' ?></li>
      <li><strong>Public ordering link:</strong> <?= $orderingUrl ? 'saved; check it on mobile data' : 'not saved' ?></li>
      <li><strong>Payment demonstration:</strong> <?= demo_payment_enabled() ? 'local simulator ready; no account or real money needed' : 'simulator disabled' ?></li>
    </ul>
    <p><a class="btn btn-outline" href="<?= e(url('manager/email.php')) ?>">Email setup</a></p>
    <p class="hint">Keep the server PC, MySQL, and the public tunnel running. A saved address alone does not prove the tunnel is online.</p>
    <details><summary>Update public ordering address</summary>
      <form method="post" action="<?= e(url('manager/demo.php')) ?>">
        <?= csrf_field() ?><label for="public-base">Public HTTPS base URL</label>
        <input id="public-base" name="public_base_url" type="url" maxlength="400" placeholder="https://your-demo.ngrok-free.app" value="<?= e($publicBase) ?>" required>
        <p class="hint">Use the app's base address. Do not append /customer/menu.php. Download a new ordering QR if this address changes.</p>
        <button class="btn btn-primary" type="submit">Save ordering address</button>
      </form>
    </details>
  </section>
</div>
<script src="<?= e(url('assets/vendor/qrcode-generator/qrcode.js')) ?>"></script>
<script src="<?= e(url('assets/vendor/qrcode-generator/qrcode_UTF8.js')) ?>"></script>
<script src="<?= e(url('assets/js/ordering-qr.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/ordering-qr.js') ?>"></script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
