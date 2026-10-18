<?php
/** Verify demo schema and upgrade together without touching the live database. */
require dirname(__DIR__) . '/tools/database-transfer.php';
require dirname(__DIR__) . '/includes/email-migration.php';
$config = transfer_config();
$pdo = transfer_connection($config);
$name = 'bulkbites_schema_test_' . bin2hex(random_bytes(5));
$identifier = transfer_identifier($name);
try {
    $sql = file_get_contents(dirname(__DIR__) . '/database.sql');
    $sql = str_replace('`bulkbites_bbq`', $identifier, $sql);
    $pdo->exec($sql);
    migrate_email_login($pdo);
    migrate_receipt_emails($pdo);
    provision_default_managers($pdo);
    $actual = $pdo->query("SELECT email FROM users WHERE role = 'MANAGER' AND is_active = 1 AND password_hash IS NULL ORDER BY email")->fetchAll(PDO::FETCH_COLUMN);
    $expected = array_keys(default_manager_accounts());
    sort($expected);
    if ($actual !== $expected) throw new RuntimeException('Incorrect default personnel accounts.');
    echo "Fresh SQL schema import and idempotent upgrade passed; exactly three email-only managers.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS ' . $identifier);
}
