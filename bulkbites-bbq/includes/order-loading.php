<?php /** Checkout-only progress screen. Order state comes from the server response. */ ?>
<dialog class="order-loading" data-order-progress aria-labelledby="order-loading-title" aria-describedby="order-loading-message">
  <div class="order-loading-art" aria-hidden="true">
    <img src="<?= e(url('assets/images/grill-render.webp')) ?>" width="300" height="300" alt="">
    <span class="order-loading-smoke"><i></i><i></i><i></i></span>
  </div>
  <h2 id="order-loading-title">Sending your order…</h2>
  <p id="order-loading-message" role="status">Please keep this page open while we confirm your order.</p>
</dialog>
<script src="<?= e(url('assets/js/order-loading.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/order-loading.js') ?>" data-grill-module="<?= e(url('assets/js/grill-3d.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/js/grill-3d.js') ?>" defer></script>
