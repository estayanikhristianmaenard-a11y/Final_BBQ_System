<?php
/** Manager-only sender connection; subsequent customer emails run automatically. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (empty($_SESSION['user_id']) || current_user() === null) {
    // Fixed server-side destination: guests must reach the manager sign-in,
    // then return here rather than getting trapped in customer email sign-in.
    $_SESSION['staff_login_next'] = 'manager/email.php';
}
require_role('MANAGER');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

$settings = email_settings();
$sender = email_configured() ? (string) $settings['username'] : '';
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'retry_receipts') {
        if (!rate_limit_hit('receipt_retry', 'manager:' . current_user()['id'], 3, 60)) {
            flash('error', 'Please wait a minute before retrying receipt emails again.');
        } else {
            $sent = retry_receipt_emails(5, true);
            flash('success', $sent . ' pending receipt email(s) sent. Any remaining failures stay queued.');
        }
        redirect(url('manager/email.php'));
    }
    $manager = current_user();
    $allowed = rate_limit_hit('email_setup', 'manager:' . $manager['id'], 5, 3600);
    $ipAllowed = rate_limit_hit('email_setup', 'ip:' . client_ip(), 10, 3600);
    if (!$allowed || !$ipAllowed) {
        $error = 'Too many connection attempts. Please try again in about an hour.';
    } else {
        $action = $_POST['action'] ?? '';
        $candidate = null;
        if ($action === 'connect_gmail') {
            $sender = is_string($_POST['sender'] ?? null) ? trim($_POST['sender']) : '';
            $password = is_string($_POST['app_password'] ?? null) ? $_POST['app_password'] : '';
            try {
                $candidate = gmail_settings($sender, $password, $settings);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            unset($password, $_POST['app_password']);
        } elseif ($action === 'test_email' && email_configured()) {
            $candidate = $settings;
        } else {
            $error = 'Connect a sender account first.';
        }
        if ($candidate !== null) {
            $delivery = deliver_system_email($candidate['from_email'], 'BulkBites BBQ email delivery test',
                "Your BulkBites BBQ email delivery test was successful.\n\n"
                . "Customers can request sign-in codes from Customer login. The backend will generate and email each code automatically.\n\n"
                . 'This is a setup test, not a sign-in code.', $candidate);
            if (!$delivery['ok']) {
                $error = email_failure_message($delivery['error_code']) . ' Your saved sender was not changed.';
            } elseif ($action === 'connect_gmail' && !save_email_settings($candidate)) {
                $error = 'The test email was sent, but the sender could not be saved. Please check the server can write its email configuration.';
            } else {
                flash('success', $action === 'connect_gmail'
                    ? 'Gmail connected and a test email was sent. Customer sign-in codes will now be emailed automatically.'
                    : 'Test email sent. Check the sender inbox or Spam folder.');
                redirect(url('manager/email.php'));
            }
            unset($candidate);
        }
    }
}

$pageTitle = 'Email delivery — BulkBites BBQ';
$pendingReceipts = (int) db()->query("SELECT COUNT(*) FROM receipt_email_deliveries WHERE status IN ('PENDING','FAILED')")->fetchColumn();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-head">
  <div>
    <h1>Email delivery</h1>
    <p class="muted">Connect the store's sender once. Customer sign-in emails are sent automatically afterward.</p>
  </div>
</section>
<?php if ($pendingReceipts > 0): ?>
<div class="notice">
  <p><?= $pendingReceipts ?> receipt email(s) are waiting for delivery. The order remains completed.</p>
  <form method="post" action="<?= e(url('manager/email.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="retry_receipts">
    <button class="btn btn-outline" type="submit">Retry pending receipt emails</button>
  </form>
</div>
<?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="dash-grid">
  <section class="card">
    <h2 class="card-title"><?= email_configured() ? 'Sender configured' : 'Connect Gmail' ?></h2>
    <?php if (email_configured()): ?>
      <p>Sending from <strong><?= e($settings['from_email']) ?></strong>.</p>
      <form method="post" action="<?= e(url('manager/email.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test_email">
        <button type="submit" class="btn btn-outline">Send a test email to this inbox</button>
      </form>
      <div class="divider">Update sender</div>
    <?php else: ?>
      <p class="muted">One-time setup for the store owner. Customers only enter their own email and the code they receive.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('manager/email.php')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="connect_gmail">
      <div class="form-group">
        <label for="sender">Sender Google account email</label>
        <input type="email" id="sender" name="sender" maxlength="254" value="<?= e($sender) ?>" placeholder="yourstore@gmail.com" required>
      </div>
      <div class="form-group">
        <label for="app-password">Google App Password</label>
        <input type="password" id="app-password" name="app_password" maxlength="32" autocomplete="new-password" spellcheck="false" <?= email_configured() ? '' : 'required' ?> aria-describedby="password-hint">
        <p id="password-hint" class="hint">Use the 16-letter App Password from Google.<?= email_configured() ? ' Leave blank to keep the saved password for the same sender.' : '' ?></p>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Connect Gmail and send test email</button>
      <p class="hint">The backend checks delivery before saving. Your App Password is never shown again.</p>
    </form>
  </section>
  <section class="card">
    <h2 class="card-title">Get your Google App Password</h2>
    <ol>
      <li>Enable <a href="https://myaccount.google.com/security" target="_blank" rel="noopener noreferrer">2-Step Verification</a> for the sender account.</li>
      <li>Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">Google App Passwords</a> and create one named BulkBites BBQ.</li>
      <li>Enter the sender email and App Password here, then connect.</li>
    </ol>
    <p class="muted">Gmail requires the sender's authorization. You only do this once per server, with no code changes or setup scripts.</p>
    <p class="hint">If your school or work account does not offer App Passwords, use an eligible Gmail account or contact its administrator.</p>
    <a class="btn btn-outline" href="<?= e(url('login.php')) ?>" target="_blank" rel="noopener">Open Customer login</a>
  </section>
</div>
<script>
document.querySelectorAll('form input[name="action"]').forEach(input => {
  input.form.addEventListener('submit', () => {
    const button = input.form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Sending test email…';
  });
});
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
