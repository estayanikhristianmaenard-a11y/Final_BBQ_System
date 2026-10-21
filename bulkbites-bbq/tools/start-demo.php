<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
echo "BulkBites local demo: http://127.0.0.1:8080/\n";
echo "Keep this window open. In a separate terminal use: ngrok http 8080\n";
echo "Stop with Ctrl+C. This server is for demonstrations, not production hosting.\n";
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:8080', '-t', $root, __DIR__ . '/demo-router.php'],
    [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root, null, ['bypass_shell' => true]);
exit(is_resource($process) ? proc_close($process) : 1);
