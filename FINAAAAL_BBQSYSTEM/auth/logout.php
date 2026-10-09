<?php
/**
 * Logout handler — destroys the session completely, then flashes a notice
 * from a brand-new session. Accepts POST (with CSRF) from the header button.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    destroy_session();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    flash('success', 'You have been logged out.');
}

redirect(url('index.php'));
