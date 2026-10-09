<?php /** Shared page footer. */ ?>
</main>
<footer class="site-footer">
  <div class="container">
    <strong>BulkBites BBQ</strong> · Greensite Compound, Sta. Lucia, Pasig City · near SM City East
  </div>
</footer>
<script src="<?= e(url('assets/js/password-toggle.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/password-toggle.js') ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/app.js') ?>"></script>
<?php $footerUser = current_user(); if ($footerUser): ?>
<script src="<?= e(url('assets/js/notifications.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/notifications.js') ?>" data-notif-api="<?= e(url('api/notifications.php')) ?>"></script>
<?php endif; ?>
</body>
</html>
