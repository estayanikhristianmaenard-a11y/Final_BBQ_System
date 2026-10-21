<?php
/** Compatibility endpoint for existing stock forms and bookmarks. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('MANAGER');
$user = current_user();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require dirname(__DIR__) . '/includes/manager-stock-actions.php';
}
redirect(url('manager/products.php') . '#stock');
