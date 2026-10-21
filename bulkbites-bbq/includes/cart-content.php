<?php /** Shared initial cart and authoritative in-place response markup. Expects $view and $user. */ ?>
<?php if (!$view['lines']): ?>
  <div class="empty-state card">
    <div class="empty-icon" aria-hidden="true"><svg class="nav-icon" focusable="false"><use href="<?= e(url('assets/images/nav-icons.svg#cart')) ?>"></use></svg></div>
    <p><strong>Your cart is empty.</strong></p>
    <p class="muted">Add freshly grilled ihaw-ihaw from the menu.</p>
    <a class="btn btn-primary" href="<?= e(url('customer/menu.php')) ?>">Browse the menu</a>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <div class="cart-lines">
      <?php foreach ($view['lines'] as $line):
          $image = $line['image'] ?: 'assets/images/products/placeholder.svg'; ?>
        <div data-cart-product="<?= (int) $line['id'] ?>" class="cart-row<?= $line['buyable'] ? '' : ' unavailable' ?>">
          <img class="cart-thumb" src="<?= e(url($image)) ?>" alt="<?= e($line['name']) ?>">
          <div class="cart-info">
            <div class="cart-name">
              <?= e($line['name']) ?>
              <?php if (!$line['buyable']): ?><span class="badge badge-out"><?= e($line['reason']) ?></span><?php endif; ?>
            </div>
            <div class="cart-price"><?= e(peso($line['price'])) ?> each</div>
          </div>

          <form data-cart-form class="qty-form" method="post" action="<?= e(url('customer/cart.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $line['id'] ?>">
            <div class="stepper">
              <button class="step-btn" type="submit" name="step" value="-1" formnovalidate aria-label="Decrease quantity for <?= e($line['name']) ?>">&minus;</button>
              <input class="qty-field" type="number" name="qty" value="<?= (int) $line['qty'] ?>"
                     min="1" max="<?= max(1, (int) $line['stock']) ?>" data-autosubmit
                     aria-label="Quantity for <?= e($line['name']) ?>">
              <button class="step-btn" type="submit" name="step" value="1" formnovalidate aria-label="Increase quantity for <?= e($line['name']) ?>">&plus;</button>
            </div>
            <button class="btn btn-outline btn-sm" type="submit" name="remove" value="1" formnovalidate aria-label="Remove <?= e($line['name']) ?> from cart">Remove</button>
          </form>

          <div class="cart-line-total"><?= $line['buyable'] ? e(peso($line['line_total'])) : '—' ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <aside class="cart-summary card">
      <h2>Order summary</h2>
      <div class="summary-row"><span>Items</span><strong><?= (int) $view['items'] ?></strong></div>
      <?php if ($view['unavailable']): ?>
        <div class="summary-row muted"><span>Unavailable</span><strong><?= (int) $view['unavailable'] ?></strong></div>
      <?php endif; ?>
      <div class="summary-row summary-total"><span>Subtotal</span><strong><?= e(peso($view['subtotal'])) ?></strong></div>
      <p class="hint">Prices and availability are checked at checkout.</p>

      <?php if ($view['items'] > 0): ?>
        <a class="btn btn-primary btn-block cart-summary-checkout" href="<?= e(url('customer/checkout.php')) ?>">Proceed to checkout</a>
        <p class="hint center"><?= $user ? 'Choose your payment method on the next step.' : 'Verify your email to continue. Your cart stays with you.' ?></p>
      <?php else: ?>
        <p class="notice" role="status">These items are unavailable. Add available food from the menu to continue.</p>
      <?php endif; ?>

      <a class="btn btn-outline btn-block mt" href="<?= e(url('customer/menu.php')) ?>">Add more items</a>
      <form data-cart-form method="post" action="<?= e(url('customer/cart.php')) ?>" data-confirm="Clear your cart? This will remove all items.">
        <?= csrf_field() ?>
        <button class="btn btn-text btn-block mt" type="submit" name="reset" value="1">Clear cart</button>
      </form>

    </aside>
  </div>
<?php endif; ?>
<?php if ($view['items'] > 0): ?>
<a class="cart-dock cart-checkout-dock" href="<?= e(url('customer/checkout.php')) ?>">
  <span><strong><?= e(peso($view['subtotal'])) ?></strong><small><?= (int) $view['items'] ?> <?= $view['items'] === 1 ? 'item' : 'items' ?></small></span>
  <span>Continue to checkout</span>
</a>
<?php endif; ?>
