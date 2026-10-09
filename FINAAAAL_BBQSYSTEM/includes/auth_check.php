<?php
/**
 * Login requirement — include bootstrap.php first, then call require_login()
 * at the top of every protected page.
 */

function require_login(): void
{
    // Re-validates against the database; logs out deactivated/missing accounts.
    if (!empty($_SESSION['user_id']) && current_user() === null) {
        // force_logout() already refreshed the session and flashed a warning
    }

    if (empty($_SESSION['user_id'])) {
        if (is_ajax()) {
            http_response_code(401);
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => 'Login required']));
        }
        flash('error', 'Please log in to continue.');
        redirect(url('login.php'));
    }
}
