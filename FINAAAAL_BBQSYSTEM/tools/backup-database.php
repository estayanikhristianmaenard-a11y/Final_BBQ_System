<?php
/** Export the current database into the snapshot shipped with the repository. */
require __DIR__ . '/database-transfer.php';

$temporary = null;
$exitCode = 0;
try {
    $config = transfer_config();
    transfer_identifier($config['name']);
    $directory = dirname(__DIR__) . '/database';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Could not create the database snapshot directory.');
    }
    $snapshot = $directory . '/current.sql';
    $temporary = tempnam($directory, 'snapshot-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create a temporary snapshot.');
    }
    transfer_run(transfer_binary('mysqldump'), [
        '--single-transaction',
        '--quick',
        '--skip-lock-tables',
        '--skip-dump-date',
        '--default-character-set=utf8mb4',
        '--hex-blob',
        '--result-file=' . $temporary,
        $config['name'],
    ], $config);
    if (filesize($temporary) === 0 || !rename($temporary, $snapshot)) {
        throw new RuntimeException('Could not save the completed snapshot.');
    }
    echo "Saved the current database to database/current.sql.\n";
    echo "Commit and push this snapshot with your application changes to transfer these records.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Backup failed: " . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($temporary && is_file($temporary)) {
        unlink($temporary);
    }
}
exit($exitCode);
