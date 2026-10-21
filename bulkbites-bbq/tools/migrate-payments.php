<?php
require __DIR__ . '/database-transfer.php';
require dirname(__DIR__) . '/includes/payment-migration.php';
try {
    $config = transfer_config();
    $pdo = transfer_connection($config);
    $pdo->exec('USE ' . transfer_identifier($config['name']));
    migrate_payment_inventory($pdo);
    echo "Payment, stock, and pickup/delivery/store schema upgraded. Historical records and stock quantities were preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Payment upgrade failed. Check the database connection and schema permissions.\n");
    exit(1);
}
