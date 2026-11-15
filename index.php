<?php
require_once __DIR__ . '/config.php';
start_secure_session();

header('location: ' . (empty($_SESSION['loggedin']) ? 'login.php' : 'dashboard.php'));
exit;
