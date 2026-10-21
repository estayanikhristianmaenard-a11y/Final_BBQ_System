<?php
/**
 * Role-based authorization — the server-side gate that makes "typing the URL"
 * useless for unauthorized roles. Call at the top of every protected page:
 *
 *   require_role('CUSTOMER');            // customers only
 *   require_role('STAFF', 'MANAGER');    // staff and managers (not customers)
 *   require_role('MANAGER');             // managers only
 *
 * The role is re-read from the database (current_user()), never trusted from
 * the client or from stale session data.
 */

/** Guests can shop; authenticated operational accounts keep their role restrictions. */
function require_customer_or_guest(): void
{
    if (current_user()) {
        require_role('CUSTOMER');
    }
}

function require_role(string ...$roles): void
{
    $staffOnly = !in_array('CUSTOMER', $roles, true)
        && (bool) array_intersect(['STAFF', 'MANAGER'], $roles);
    require_login($staffOnly ? 'auth/staff_login.php' : 'login.php');

    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $home = $user ? user_home($user['role']) : url('login.php');
        $pageTitle = 'Access denied — BulkBites BBQ';
        require __DIR__ . '/header.php';
        echo '<section class="card auth-card">'
            . '<h1>Access denied</h1>'
            . '<p class="card-sub">Your account cannot open this page. Return to your menu or workspace to continue.</p>'
            . '<a class="btn btn-primary btn-block" href="' . e($home) . '">Back to my workspace</a>'
            . '</section>';
        require __DIR__ . '/footer.php';
        exit;
    }
    expire_order_reservations();
}
