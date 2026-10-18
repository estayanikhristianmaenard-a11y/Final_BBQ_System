<?php
require __DIR__ . '/database-transfer.php';
require dirname(__DIR__) . '/includes/email-migration.php';
try {
    $config = transfer_config();
    $pdo = transfer_connection($config);
    $pdo->exec('USE ' . transfer_identifier($config['name']));
    migrate_email_login($pdo);
    migrate_receipt_emails($pdo);
    provision_default_managers($pdo);
    echo "Email login database upgrade complete. Existing accounts and orders were preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Email login upgrade failed: ' . $error->getMessage() . "\n");
    exit(1);
}
