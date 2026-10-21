<?php
/** Staff and manager access uses an invited email and a one-time emailed code. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (!empty($_SESSION['user_id'])) {
    $user = current_user();
    if ($user) redirect(staff_login_destination($user['role']));
}
$pageTitle = 'Staff / Manager Login — BulkBites BBQ';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card auth-card">
  <h1>Staff &amp; manager sign in</h1>
  <?php if (!email_configured()): ?>
    <div class="notice" role="status">Email is not configured yet. On the server PC, run <strong>SETUP-EMAIL.bat</strong> to connect the sender before signing in.</div>
  <?php endif; ?>
  <p class="card-sub">Use your authorized email to receive a 6-digit sign-in code.</p>
  <form method="post" action="<?= e(url('auth/request_otp.php')) ?>">
    <?= csrf_field() ?>
    <?= human_field() ?>
    <input type="hidden" name="login_type" value="staff">
    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" autocomplete="email" maxlength="254" placeholder="you@gmail.com" aria-describedby="email-hint" required autofocus>
      <p class="hint" id="email-hint">Your account must be added by a manager.</p>
    </div>
    <label class="human-box" for="iam_human">
      <input type="checkbox" id="iam_human" name="iam_human" value="1" required>
      <span>I am human</span>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Email my sign-in code</button>
  </form>
  <hr class="auth-divider">
  <p class="auth-switch">Here to order? <a href="<?= e(url('login.php')) ?>">Customer sign in</a></p>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
