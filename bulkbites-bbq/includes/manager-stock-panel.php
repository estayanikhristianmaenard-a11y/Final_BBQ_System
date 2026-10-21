<?php
$rows = staff_inventory_rows();
$catalogueById = array_column($products, null, 'id');
$movements = manager_recent_movements(15);
$lowCount = count(array_filter($rows, fn($r) => $r['state'] !== 'IN'));

?>
<div data-managed-products>
<section class="page-head" id="stock">
  <div>
    <h2>Menu items</h2>
    <p class="muted">Update available quantities and pause items when needed. Stock changes are recorded.</p>
  </div>
  <div class="inv-summary">
    <?php if ($lowCount > 0): ?>
      <span class="badge badge-low"><?= $lowCount ?> item(s) need attention</span>
    <?php else: ?>
      <span class="badge badge-in">All stock healthy</span>
    <?php endif; ?>
  </div>
</section>

<div class="card">
  <h2 class="card-title">Products, prices and stock</h2>
  <div class="queue-wrap">
    <table class="data-table inv-table">
      <thead>
        <tr>
          <th>Product</th>
          <th class="num">Stock</th>
          <th class="num">Price</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr id="product-row-<?= (int)$row['id'] ?>">
            <td>
              <strong><?= e($row['name']) ?></strong><br>
              <span class="muted small"><?= e($row['category_name']) ?></span>
              <?php if (!(int) $row['is_active']): ?><span class="badge badge-out">Hidden from menu</span><?php endif; ?>
            </td>
            <td class="num"><strong><?= (int) $row['quantity_available'] ?></strong> <?= e($row['unit']) ?></td>
            <td class="num"><?= e(peso($catalogueById[$row['id']]['price'])) ?></td>
            <td>
              <?php if (!(int)$row['is_available']): ?><span class="badge badge-low">Ordering paused</span><?php endif; ?>
              <?php if ($row['state'] === 'IN'): ?>
                <span class="badge badge-in">In stock</span>
              <?php elseif ($row['state'] === 'LOW'): ?>
                <span class="badge badge-low">Low stock</span>
              <?php else: ?>
                <span class="badge badge-out">Out of stock</span>
              <?php endif; ?>
            </td>
            <td>
              <?php $product=$catalogueById[$row['id']]; ?>
              <details class="product-editor"><summary>Manage product</summary>
                <section class="product-editor-section"><h3>Stock</h3>
                <p>Available: <strong><?= (int)$row['quantity_available'] ?> <?= e($row['unit']) ?></strong></p>
              <form method="post" action="<?= e(url('manager/products.php') . '#stock') ?>" class="stock-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <label>Stock to add (pcs)</label>
                <input type="number" name="quantity" min="1" max="999999" value="10" required
                       aria-label="Restock quantity for <?= e($row['name']) ?>">
                <input type="text" name="remarks" maxlength="255" placeholder="Remarks (optional)"
                       aria-label="Remarks">
                <button class="btn btn-sm btn-primary" type="submit">Restock</button>
              </form>
              <details class="advanced-section"><summary>Correct stock quantity</summary>
              <p class="hint">Use a negative number to remove stock, or a positive number with a reason to correct it.</p>
              <form method="post" action="<?= e(url('manager/products.php') . '#stock') ?>" class="stock-form"
                    data-confirm="Apply this adjustment to <?= e($row['name']) ?>? It will be logged with your name.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <input type="number" name="quantity" min="-999999" max="999999" value="-1" required
                       aria-label="Adjustment quantity for <?= e($row['name']) ?>">
                <input type="text" name="remarks" maxlength="255" placeholder="Reason (required for +)"
                       aria-label="Adjustment reason">
                <button class="btn btn-sm btn-outline" type="submit">Adjust</button>
              </form>
              </details>
              <details><summary>Advanced stock settings</summary>
              <form method="post" action="<?= e(url('manager/products.php') . '#stock') ?>" class="stock-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="threshold">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <label class="sr-only" for="thr-<?= (int) $row['id'] ?>">Low-stock threshold</label>
                <input type="number" id="thr-<?= (int) $row['id'] ?>" name="low_stock_threshold"
                       min="0" max="999999" value="<?= (int) $row['low_stock_threshold'] ?>">
                <button class="btn btn-sm btn-outline" type="submit">Set min</button>
              </form>
              </details>
                <p class="muted small">Last updated <?= e(date('M j · g:i A', strtotime($row['last_updated']))) ?><?= $row['updated_by_name'] ? ' by ' . e($row['updated_by_name']) : '' ?></p>
                </section>
                <details class="product-editor-section"><summary>Product details</summary>
                  <form method="post" enctype="multipart/form-data" action="<?= e(url('manager/products.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="product_id" value="<?= (int)$row['id'] ?>">
                    <label for="manage-name-<?= (int)$row['id'] ?>">Name</label><input id="manage-name-<?= (int)$row['id'] ?>" type="text" name="name" maxlength="100" minlength="2" value="<?= e($product['name']) ?>" required>
                    <label for="manage-price-<?= (int)$row['id'] ?>">Price (₱)</label><input id="manage-price-<?= (int)$row['id'] ?>" type="number" name="price" min="0" max="99999999.99" step="0.01" value="<?= e($product['price']) ?>" required>
                    <details class="advanced-section"><summary>More product details</summary>
                      <label for="manage-category-<?= (int)$row['id'] ?>">Category</label><select id="manage-category-<?= (int)$row['id'] ?>" name="category_id"><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>"<?= (int)$product['category_id']===(int)$category['id']?' selected':'' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
                      <label for="manage-description-<?= (int)$row['id'] ?>">Description</label><textarea id="manage-description-<?= (int)$row['id'] ?>" name="description" maxlength="255" rows="2"><?= e($product['description'] ?? '') ?></textarea>
                      <label for="manage-image-<?= (int)$row['id'] ?>">Replace photo (optional, up to 2 MB)</label><input id="manage-image-<?= (int)$row['id'] ?>" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                    </details>
                    <button class="btn btn-primary" type="submit">Save product details</button>
                  </form>
                </details>
                <section class="product-editor-section"><h3>Availability</h3>
              <span class="availability-label"><?= (int) $row['is_available'] ? 'Ordering enabled' : 'Ordering paused' ?></span>
              <form method="post" action="<?= e(url('manager/products.php') . '#stock') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_availability">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-sm <?= (int) $row['is_available'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"
                  data-confirm="<?= (int) $row['is_available']
                    ? 'Pause ordering for ' . e($row['name']) . '?'
                    : 'Make ' . e($row['name']) . ' available for ordering again?' ?>">
                  <?= (int) $row['is_available'] ? 'Pause ordering' : 'Resume ordering' ?>
                </button>
              </form>
              <details class="mt"><summary>Menu visibility</summary>
                <form method="post" action="<?= e(url('manager/products.php')) ?>">
                  <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-sm btn-outline" type="submit" data-confirm="Change menu visibility for <?= e($row['name']) ?>? Existing orders are unaffected."><?= (int) $row['is_active'] ? 'Hide from menu' : 'Show on menu' ?></button>
                </form>
              </details>
                </section>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<details class="card" style="margin-top:16px"><summary>Stock movement log (latest 15)</summary>
  <?php if (!$movements): ?>
    <p class="muted">No movements recorded yet — restocks, adjustments, and checkout deductions appear here.</p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>When</th><th>Product</th><th>Type</th><th class="num">Change</th><th>By</th><th>Remarks</th></tr>
      </thead>
      <tbody>
        <?php foreach ($movements as $m): ?>
          <tr>
            <td class="muted small"><?= e(date('M j · g:i A', strtotime($m['created_at']))) ?></td>
            <td><?= e($m['product_name']) ?></td>
            <td>
              <?php if ($m['movement_type'] === 'RESTOCK'): ?><span class="badge badge-in">Restock</span>
              <?php elseif ($m['movement_type'] === 'ORDER_DEDUCT'): ?><span class="badge badge-incart">Order</span>
              <?php else: ?><span class="badge badge-low"><?= e($m['movement_type']) ?></span><?php endif; ?>
            </td>
            <td class="num"><strong><?= ($m['quantity_change'] > 0 ? '+' : '') . (int) $m['quantity_change'] ?></strong></td>
            <td class="muted"><?= $m['performed_by_name'] ? e($m['performed_by_name']) : 'System' ?></td>
            <td class="muted small"><?= e($m['remarks'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</details>

</div>
