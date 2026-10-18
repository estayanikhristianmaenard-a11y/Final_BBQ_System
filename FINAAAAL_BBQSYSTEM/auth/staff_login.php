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
  <h1>Staff / Manager login</h1>
  <p class="card-sub">Enter your authorized email address. The code we email you is your one-time password.</p>
  <form method="post" action="<?= e(url('auth/request_otp.php')) ?>">
    <?= csrf_field() ?>
    <?= human_field() ?>
    <input type="hidden" name="login_type" value="staff">
    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" autocomplete="email" maxlength="254" placeholder="you@example.com" required autofocus>
      <p class="hint">Only accounts added by a manager can use this login.</p>
    </div>
    <label class="human-box" for="iam_human">
      <input type="checkbox" id="iam_human" name="iam_human" value="1" required>
      <span>I am human</span>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Email my sign-in code</button>
  </form>
  <div class="divider">— — —</div>
  <p class="center muted">Ordering as a customer? <a href="<?= e(url('login.php')) ?>">Customer login</a></p>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
