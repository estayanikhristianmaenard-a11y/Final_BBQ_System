<?php
/** Managers explicitly add personnel emails. Every account uses emailed codes. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');
$user = current_user();
$pdo = db();
$back = url('manager/users.php');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $role = strtoupper((string) ($_POST['role'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !valid_login_email($email)
            || !in_array($role, ['STAFF', 'MANAGER'], true)) {
            flash('error', 'Enter a name, valid email, and STAFF or MANAGER role.');
            redirect($back);
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? FOR UPDATE');
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            if ($existing && $existing['role'] !== 'CUSTOMER') {
                $pdo->rollBack();
                flash('error', 'That email is already a personnel account. Use its activation control below.');
                redirect($back);
            }
            if ($existing) {
                $pdo->prepare('UPDATE users SET full_name = ?, role = ?, password_hash = NULL, is_active = 1 WHERE id = ?')
                    ->execute([$name, $role, $existing['id']]);
            } else {
                $pdo->prepare('INSERT INTO users (full_name, email, phone, role, password_hash) VALUES (?, ?, NULL, ?, NULL)')
                    ->execute([$name, $email, $role]);
            }
            $pdo->commit();
            flash('success', $role . ' account added. They can sign in with their email and a one-time code.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not add that account. It may already exist.');
        }
        redirect($back);
    }
    if ($action === 'toggle') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === (int) $user['id']) {
            flash('error', 'You cannot deactivate your own account.');
            redirect($back);
        }
        $stmt = $pdo->prepare('SELECT role, is_active FROM users WHERE id = ? AND email IS NOT NULL AND role IN ("STAFF","MANAGER")');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'Email account not found.');
            redirect($back);
        }
        if ((int) $target['is_active'] && $target['role'] === 'MANAGER'
            && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'MANAGER' AND is_active = 1 AND email IS NOT NULL")->fetchColumn() <= 1) {
            flash('error', 'Keep at least one active manager.');
            redirect($back);
        }
        $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([(int) $target['is_active'] ? 0 : 1, $targetId]);
        flash('success', (int) $target['is_active'] ? 'Account deactivated.' : 'Account reactivated.');
        redirect($back);
    }
    flash('error', 'Unknown account action.');
    redirect($back);
}
$accounts = manager_user_rows();
$pageTitle = 'Staff accounts — BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-head">
  <div><h1>Staff &amp; managers</h1><p class="muted">Only emails added here can access the staff or manager login. All accounts use emailed one-time codes.</p></div>
</section>
<div class="crud-grid">
  <section class="card">
    <h2 class="card-title">Add an authorized email</h2>
    <form method="post" action="<?= e($back) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label for="u-name">Full name</label><input type="text" id="u-name" name="full_name" minlength="2" maxlength="100" required></div>
      <div class="form-group"><label for="u-email">Email address</label><input type="email" id="u-email" name="email" maxlength="254" required></div>
      <div class="form-group">
        <label for="u-role">Role</label>
        <select id="u-role" name="role" required>
          <option value="STAFF">STAFF — kitchen, orders, inventory</option>
          <option value="MANAGER">MANAGER — full access</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Add account</button>
      <p class="hint">No permanent password is needed. Adding a customer's existing email authorizes that account for the selected role.</p>
    </form>
  </section>
  <section class="card" style="overflow-x:auto">
    <h2 class="card-title">Authorized accounts — <?= count($accounts) ?></h2>
    <table class="data-table">
      <thead><tr><th>Name / email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $account): ?>
        <tr>
          <td><strong><?= e($account['full_name']) ?></strong><br><?= e($account['email']) ?></td>
          <td><?= e($account['role']) ?></td>
          <td><?= (int) $account['is_active'] ? 'Active' : 'Inactive' ?></td>
          <td><form method="post" action="<?= e($back) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
            <button class="btn btn-sm btn-outline" type="submit" <?= (int) $account['id'] === (int) $user['id'] ? 'disabled' : '' ?>><?= (int) $account['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
          </form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
