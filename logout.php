<?php
require_once __DIR__ . '/config.php';
start_secure_session();

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
session_destroy();

header('location: login.php');
exit;
