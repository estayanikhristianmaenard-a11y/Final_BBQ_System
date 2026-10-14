<?php
/** Shared helpers for the command-line database backup and setup tools. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This tool must be run from the command line.');
}

function transfer_config(): array
{
    $root = dirname(__DIR__);
    $path = $root . '/config/database.php';
    if (!is_file($path)) {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) {
            throw new RuntimeException('Could not create the config directory.');
        }
        if (!copy($root . '/config.example.php', $path)) {
            throw new RuntimeException('Could not create config/database.php.');
        }
        echo "Created config/database.php from the example.\n";
    }
    $config = require $path;
    return $config['db'];
}

function transfer_connection(array $config): PDO
{
    return new PDO(
        'mysql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 3306) . ';charset=utf8mb4',
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function transfer_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name)) {
        throw new RuntimeException('Use a database name containing only letters, numbers, or underscores.');
    }
    return '`' . $name . '`';
}

/** Locate the MySQL tools bundled alongside XAMPP's PHP installation. */
function transfer_binary(string $name): string
{
    $extension = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
    $root = dirname(PHP_BINARY, 2);
    $candidates = [
        $root . '/mysql/bin/' . $name . $extension,
        $root . '/bin/' . $name . $extension,
    ];
    foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory) {
        $candidates[] = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name . $extension;
    }
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException("Could not find {$name}. Run this tool using XAMPP's PHP installation.");
}

/** Put credentials in a temporary option file instead of command-line arguments. */
function transfer_run(string $binary, array $arguments, array $config, ?string $inputFile = null): void
{
    $optionFile = tempnam(sys_get_temp_dir(), 'bbq-db-');
    if ($optionFile === false) {
        throw new RuntimeException('Could not create a temporary MySQL option file.');
    }
    try {
        @chmod($optionFile, 0600);
        $options = "[client]\n";
        foreach (['host', 'user', 'password', 'port'] as $key) {
            $value = (string) ($key === 'password' ? $config['pass'] : ($config[$key] ?? 3306));
            $value = strtr($value, ["\\" => "\\\\", '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
            $options .= $key . '="' . $value . "\"\n";
        }
        if (file_put_contents($optionFile, $options) === false) {
            throw new RuntimeException('Could not write the temporary MySQL option file.');
        }
        $descriptors = [
            0 => $inputFile === null ? ['pipe', 'r'] : ['file', $inputFile, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            array_merge([$binary, '--defaults-extra-file=' . $optionFile], $arguments),
            $descriptors,
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the MySQL tool.');
        }
        if ($inputFile === null) {
            fclose($pipes[0]);
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException(trim($error ?: $output) ?: 'The MySQL tool failed.');
        }
    } finally {
        if (is_file($optionFile)) {
            unlink($optionFile);
        }
    }
}
