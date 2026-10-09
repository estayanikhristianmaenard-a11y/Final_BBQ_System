<?php
/**
 * OTP request handler (POST only): CSRF + human verification + phone format +
 * rate limits + resend cooldown, then generates and "sends" the code.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(url('login.php'));
}

csrf_verify();

if (!human_verify()) {
    flash('error', 'Please complete the human verification checkbox and try again.');
    redirect(url('login.php'));
}

$phone = trim($_POST['phone'] ?? '');
if (!preg_match('/^09[0-9]{9}$/', $phone)) {
    flash('error', 'Please enter a valid 11-digit mobile number starting with 09.');
    redirect(url('login.php'));
}

$result = otp_request($phone, client_ip());

if ($result['ok']) {
    $_SESSION['otp_phone']         = $phone;
    $_SESSION['otp_resend_after']  = time() + $result['retry_after'];
    $_SESSION['otp_expires_at']    = time() + (int) config('app.otp_expiry_minutes', 5) * 60;
    flash('success', $result['message']);
    redirect(url('auth/verify_otp.php'));
}

flash('error', $result['message']);
redirect(url('login.php'));
