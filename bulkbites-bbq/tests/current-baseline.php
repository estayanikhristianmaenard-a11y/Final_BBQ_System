<?php
/** Verify a fresh private export in a disposable database; leave source records intact. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/tools/database-transfer.php';
$directory=dirname(__DIR__,2).'/.tmp';
if(!is_dir($directory) && !mkdir($directory,0700,true))exit(1);
$snapshot=tempnam($directory,'baseline-');
$status=1;
try {
    if($snapshot===false)throw new RuntimeException('Temporary export unavailable');
    $config=transfer_config();
    transfer_run(transfer_binary('mysqldump'),['--single-transaction','--quick','--skip-lock-tables','--skip-dump-date','--default-character-set=utf8mb4','--hex-blob','--result-file='.$snapshot,$config['name']],$config);
    putenv('BB_TEST_SNAPSHOT='.$snapshot);
    $process=proc_open([PHP_BINARY,__DIR__.'/baseline.php'],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process))throw new RuntimeException('Baseline runner unavailable');
    fclose($pipes[0]);$status=proc_close($process);
} catch(Throwable $error) {
    fwrite(STDERR,"Could not complete the private baseline export. Check local database and PHP setup.\n");
} finally {
    if($snapshot && is_file($snapshot) && dirname(realpath($snapshot))===realpath($directory))unlink($snapshot);
}
exit($status);
