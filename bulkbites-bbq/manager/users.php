<?php
/** Managers authorize personnel accounts and manage their passwords. */
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
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || !valid_login_email($email)
            || !in_array($role, ['STAFF', 'MANAGER'], true) || !personnel_password_valid($password)) {
            flash('error', 'Enter a name, valid email, role, and password of 8-72 characters.');
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
                $pdo->prepare('UPDATE users SET full_name = ?, role = ?, password_hash = ?, is_active = 1 WHERE id = ?')
                    ->execute([$name, $role, password_hash($password, PASSWORD_DEFAULT), $existing['id']]);
            } else {
                $pdo->prepare('INSERT INTO users (full_name, email, phone, role, password_hash) VALUES (?, ?, NULL, ?, ?)')
                    ->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT)]);
            }
            $pdo->commit();
            flash('success', $role . ' account added. They can sign in with their email and password.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not add that account. It may already exist.');
        }
        redirect($back);
    }
    if ($action === 'password') {
        $result = personnel_password_save((int)($_POST['user_id'] ?? 0), (string)($_POST['password'] ?? ''), (int)$user['id']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
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
  <div><h1>Staff &amp; managers</h1><p class="muted">Only emails added here can access the staff or manager login. Staff and managers sign in with email and password.</p></div>
</section>
<?php require dirname(__DIR__) . '/includes/settings-nav.php'; ?>
<div class="crud-grid">
  <details class="card record-create" data-editing="false" open>
    <summary>Add staff or manager</summary>
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
      <div class="form-group"><label for="u-password">Password</label><input type="password" id="u-password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required><p class="hint">Use 8-72 characters. Share the password directly with the account owner.</p></div>
      <button class="btn btn-primary" type="submit">Add account</button>
      <p class="hint">Adding a customer's existing email authorizes that account for the selected role.</p>
    </form>
  </details>
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
          </form>
          <details class="mt"><summary>Set password</summary>
            <form method="post" action="<?= e($back) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="password">
              <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
              <div class="form-group"><label for="password-<?= (int)$account['id'] ?>">New password</label><input type="password" id="password-<?= (int)$account['id'] ?>" name="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
              <button class="btn btn-sm btn-primary" type="submit">Save password</button>
            </form>
          </details></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
