<?php
/**
 * Customer login — Step 1: phone number + human verification.
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
  <p class="card-sub">Sign in with your mobile number. We'll send a one-time PIN (OTP).</p>

  <form method="post" action="<?= e(url('auth/request_otp.php')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <?= human_field() ?>

    <div class="form-group">
      <label for="phone">Mobile number</label>
      <input type="tel" id="phone" name="phone" inputmode="numeric" maxlength="11"
             placeholder="09XXXXXXXXX" value="<?= e($_POST['phone'] ?? '') ?>"
             pattern="09[0-9]{9}" required autofocus>
      <div class="hint">Philippine mobile number starting with 09 (11 digits).</div>
    </div>

    <label class="human-box" for="iam_human">
      <input type="checkbox" id="iam_human" name="iam_human" value="1" required>
      <span>I am human</span>
    </label>
    <div class="human-note">Simple human check (custom, not Google reCAPTCHA), backed by server-side validation and rate limiting.</div>

    <button class="btn btn-primary btn-block" type="submit">Send verification code</button>
  </form>

  <div class="divider">— — —</div>
  <p class="center muted" style="margin-bottom:0">
    Staff or manager? <a href="<?= e(url('auth/staff_login.php')) ?>">Use the Staff/Manager login</a>
  </p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
