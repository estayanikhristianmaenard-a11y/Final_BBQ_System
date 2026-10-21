<?php
/** Optional scheduled retry worker: php tools/send-receipts.php */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
echo retry_receipt_emails(10) . " receipt email(s) sent.\n";
