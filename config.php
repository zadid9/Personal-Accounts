<?php
// Settings come from a .env file that is never committed. See .env.example.

function load_env_file(string $path): array
{
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }
    return $values;
}

$env_file = getenv('APP_ENV_FILE') ?: __DIR__ . '/.env';
if (!is_readable($env_file)) {
    http_response_code(500);
    exit('Configuration missing: copy .env.example to .env and fill in your database details.');
}
$env = load_env_file($env_file);

define('APP_DEBUG', strtolower($env['APP_DEBUG'] ?? 'false') === 'true');
define('ALLOW_REGISTRATION', strtolower($env['ALLOW_REGISTRATION'] ?? 'true') === 'true');
define('CLIENT_IP_HEADER', $env['CLIENT_IP_HEADER'] ?? '');
// Links in emails are built from APP_URL, never from the request's Host header.
define('APP_URL', rtrim($env['APP_URL'] ?? '', '/'));
define('MAIL_FROM', $env['MAIL_FROM'] ?? '');
define('MAIL_FROM_NAME', $env['MAIL_FROM_NAME'] ?? 'ব্যক্তিগত হিসাব');

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';

set_exception_handler(function (Throwable $e) {
    error_log('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><title>ত্রুটি</title></head><body style="font-family:sans-serif;padding:40px;text-align:center">';
    echo '<h2>দুঃখিত, কিছু একটা সমস্যা হয়েছে।</h2><p>অনুগ্রহ করে একটু পরে আবার চেষ্টা করুন।</p><p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a></p>';
    if (APP_DEBUG) {
        echo '<pre style="text-align:left;background:#f4f4f4;padding:12px">' . e((string)$e) . '</pre>';
    }
    echo '</body></html>';
});

$link = mysqli_connect(
    $env['DB_HOST'] ?? 'localhost',
    $env['DB_USER'] ?? '',
    $env['DB_PASSWORD'] ?? '',
    $env['DB_NAME'] ?? '',
    (int)($env['DB_PORT'] ?? 3306)
);
// Installs created before the charset fix stored UTF-8 text through a latin1 connection,
// so they must keep DB_CHARSET=latin1 until their data is converted.
mysqli_set_charset($link, $env['DB_CHARSET'] ?? 'utf8mb4');

unset($env);
