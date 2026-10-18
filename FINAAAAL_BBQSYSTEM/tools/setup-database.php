<?php
/** Restore the bundled snapshot only into a new or completely empty database. */
require __DIR__ . '/database-transfer.php';
require dirname(__DIR__) . '/includes/email-migration.php';

try {
    $config = transfer_config();
    foreach (array_slice($argv, 1) as $argument) {
        if (!str_starts_with($argument, '--database=')) {
            throw new RuntimeException('Usage: php tools/setup-database.php [--database=NEW_DATABASE_NAME]');
        }
        $config['name'] = substr($argument, strlen('--database='));
    }
    $identifier = transfer_identifier($config['name']);
    $snapshot = dirname(__DIR__) . '/database/current.sql';
    if (!is_file($snapshot) || filesize($snapshot) === 0) {
        throw new RuntimeException('The bundled database/current.sql snapshot is missing. Run the backup on the source PC first.');
    }
    $mysql = transfer_binary('mysql');
    $pdo = transfer_connection($config);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute(['bbq-setup-' . $config['name']]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another setup is running. Try again after it finishes.');
    }
    try {
        $tables = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
        $tables->execute([$config['name']]);
        if ((int) $tables->fetchColumn() > 0) {
            throw new RuntimeException('Database ' . $config['name'] . ' already has tables. Setup left its data unchanged.');
        }
        $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        transfer_run($mysql, ['--default-character-set=utf8mb4', '--database=' . $config['name']], $config, $snapshot);
        $pdo->exec('USE ' . $identifier);
        migrate_email_login($pdo);
        migrate_receipt_emails($pdo);
        provision_default_managers($pdo);
        echo 'Restored the bundled database into ' . $config['name'] . ".\n";
        echo "Start Apache and open the application in your browser.\n";
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute(['bbq-setup-' . $config['name']]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Setup stopped: " . $error->getMessage() . "\n");
    fwrite(STDERR, "Check that MySQL is running and config/database.php matches this PC.\n");
    exit(1);
}
