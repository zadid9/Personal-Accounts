<?php
require_once 'auth.php';
require_once 'account_mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location: dashboard.php');
    exit;
}

$user_id = (int)$_SESSION['id'];
$identifier = 'user:' . $user_id;
$result = 'verify_sent';

if (attempts_since($link, 'verify', null, $identifier, MAIL_WINDOW_MINUTES) >= VERIFY_RESEND_MAX) {
    $result = 'verify_limit';
} else {
    $stmt = mysqli_prepare($link, "SELECT username, email, email_verified_at FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($user && $user['email_verified_at'] === null) {
        record_attempt($link, 'verify', $identifier);
        if (!send_verification_email($link, $user_id, $user['username'], $user['email'])) {
            $result = 'verify_failed';
        }
    }
}

header('location: dashboard.php?' . $result . '=1');
exit;
