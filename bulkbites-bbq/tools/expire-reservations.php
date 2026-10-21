<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
try { echo expire_order_reservations(500) . " unpaid reservation(s) expired.\n"; }
catch (Throwable $error) { fwrite(STDERR, "Reservation expiry failed. Check server logs and database access.\n"); exit(1); }
