<?php
/** Staff workspace; managers use the combined dashboard. */
require_once dirname(__DIR__).'/includes/bootstrap.php';
require_role('STAFF','MANAGER');
if (current_user()['role'] === 'MANAGER') redirect(url('manager/dashboard.php'));
require dirname(__DIR__).'/includes/operations-dashboard.php';
