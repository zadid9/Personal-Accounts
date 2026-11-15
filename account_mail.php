<?php
require_once __DIR__ . '/mailer.php';

const RESET_TOKEN_MINUTES = 60;
const VERIFY_TOKEN_MINUTES = 60 * 24 * 3;
const RESET_MAX_PER_IP = 5;
const RESET_MAX_PER_EMAIL = 3;
const VERIFY_RESEND_MAX = 3;
const MAIL_WINDOW_MINUTES = 60;

// Only the SHA-256 of a token is stored, so a leaked database can't be used to reset passwords.
function create_user_token(mysqli $link, int $user_id, string $purpose, int $minutes): string
{
    $stmt = mysqli_prepare($link, "DELETE FROM user_tokens WHERE user_id = ? AND purpose = ? AND used_at IS NULL");
    mysqli_stmt_bind_param($stmt, "is", $user_id, $purpose);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = mysqli_prepare($link, "INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE)");
    mysqli_stmt_bind_param($stmt, "issi", $user_id, $purpose, $hash, $minutes);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $token;
}

/** Returns ['token_id', 'user_id', 'username', 'email'] for a valid unused token, else null. */
function find_user_token(mysqli $link, string $token, string $purpose): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = mysqli_prepare($link, "SELECT t.id AS token_id, u.id AS user_id, u.username, u.email
        FROM user_tokens t JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ? AND t.purpose = ? AND t.used_at IS NULL AND t.expires_at > NOW()");
    mysqli_stmt_bind_param($stmt, "ss", $hash, $purpose);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function mark_token_used(mysqli $link, int $token_id): void
{
    $stmt = mysqli_prepare($link, "UPDATE user_tokens SET used_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $token_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function send_verification_email(mysqli $link, int $user_id, string $username, string $email): bool
{
    $token = create_user_token($link, $user_id, 'verify_email', VERIFY_TOKEN_MINUTES);
    [$text, $html] = mail_layout(
        'ইমেল যাচাই করুন',
        "হ্যালো $username, ব্যক্তিগত হিসাবে আপনার অ্যাকাউন্ট খোলা হয়েছে। নিচের বাটনে চাপ দিয়ে আপনার ইমেল ঠিকানাটি নিশ্চিত করুন। এতে পাসওয়ার্ড ভুলে গেলে আপনি অ্যাকাউন্ট ফিরে পাবেন।",
        'ইমেল যাচাই করুন',
        APP_URL . '/verify_email.php?token=' . $token,
        'লিংকটি ৩ দিন কাজ করবে। আপনি এই অ্যাকাউন্ট না খুলে থাকলে এই ইমেলটি উপেক্ষা করুন।'
    );
    return send_mail($email, 'আপনার ইমেল যাচাই করুন', $text, $html);
}

function send_password_reset_email(mysqli $link, int $user_id, string $username, string $email): bool
{
    $token = create_user_token($link, $user_id, 'password_reset', RESET_TOKEN_MINUTES);
    [$text, $html] = mail_layout(
        'পাসওয়ার্ড রিসেট',
        "হ্যালো $username, আপনার অ্যাকাউন্টের পাসওয়ার্ড রিসেট করার অনুরোধ পাওয়া গেছে। নতুন পাসওয়ার্ড দিতে নিচের বাটনে চাপ দিন।",
        'নতুন পাসওয়ার্ড দিন',
        APP_URL . '/reset_password.php?token=' . $token,
        'লিংকটি ১ ঘণ্টা কাজ করবে এবং একবারই ব্যবহার করা যাবে। আপনি অনুরোধ না করে থাকলে এই ইমেলটি উপেক্ষা করুন, আপনার পাসওয়ার্ড বদলাবে না।'
    );
    return send_mail($email, 'পাসওয়ার্ড রিসেট করুন', $text, $html);
}
