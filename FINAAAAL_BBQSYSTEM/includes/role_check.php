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

function require_role(string ...$roles): void
{
    require_login();

    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $home = $user ? user_home($user['role']) : url('login.php');
        $label = $user ? $user['role'] : 'Guest';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>403 — Access denied</title>'
            . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head><body>'
            . '<main class="container" style="max-width:520px;margin:64px auto;text-align:center">'
            . '<div class="card">'
            . '<h1 style="margin-top:0;color:var(--red)">403 — Access denied</h1>'
            . '<p>Your account (<strong>' . e($label) . '</strong>) is not allowed to open this page.</p>'
            . '<p class="muted">Pages are protected on the server, so typing a URL directly cannot bypass authorization.</p>'
            . '<p><a class="btn btn-primary" href="' . e($home) . '">Go to my dashboard</a></p>'
            . '</div></main></body></html>';
        exit;
    }
}
