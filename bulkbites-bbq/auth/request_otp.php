<?php
/**
 * OTP request handler: CSRF, human verification, email validation, and real delivery.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$purpose = ($_POST['login_type'] ?? '') === 'staff' ? 'STAFF' : 'CUSTOMER';
$loginPage = $purpose === 'STAFF' ? 'auth/staff_login.php?mode=code' : 'login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(url($loginPage));
}

csrf_verify();

if (!human_verify()) {
    flash('error', 'Please complete the human verification checkbox and try again.');
    redirect(url($loginPage));
}

$email = normalize_email((string) ($_POST['email'] ?? ''));
if (!valid_login_email($email)) {
    flash('error', 'Please enter a valid email address.');
    redirect(url($loginPage));
}

$result = otp_request($email, client_ip(), $purpose);

if ($result['ok']) {
    $_SESSION['otp_email']         = $email;
    $_SESSION['otp_purpose']       = $purpose;
    $_SESSION['otp_resend_after']  = time() + $result['retry_after'];
    $_SESSION['otp_expires_at']    = time() + (int) config('app.otp_expiry_minutes', 5) * 60;
    flash('success', $result['message']);
    redirect(url('auth/verify_otp.php'));
}

flash('error', $result['message']);
redirect(url($loginPage));
