<?php
/**
 * Staff / Manager login — phone + password, with the password visibility
 * toggle, human verification, CSRF, and failed-attempt throttling.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $u = current_user();
    redirect($u ? user_home($u['role']) : url('index.php'));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    if (!human_verify()) {
        flash('error', 'Please complete the human verification checkbox and try again.');
        redirect(url('auth/staff_login.php'));
    }

    $phone    = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^09[0-9]{9}$/', $phone)) {
        flash('error', 'Please enter a valid 11-digit mobile number starting with 09.');
        redirect(url('auth/staff_login.php'));
    }

    // Throttle: per phone and per IP (counts every attempt, success or not)
    $ip       = client_ip();
    $phoneOk  = rate_limit_hit('staff_login', 'ph:' . $phone, (int) config('app.staff_login_max_per_15min', 5), 900);
    $ipOk     = rate_limit_hit('staff_login', 'ip:' . $ip, (int) config('app.staff_login_ip_max_per_15min', 10), 900);
    if (!$phoneOk || !$ipOk) {
        flash('error', 'Too many login attempts. Please wait 15 minutes and try again.');
        redirect(url('auth/staff_login.php'));
    }

    $stmt = db()->prepare('SELECT id, full_name, role, password_hash, is_active FROM users WHERE phone = ?');
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    $valid = $user
        && in_array($user['role'], ['STAFF', 'MANAGER'], true)
        && (int) $user['is_active'] === 1
        && $user['password_hash'] !== null
        && password_verify($password, $user['password_hash']);

    if (!$valid) {
        flash('error', 'Invalid mobile number or password.');
        redirect(url('auth/staff_login.php'));
    }

    // Success — fresh session on privilege change (anti session-fixation)
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['login_at']      = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token'], $_SESSION['human_token']);

    flash('success', 'Welcome back, ' . $user['full_name'] . '!');
    redirect(user_home($user['role']));
}

$pageTitle = 'Staff Login — BulkBites BBQ';
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card">
  <h1>Staff / Manager login</h1>
  <p class="card-sub">Authorized BulkBites BBQ personnel only.</p>

  <form method="post" action="<?= e(url('auth/staff_login.php')) ?>">
    <?= csrf_field() ?>
    <?= human_field() ?>

    <div class="form-group">
      <label for="phone">Mobile number</label>
      <input type="tel" id="phone" name="phone" inputmode="numeric" maxlength="11"
             placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" required autofocus>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="password-wrap">
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="button" class="password-toggle" data-toggle-password="password"
                aria-label="Show password" title="Show/hide password">&#128065;</button>
      </div>
    </div>

    <label class="human-box" for="iam_human">
      <input type="checkbox" id="iam_human" name="iam_human" value="1" required>
      <span>I am human</span>
    </label>
    <div class="human-note">Simple human check (custom, not Google reCAPTCHA), backed by server-side validation and rate limiting.</div>

    <button class="btn btn-dark btn-block" type="submit">Log in</button>
  </form>

  <div class="divider">— — —</div>
  <p class="center muted" style="margin-bottom:0">
    Ordering as a customer? <a href="<?= e(url('login.php')) ?>">Use the OTP login</a>
  </p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
