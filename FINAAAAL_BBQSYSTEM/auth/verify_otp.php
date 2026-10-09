<?php
/**
 * OTP verification — Step 2: enter the 6-digit code.
 * Enforces expiry, attempt limit, and per-phone rate limiting server-side.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$phone = $_SESSION['otp_phone'] ?? null;
if (!$phone) {
    flash('warning', 'Please request a verification code first.');
    redirect(url('login.php'));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $code = trim($_POST['code'] ?? '');
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        flash('error', 'Please enter the 6-digit verification code.');
        redirect(url('auth/verify_otp.php'));
    }

    $result = otp_verify($phone, $code);
    if ($result['ok']) {
        $login = complete_otp_login($phone);
        if ($login['ok']) {
            flash('success', 'Welcome! You are now logged in.');
            redirect(url('customer/dashboard.php'));
        }
        flash('error', $login['message']);
        redirect(url('login.php'));
    }

    flash('error', $result['message']);
    redirect(url('auth/verify_otp.php'));
}

$debugOtp       = otp_debug_mode() ? ($_SESSION['debug_otp'] ?? null) : null;
$resendIn       = max(0, (int) ($_SESSION['otp_resend_after'] ?? 0) - time());
$expiresIn      = max(0, (int) ($_SESSION['otp_expires_at'] ?? 0) - time());
$pageTitle      = 'Verify Code — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card">
  <h1>Enter verification code</h1>
  <p class="card-sub">
    We sent a 6-digit code to <strong><?= e(mask_phone($phone)) ?></strong>.
    It expires in <strong><?= (int) ceil($expiresIn / 60) ?> minute(s)</strong>.
  </p>

  <?php if ($debugOtp): ?>
    <div class="notice">
      <strong>Development mode</strong> (no SMS service): your code is
      <code><?= e($debugOtp['code']) ?></code>
      — also written to <code>logs/otp_debug.log</code>.
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('auth/verify_otp.php')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="code">Verification code</label>
      <input class="otp-input" type="text" id="code" name="code" inputmode="numeric"
             maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="one-time-code" required autofocus>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Verify and log in</button>
  </form>

  <form method="post" action="<?= e(url('auth/resend_otp.php')) ?>" class="mt">
    <?= csrf_field() ?>
    <button class="btn btn-outline btn-block" type="submit" <?= $resendIn > 0 ? 'disabled' : '' ?>>
      <?= $resendIn > 0 ? 'Resend code (wait ' . (int) $resendIn . 's)' : 'Resend code' ?>
    </button>
  </form>

  <div class="divider">— — —</div>
  <p class="center muted" style="margin-bottom:0">
    <a href="<?= e(url('login.php')) ?>">Use a different number</a>
  </p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
