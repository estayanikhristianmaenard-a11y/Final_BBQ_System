<?php
/** Compatibility link: the main task replaces the separate dashboard. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_role('CUSTOMER');
redirect(url('customer/menu.php'));
