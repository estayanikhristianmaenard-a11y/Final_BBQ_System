<?php
/** Staff and manager password sign-in, with email recovery for existing accounts. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!empty($_SESSION['user_id'])) {
    $user = current_user();
    if ($user) redirect(staff_login_destination($user['role']));
}
$codeMode = ($_GET['mode'] ?? '') === 'code';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $codeMode = false;
    $email = normalize_email((string)($_POST['email'] ?? ''));
    $result = personnel_password_login($email, (string)($_POST['password'] ?? ''), client_ip());
    if ($result['ok']) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['role'] = $result['role'];
        $_SESSION['login_at'] = $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf_token'], $_SESSION['human_token'], $_SESSION['debug_otp'], $_SESSION['verified_otp_email'],
            $_SESSION['otp_email'], $_SESSION['otp_purpose'], $_SESSION['otp_resend_after'], $_SESSION['otp_expires_at'], $_SESSION['personnel_password_proof']);
        redirect(staff_login_destination($result['role']));
    }
    flash('error', $result['message']);
}
$pageTitle = 'Staff / Manager Login - BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card auth-card">
  <h1><?= $codeMode ? 'Set or reset password' : 'Staff &amp; manager sign in' ?></h1>
  <p class="card-sub"><?= $codeMode ? 'Verify your authorized email once to choose a password.' : 'Sign in with your authorized email and password.' ?></p>
  <?php if ($codeMode && !email_configured()): ?><div class="notice" role="status">Email setup is needed for password recovery. Ask a manager to set your password in Staff accounts.</div><?php endif; ?>
  <form method="post" action="<?= e(url($codeMode ? 'auth/request_otp.php' : 'auth/staff_login.php')) ?>">
    <?= csrf_field() ?>
    <?php if ($codeMode): ?><?= human_field() ?><input type="hidden" name="login_type" value="staff"><?php endif; ?>
    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" maxlength="254" placeholder="you@gmail.com" required autofocus>
    </div>
    <?php if ($codeMode): ?>
      <label class="human-box" for="iam_human"><input type="checkbox" id="iam_human" name="iam_human" value="1" required><span>I am human</span></label>
    <?php else: ?>
      <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="current-password" maxlength="72" required></div>
    <?php endif; ?>
    <button class="btn btn-primary btn-block" type="submit"><?= $codeMode ? 'Email verification code' : 'Sign in' ?></button>
  </form>
  <p class="auth-switch"><a href="<?= e(url('auth/staff_login.php').($codeMode ? '' : '?mode=code')) ?>"><?= $codeMode ? 'Back to password sign in' : 'Set or reset password' ?></a></p>
  <hr class="auth-divider">
  <p class="auth-switch">Here to order? <a href="<?= e(url('login.php')) ?>">Customer sign in</a></p>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
