<?php
/**
 * Inventory management — Phase 7, MANAGER only (architecture §3/§9).
 * Full stock control: restock (+RESTOCK), adjust/waste (signed ADJUSTMENT),
 * thresholds, availability toggle — every quantity change writes an
 * inventory_movements row (performed_by + remarks) inside a guarded UPDATE,
 * and crossing the low-stock threshold notifies active managers (§10).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

$user = current_user();

/** Notify other active managers when stock crosses from IN into LOW/OUT. */
function inventory_low_stock_alert(int $productId, string $beforeState, int $actorId): void
{
    $stmt = db()->prepare(
        'SELECT p.name, i.quantity_available, i.low_stock_threshold, i.is_available
         FROM inventory i JOIN products p ON p.id = i.product_id
         WHERE i.product_id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $afterState = stock_state((int) $row['quantity_available'], (int) $row['low_stock_threshold'], (int) $row['is_available']);
    // Alert only on a degradation: IN → LOW/OUT or LOW → OUT (never on improvement,
    // never repeatedly while already OUT).
    if ($afterState === 'IN' || $afterState === $beforeState || $beforeState === 'OUT') {
        return;
    }
    $managers = db()->query(
        "SELECT id FROM users WHERE role = 'MANAGER' AND is_active = 1"
    )->fetchAll(PDO::FETCH_COLUMN);
    $label = $afterState === 'OUT' ? 'OUT OF STOCK' : 'LOW STOCK';
    foreach ($managers as $managerId) {
        if ((int) $managerId === $actorId) {
            continue; // the acting manager already knows
        }
        notify_user(
            (int) $managerId,
            null,
            'LOW_STOCK',
            $label . ': ' . $row['name'],
            $row['name'] . ' is now ' . $afterState . ' — '
            . (int) $row['quantity_available'] . ' ' . 'left (minimum ' . (int) $row['low_stock_threshold'] . ').'
        );
    }
}

/** Read current row + derive state before an action. */
function inventory_row(int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT i.quantity_available, i.low_stock_threshold, i.is_available
         FROM inventory i WHERE i.product_id = ?'
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['state'] = stock_state(
        (int) $row['quantity_available'],
        (int) $row['low_stock_threshold'],
        (int) $row['is_available']
    );
    return $row;
}

// --- POST actions (PRG) ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $productId = (int) ($_POST['product_id'] ?? 0);
    $back = url('manager/inventory.php');
    $pdo = db();

    if (in_array($action, ['restock', 'adjust'], true)) {
        $before = inventory_row($productId);
        if (!$before) {
            flash('error', 'Product not found.');
            redirect($back);
        }

        $qty = (int) ($_POST['quantity'] ?? 0);
        $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 255);

        if ($qty === 0) {
            flash('error', 'Enter a non-zero quantity.');
            redirect($back);
        }
        if ($action === 'restock' && $qty < 0) {
            flash('error', 'Restock quantity must be positive.');
            redirect($back);
        }
        if ($action === 'adjust' && $qty > 0 && $remarks === '') {
            flash('error', 'Describe what the positive adjustment is for (e.g. “supplier correction”).');
            redirect($back);
        }

        $pdo->beginTransaction();
        try {
            // Guarded change: new balance can never go below zero
            $stmt = $pdo->prepare(
                'UPDATE inventory
                 SET quantity_available = quantity_available + ?,
                     updated_by = ?,
                     last_updated = NOW()
                 WHERE product_id = ? AND quantity_available + ? >= 0'
            );
            $stmt->execute([$qty, (int) $user['id'], $productId, $qty]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Stock is insufficient for that change (would go below zero).');
            }

            $type = $action === 'restock' ? 'RESTOCK' : 'ADJUSTMENT';
            $stmt = $pdo->prepare(
                'INSERT INTO inventory_movements (product_id, movement_type, quantity_change, performed_by, remarks)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$productId, $type, $qty, (int) $user['id'], $remarks !== '' ? $remarks : ($action === 'restock' ? 'Restock' : 'Adjustment')]);

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'Stock change failed — nothing was written.');
            redirect($back);
        }

        inventory_low_stock_alert($productId, $before['state'], (int) $user['id']);
        $after = inventory_row($productId);
        flash('success', 'Stock updated: ' . $before['quantity_available'] . ' → ' . $after['quantity_available'] . ' pcs.');
        redirect($back);
    }

    if ($action === 'threshold') {
        $before = inventory_row($productId);
        if (!$before) {
            flash('error', 'Product not found.');
            redirect($back);
        }
        $threshold = (int) ($_POST['low_stock_threshold'] ?? -1);
        if ($threshold < 0 || $threshold > 999999) {
            flash('error', 'Threshold must be between 0 and 999999.');
            redirect($back);
        }
        $stmt = $pdo->prepare('UPDATE inventory SET low_stock_threshold = ?, updated_by = ? WHERE product_id = ?');
        $stmt->execute([$threshold, (int) $user['id'], $productId]);
        inventory_low_stock_alert($productId, $before['state'], (int) $user['id']);
        flash($stmt->rowCount() > 0 ? 'success' : 'warning',
            $stmt->rowCount() > 0 ? 'Low-stock threshold set to ' . $threshold . '.' : 'Threshold unchanged.');
        redirect($back);
    }

    if ($action === 'toggle_availability') {
        $before = inventory_row($productId);
        if (!$before) {
            flash('error', 'Product not found.');
            redirect($back);
        }
        $stmt = $pdo->prepare(
            'UPDATE inventory SET is_available = 1 - is_available, updated_by = ? WHERE product_id = ?'
        );
        $stmt->execute([(int) $user['id'], $productId]);
        inventory_low_stock_alert($productId, $before['state'], (int) $user['id']);
        $after = inventory_row($productId);
        flash('success', ($after['is_available'] ? 'Marked available.' : 'Marked sold out.') . ' State is now ' . $after['state'] . '.');
        redirect($back);
    }

    flash('error', 'Unknown action.');
    redirect($back);
}

// --- GET data ----------------------------------------------------------------
$rows = staff_inventory_rows();
$movements = manager_recent_movements(15);
$lowCount = count(array_filter($rows, fn($r) => $r['state'] !== 'IN'));

$pageTitle = 'Inventory — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Inventory management</h1>
    <p class="muted">Restock, adjust, set thresholds, and toggle availability · every change is recorded in the stock movement log.</p>
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
  <h2 class="card-title">Stock levels</h2>
  <div class="queue-wrap">
    <table class="data-table inv-table">
      <thead>
        <tr>
          <th>Product</th>
          <th class="num">Available</th>
          <th class="num">Threshold</th>
          <th>State</th>
          <th>Restock / adjust</th>
          <th>Availability</th>
          <th>Last updated</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>
              <strong><?= e($row['name']) ?></strong><br>
              <span class="muted small"><?= e($row['category_name']) ?></span>
              <?php if (!(int) $row['is_active']): ?><span class="badge badge-out">Inactive</span><?php endif; ?>
            </td>
            <td class="num"><strong><?= (int) $row['quantity_available'] ?></strong> <?= e($row['unit']) ?></td>
            <td class="num muted"><?= (int) $row['low_stock_threshold'] ?></td>
            <td>
              <?php if ($row['state'] === 'IN'): ?>
                <span class="badge badge-in">In stock</span>
              <?php elseif ($row['state'] === 'LOW'): ?>
                <span class="badge badge-low">Low stock</span>
              <?php else: ?>
                <span class="badge badge-out">Out of stock</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" action="<?= e(url('manager/inventory.php')) ?>" class="stock-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <input type="number" name="quantity" min="1" max="999999" value="10" required
                       aria-label="Restock quantity for <?= e($row['name']) ?>">
                <input type="text" name="remarks" maxlength="255" placeholder="Remarks (optional)"
                       aria-label="Remarks">
                <button class="btn btn-sm btn-primary" type="submit">Restock</button>
              </form>
              <form method="post" action="<?= e(url('manager/inventory.php')) ?>" class="stock-form"
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
              <form method="post" action="<?= e(url('manager/inventory.php')) ?>" class="stock-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="threshold">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <label class="sr-only" for="thr-<?= (int) $row['id'] ?>">Low-stock threshold</label>
                <input type="number" id="thr-<?= (int) $row['id'] ?>" name="low_stock_threshold"
                       min="0" max="999999" value="<?= (int) $row['low_stock_threshold'] ?>">
                <button class="btn btn-sm btn-outline" type="submit">Set min</button>
              </form>
            </td>
            <td>
              <form method="post" action="<?= e(url('manager/inventory.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_availability">
                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                <button class="btn btn-sm <?= (int) $row['is_available'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"
                  data-confirm="<?= (int) $row['is_available']
                    ? 'Mark ' . e($row['name']) . ' as sold out? It will be hidden from ordering.'
                    : 'Make ' . e($row['name']) . ' available for ordering again?' ?>">
                  <?= (int) $row['is_available'] ? 'Mark sold out' : 'Make available' ?>
                </button>
              </form>
            </td>
            <td class="muted small">
              <?= e(date('M j · g:i A', strtotime($row['last_updated']))) ?>
              <?php if ($row['updated_by_name']): ?><br>by <?= e($row['updated_by_name']) ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <h2 class="card-title">Stock movement log <span class="muted small">(latest 15)</span></h2>
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
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
