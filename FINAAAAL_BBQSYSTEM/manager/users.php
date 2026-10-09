<?php
/**
 * Staff / manager account management — Phase 7, MANAGER only (architecture §3).
 * Create accounts (bcrypt), activate/deactivate, reset passwords. Accounts are
 * never deleted (orders/history reference them) — deactivation locks the user
 * out on their next request because current_user() re-reads is_active.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');

$user = current_user();

// --- POST actions (PRG) ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $back = url('manager/users.php');
    $pdo = db();

    if ($action === 'create') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = strtoupper((string) ($_POST['role'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            flash('error', 'Full name must be 2–100 characters.');
            redirect($back);
        }
        if (!preg_match('/^09[0-9]{9}$/', $phone)) {
            flash('error', 'Mobile number must look like 09XXXXXXXXX.');
            redirect($back);
        }
        if (!in_array($role, ['STAFF', 'MANAGER'], true)) {
            flash('error', 'Role must be STAFF or MANAGER.');
            redirect($back);
        }
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect($back);
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn()) {
            flash('error', 'That mobile number is already registered.');
            redirect($back);
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, phone, role, password_hash, is_active)
                 VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([$fullName, $phone, $role, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', $role . ' account created for ' . $fullName . ' (' . mask_phone($phone) . ').');
        } catch (PDOException $ex) {
            flash('error', 'Could not create the account (duplicate number?).');
        }
        redirect($back);
    }

    if ($action === 'toggle') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === (int) $user['id']) {
            flash('error', 'You cannot deactivate your own account.');
            redirect($back);
        }
        $stmt = $pdo->prepare('SELECT role, is_active FROM users WHERE id = ? AND role IN ("STAFF","MANAGER")');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'Account not found.');
            redirect($back);
        }
        if ((int) $target['is_active'] === 1) {
            // Never lock the business out of its last active manager
            if ($target['role'] === 'MANAGER') {
                $others = $pdo->query(
                    "SELECT COUNT(*) FROM users WHERE role = 'MANAGER' AND is_active = 1"
                )->fetchColumn();
                if ((int) $others <= 1) {
                    flash('error', 'This is the last active manager — create another manager first.');
                    redirect($back);
                }
            }
            $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?');
            $stmt->execute([$targetId]);
            flash('success', 'Account deactivated — they will be signed out on their next request.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?');
            $stmt->execute([$targetId]);
            flash('success', 'Account reactivated.');
        }
        redirect($back);
    }

    if ($action === 'reset_password') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            flash('error', 'New password must be at least 8 characters.');
            redirect($back);
        }
        $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE id = ? AND role IN ("STAFF","MANAGER")');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'Account not found.');
            redirect($back);
        }
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $targetId]);
        flash('success', 'Password reset for ' . $target['full_name'] . '.');
        redirect($back);
    }

    flash('error', 'Unknown action.');
    redirect($back);
}

// --- GET data ----------------------------------------------------------------
$accounts = manager_user_rows();

$pageTitle = 'Staff accounts — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Staff &amp; user management</h1>
    <p class="muted">Create, deactivate, and reset passwords for STAFF/MANAGER accounts · passwords are stored as bcrypt hashes.</p>
  </div>
  <a class="btn btn-outline" href="<?= e(url('manager/customers.php')) ?>">&#128101; Customers</a>
</section>

<div class="crud-grid">
  <div class="card">
    <h2 class="card-title">Create an account</h2>
    <form method="post" action="<?= e(url('manager/users.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="form-group">
        <label for="u-name">Full name</label>
        <input type="text" id="u-name" name="full_name" maxlength="100" required placeholder="Juan Dela Cruz">
      </div>
      <div class="form-group">
        <label for="u-phone">Mobile number</label>
        <input type="tel" id="u-phone" name="phone" pattern="09[0-9]{9}" maxlength="11" required placeholder="09XXXXXXXXX">
      </div>
      <div class="form-group">
        <label for="u-role">Role</label>
        <select id="u-role" name="role" required>
          <option value="STAFF">STAFF — kitchen, orders, read-only inventory</option>
          <option value="MANAGER">MANAGER — full access including reports</option>
        </select>
      </div>
      <div class="form-group">
        <label for="u-pass">Password <span class="muted small">(min 8 characters)</span></label>
        <div class="password-wrap">
          <input type="password" id="u-pass" name="password" minlength="8" required autocomplete="new-password">
          <button type="button" class="password-toggle" data-toggle-password="u-pass"
                  aria-label="Show password" title="Show/hide password">&#128065;</button>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Create account</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 class="card-title">Accounts — <?= count($accounts) ?></h2>
    <table class="data-table">
      <thead>
        <tr><th>Name</th><th>Login</th><th>Role</th><th class="num">Handled</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $row): ?>
          <tr<?= !(int) $row['is_active'] ? ' class="row-muted"' : '' ?>>
            <td>
              <strong><?= e($row['full_name']) ?></strong>
              <?php if ((int) $row['id'] === (int) $user['id']): ?><span class="badge badge-role">You</span><?php endif; ?>
              <br><span class="muted small">since <?= e(date('M j, Y', strtotime($row['created_at']))) ?></span>
            </td>
            <td><?= e($row['phone']) ?></td>
            <td>
              <?php if ($row['role'] === 'MANAGER'): ?><span class="badge badge-role">MANAGER</span>
              <?php else: ?><span class="badge badge-incart">STAFF</span><?php endif; ?>
            </td>
            <td class="num"><?= (int) $row['handled_n'] ?> order(s)</td>
            <td>
              <?php if ((int) $row['is_active']): ?><span class="badge badge-in">Active</span>
              <?php else: ?><span class="badge badge-out">Inactive</span><?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <form method="post" action="<?= e(url('manager/users.php')) ?>"
                      <?= (int) $row['id'] !== (int) $user['id'] && (int) $row['is_active']
                          ? 'data-confirm="Deactivate ' . e($row['full_name']) . '? They will be locked out immediately."' : '' ?>>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-sm <?= (int) $row['is_active'] ? 'btn-danger' : 'btn-primary' ?>" type="submit"
                    <?= (int) $row['id'] === (int) $user['id'] ? 'disabled title="You cannot deactivate yourself"' : '' ?>>
                    <?= (int) $row['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                  </button>
                </form>
                <details class="reset-details">
                  <summary class="btn btn-sm btn-outline">Reset password</summary>
                  <form method="post" action="<?= e(url('manager/users.php')) ?>" class="reset-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                    <input type="password" name="password" minlength="8" required placeholder="New password" autocomplete="new-password">
                    <button class="btn btn-sm btn-primary" type="submit">Save</button>
                  </form>
                </details>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted small" style="margin-top:10px">
      Customer accounts are created by OTP login and are managed from the Customers page (read-only here).
    </p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
