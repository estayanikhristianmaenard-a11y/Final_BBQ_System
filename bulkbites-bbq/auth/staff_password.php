<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF','MANAGER');
$user = current_user();
$proof = $_SESSION['personnel_password_proof'] ?? [];
if ((int)($proof['user_id'] ?? 0) !== (int)$user['id'] || (int)($proof['expires_at'] ?? 0) <= time()) {
    unset($_SESSION['personnel_password_proof']);
    flash('warning', 'Email verification expired. A manager can set your password under Staff accounts.');
    redirect(staff_login_destination($user['role']));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $password = (string)($_POST['password'] ?? '');
    if ($password !== (string)($_POST['password_confirm'] ?? '')) {
        flash('error', 'Passwords do not match.');
    } else {
        $result = personnel_password_save((int)$user['id'], $password, (int)$user['id']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok']) { session_regenerate_id(true); redirect(staff_login_destination($user['role'])); }
    }
    redirect(url('auth/staff_password.php'));
}
$pageTitle = 'Set password - BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card auth-card">
  <h1>Choose your password</h1>
  <p class="card-sub">Next time, use your email and password to sign in.</p>
  <form method="post" action="<?= e(url('auth/staff_password.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group"><label for="password">New password</label><input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required autofocus><p class="hint">Use 8-72 characters.</p></div>
    <div class="form-group"><label for="password-confirm">Confirm password</label><input type="password" id="password-confirm" name="password_confirm" autocomplete="new-password" minlength="8" maxlength="72" required></div>
    <button class="btn btn-primary btn-block" type="submit">Save password</button>
  </form>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
