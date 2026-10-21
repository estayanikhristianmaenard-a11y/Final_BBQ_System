<?php
/**
 * Form security: CSRF tokens and the custom "I am human" verification token.
 *
 * NOTE: the human-verification checkbox is a simple custom check — it is NOT
 * Google reCAPTCHA and is not claimed to be equivalent. Its server-side layers
 * are: a session-bound single-use token + CSRF + rate limiting (rate_limits table).
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verify the CSRF token on a POST. Exits with HTTP 403 on failure. */
function csrf_verify(): void
{
    $posted = $_POST['csrf_token'] ?? '';
    if (!is_string($posted) || $posted === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $posted)) {
        http_response_code(403);
        exit('403 — Invalid or expired form token. Please go back, refresh the page, and try again.');
    }
}

/** Issue a fresh single-use human-verification token (called when rendering forms). */
function human_field(): string
{
    if (empty($_SESSION['human_token'])) {
        $_SESSION['human_token'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="human_token" value="' . e($_SESSION['human_token']) . '">';
}

/**
 * Verify the human-verification checkbox on POST:
 * the checkbox must be ticked AND the single-use token must match the session.
 */
function human_verify(): bool
{
    $checked = !empty($_POST['iam_human']);
    $tokenOk = is_string($_POST['human_token'] ?? '')
        && hash_equals($_SESSION['human_token'] ?? '', $_POST['human_token']);
    unset($_SESSION['human_token']); // single-use: a fresh token is issued on the next render
    return $checked && $tokenOk;
}
