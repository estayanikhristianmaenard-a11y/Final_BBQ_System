<?php /** Shared page footer. */ ?>
</main>
<footer class="site-footer">
  <div class="container">
    <?php $footerStore=store_settings(); ?><strong>BulkBites BBQ</strong> · <?= e($footerStore['pickup_location']) ?>
    <?php if($footerHours=store_hours_text($footerStore)): ?><div><?= e($footerHours) ?></div><?php endif; ?>
  </div>
</footer>
<?php $clearCheckoutDraft = $_SESSION['checkout_clear_draft'] ?? ''; unset($_SESSION['checkout_clear_draft']); ?>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/app.js') ?>"<?= $clearCheckoutDraft ? ' data-clear-checkout-draft="'.e($clearCheckoutDraft).'"' : '' ?>></script>
<script src="<?= e(url('assets/js/ui.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/ui.js') ?>"></script>
<?php if (!empty($hasProductManager)): ?>
<script src="<?= e(url('assets/js/product-manager.js')) ?>?v=<?= (int)filemtime(dirname(__DIR__).'/assets/js/product-manager.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($hasOrderActionDialogs)): ?>
<script src="<?= e(url('assets/js/order-action-dialog.js')) ?>?v=<?= (int)filemtime(dirname(__DIR__).'/assets/js/order-action-dialog.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($hasCartInteraction)): require __DIR__ . '/cart-feedback.php'; ?>
<script src="<?= e(url('assets/js/cart-interactions.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/cart-interactions.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($hasGrillScene)): ?>
<script src="<?= e(url('assets/js/grill.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/grill.js') ?>" data-grill-3d="<?= e(url('assets/js/grill-3d.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/grill-3d.js') ?>"></script>
<?php endif; ?>
<?php $footerUser = current_user(); if ($footerUser): ?>
<script src="<?= e(url('assets/js/notifications.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/notifications.js') ?>" data-notif-api="<?= e(url('api/notifications.php')) ?>"></script>
<?php endif; ?>
</body>
</html>
