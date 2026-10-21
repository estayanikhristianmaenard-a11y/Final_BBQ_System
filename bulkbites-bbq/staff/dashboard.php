<?php
/** Compatibility link: the main task replaces the separate dashboard. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('STAFF', 'MANAGER');
redirect(url(current_user()['role']==='MANAGER' ? 'manager/dashboard.php' : 'staff/kitchen.php'));
