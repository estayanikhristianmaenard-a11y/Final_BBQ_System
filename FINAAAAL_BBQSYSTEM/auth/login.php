<?php
/**
 * Customer login — Step 1: email address + human verification.
 * The form POSTs to request_otp.php.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $u = current_user();
    redirect($u ? user_home($u['role']) : url('index.php'));
}

$pageTitle = 'Customer Login — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card">
  <h1>Customer login</h1>
  <?php if (!email_configured()): ?>
    <div class="notice" role="status">
      Email sign-in is waiting for the store to connect its email sender.
      <a href="<?= e(url('manager/email.php')) ?>">Store owner? Set up email delivery</a>.
    </div>
  <?php endif; ?>
  <p class="card-sub">Enter your email address. We'll email a 6-digit code to use as your one-time password.</p>

  <form method="post" action="<?= e(url('auth/request_otp.php')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <?= human_field() ?>

    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" autocomplete="email" maxlength="254"
             placeholder="you@example.com" required autofocus>
      <div class="hint">Use an email inbox you can open. No permanent password is needed.</div>
    </div>

    <label class="human-box" for="iam_human">
      <input type="checkbox" id="iam_human" name="iam_human" value="1" required>
      <span>I am human</span>
    </label>

    <button class="btn btn-primary btn-block" type="submit">Email my sign-in code</button>
  </form>

  <div class="divider">— — —</div>
  <p class="center muted" style="margin-bottom:0">
    Staff or manager? <a href="<?= e(url('auth/staff_login.php')) ?>">Use the Staff/Manager login</a>
  </p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
