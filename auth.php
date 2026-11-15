<?php
require_once __DIR__ . '/config.php';

start_secure_session();

if (empty($_SESSION['loggedin'])) {
    header('location: login.php');
    exit;
}

// Every logged-in page funnels through here, so all state-changing requests are CSRF-checked.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}
