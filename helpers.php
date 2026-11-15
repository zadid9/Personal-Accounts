<?php

const LOGIN_MAX_FAILS_PER_USER = 5;
const LOGIN_MAX_FAILS_PER_IP = 20;
const LOGIN_WINDOW_MINUTES = 15;
const REGISTER_MAX_PER_IP = 3;
const REGISTER_WINDOW_MINUTES = 60;

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('PFMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (is_string($sent) && hash_equals(csrf_token(), $sent)) {
        return;
    }
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><title>ফর্মের মেয়াদ শেষ</title></head>'
        . '<body style="font-family:sans-serif;padding:40px;text-align:center">'
        . '<h2>ফর্মের মেয়াদ শেষ হয়ে গেছে</h2>'
        . '<p>নিরাপত্তার কারণে অনুরোধটি গ্রহণ করা হয়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।</p>'
        . '<p><a href="javascript:history.back()">পেছনে যান</a></p></body></html>';
    exit;
}

function client_ip(): string
{
    if (CLIENT_IP_HEADER !== '') {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', CLIENT_IP_HEADER));
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function attempts_since(mysqli $link, string $action, ?string $ip, ?string $identifier, int $minutes): int
{
    $sql = "SELECT COUNT(*) FROM auth_attempts WHERE action = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)";
    $types = 'si';
    $params = [$action, $minutes];
    if ($ip !== null) {
        $sql .= " AND ip = ?";
        $types .= 's';
        $params[] = $ip;
    }
    if ($identifier !== null) {
        $sql .= " AND identifier = ?";
        $types .= 's';
        $params[] = $identifier;
    }
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int)$count;
}

function record_attempt(mysqli $link, string $action, ?string $identifier): void
{
    $ip = client_ip();
    $stmt = mysqli_prepare($link, "INSERT INTO auth_attempts (action, ip, identifier) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sss', $action, $ip, $identifier);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query($link, "DELETE FROM auth_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
}

function clear_attempts(mysqli $link, string $action, string $identifier): void
{
    $stmt = mysqli_prepare($link, "DELETE FROM auth_attempts WHERE action = ? AND identifier = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $action, $identifier);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function is_valid_month(string $value): bool
{
    return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value);
}
