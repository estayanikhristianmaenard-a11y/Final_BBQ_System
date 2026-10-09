<?php
/**
 * OTP resend handler (POST only) — re-runs the full request pipeline so the
 * cooldown and the hourly per-phone / per-IP rate limits apply again.
 * (No human checkbox here: the page is only reachable from a verified OTP
 * request session, and the rate limiter is the control that actually bounds
 * abuse — at most 5 codes/hour/number, 10/hour/IP, 60 s apart.)
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(url('login.php'));
}

csrf_verify();

$phone = $_SESSION['otp_phone'] ?? null;
if (!$phone) {
    flash('warning', 'Please request a verification code first.');
    redirect(url('login.php'));
}

$result = otp_request($phone, client_ip());

if ($result['ok']) {
    $_SESSION['otp_resend_after'] = time() + $result['retry_after'];
    $_SESSION['otp_expires_at']   = time() + (int) config('app.otp_expiry_minutes', 5) * 60;
    flash('success', $result['message']);
} else {
    flash('error', $result['message']);
}

redirect(url('auth/verify_otp.php'));
