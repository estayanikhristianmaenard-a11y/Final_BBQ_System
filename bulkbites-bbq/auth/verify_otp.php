<?php
/**
 * OTP verification — Step 2: enter the 6-digit code.
 * Enforces expiry, attempt limit, and per-email rate limiting server-side.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$email = $_SESSION['otp_email'] ?? null;
$purpose = ($_SESSION['otp_purpose'] ?? '') === 'STAFF' ? 'STAFF' : 'CUSTOMER';
$loginPage = $purpose === 'STAFF' ? 'auth/staff_login.php' : 'login.php';
if (!$email) {
    flash('warning', 'Please request a verification code first.');
    redirect(url($loginPage));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $code = trim($_POST['code'] ?? '');
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        flash('error', 'Please enter the 6-digit verification code.');
        redirect(url('auth/verify_otp.php'));
    }

    $result = otp_verify($email, $code, $purpose);
    if ($result['ok']) {
        $login = complete_otp_login($email, $purpose);
        if ($login['ok']) {
            if ($purpose === 'STAFF') {
                $_SESSION['personnel_password_proof'] = ['user_id'=>(int)$_SESSION['user_id'], 'expires_at'=>time()+600];
                redirect(url('auth/staff_password.php'));
            }
            flash('success', 'Welcome! You are now logged in.');
            redirect($purpose === 'STAFF' ? staff_login_destination($_SESSION['role']) : customer_login_destination($_SESSION['role']));
        }
        flash('error', $login['message']);
        redirect(url($loginPage));
    }

    flash('error', $result['message']);
    redirect(url('auth/verify_otp.php'));
}

$resendIn       = max(0, (int) ($_SESSION['otp_resend_after'] ?? 0) - time());
$expiresIn      = max(0, (int) ($_SESSION['otp_expires_at'] ?? 0) - time());
$pageTitle      = 'Verify Code — BulkBites BBQ';
$customerExperience = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card">
  <h1>Enter verification code</h1>
  <p class="card-sub">
    We emailed a 6-digit code to <strong><?= e(mask_email($email)) ?></strong>.
    It expires in <strong><?= (int) ceil($expiresIn / 60) ?> minute(s)</strong>.
  </p>

  <p class="hint">Check your inbox and spam folder. Only the most recent code works.</p>

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
    <button class="btn btn-outline btn-block" type="submit" data-resend-seconds="<?= (int) $resendIn ?>" <?= $resendIn > 0 ? 'disabled' : '' ?>>
      <?= $resendIn > 0 ? 'Resend code (wait ' . (int) $resendIn . 's)' : 'Resend code' ?>
    </button>
  </form>

  <div class="divider">— — —</div>
  <p class="center muted" style="margin-bottom:0">
    <a href="<?= e(url($loginPage)) ?>">Use a different email</a>
  </p>
</div>
<script>
(() => {
  const button = document.querySelector('[data-resend-seconds]');
  let remaining = Number(button.dataset.resendSeconds);
  if (remaining <= 0) return;
  const timer = setInterval(() => {
    remaining = Math.max(0, remaining - 1);
    button.disabled = remaining > 0;
    button.textContent = remaining > 0 ? `Resend code (wait ${remaining}s)` : 'Resend code';
    if (remaining === 0) clearInterval(timer);
  }, 1000);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
