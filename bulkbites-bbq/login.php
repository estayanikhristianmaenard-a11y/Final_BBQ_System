<?php
/**
 * Customer login — Step 1: email address + human verification.
 * The form POSTs to request_otp.php.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $u = current_user();
    redirect($u ? customer_login_destination($u['role']) : url('index.php'));
}

$pageTitle = 'Customer Login — BulkBites BBQ';
$customerExperience = true;
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card">
  <h1>Sign in to order</h1>
  <?php if (!email_configured()): ?>
    <div class="notice" role="status">
      Email sign-in is waiting for the store to connect its email sender.
    </div>
  <?php endif; ?>
  <p class="card-sub">We'll email you a 6-digit sign-in code. Your cart stays saved.</p>

  <form method="post" action="<?= e(url('auth/request_otp.php')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <?= human_field() ?>

    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" autocomplete="email" maxlength="254"
             placeholder="you@gmail.com" aria-describedby="email-hint" required autofocus>
      <div class="hint" id="email-hint">Use an inbox you can open. No password needed.</div>
    </div>

    <label class="human-box" for="iam_human">
      <input type="checkbox" id="iam_human" name="iam_human" value="1" required>
      <span>I am human</span>
    </label>

    <button class="btn btn-primary btn-block" type="submit">Email my sign-in code</button>
  </form>

  <hr class="auth-divider">
  <p class="auth-switch">
    Staff or manager? <a href="<?= e(url('auth/staff_login.php')) ?>">Sign in here</a>
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
