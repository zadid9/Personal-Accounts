<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/account_mail.php';
start_secure_session();

$email = '';
$email_err = $general_err = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = 'একটি সঠিক ইমেল ঠিকানা লিখুন।';
    } elseif (attempts_since($link, 'reset', client_ip(), null, MAIL_WINDOW_MINUTES) >= RESET_MAX_PER_IP) {
        $general_err = 'অল্প সময়ে অনেকবার চেষ্টা হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
    } else {
        $identifier = mb_strtolower($email);
        $over_email_limit = attempts_since($link, 'reset', null, $identifier, MAIL_WINDOW_MINUTES) >= RESET_MAX_PER_EMAIL;
        record_attempt($link, 'reset', $identifier);

        if (!$over_email_limit) {
            $stmt = mysqli_prepare($link, "SELECT id, username, email FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($user) {
                send_password_reset_email($link, (int)$user['id'], $user['username'], $user['email']);
            }
        }
        // Same answer whether or not the address has an account, so the form can't be used to discover users.
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>পাসওয়ার্ড ভুলে গেছেন - ব্যক্তিগত হিসাব</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-card">
        <?php include __DIR__ . '/auth_brand.php'; ?>

        <section class="form-panel">
            <h2>পাসওয়ার্ড ভুলে গেছেন?</h2>

            <?php if ($sent): ?>
                <div class="alert success" role="status">
                    এই ইমেলে কোনো অ্যাকাউন্ট থাকলে পাসওয়ার্ড রিসেটের লিংক পাঠানো হয়েছে। ইনবক্স দেখুন, না পেলে স্প্যাম ফোল্ডারও দেখুন। লিংকটি ১ ঘণ্টা কাজ করবে।
                </div>
                <p class="switch-link"><a href="login.php">লগইন পেজে ফিরে যান</a></p>
            <?php else: ?>
                <p class="subtitle">আপনার অ্যাকাউন্টের ইমেল দিন, আমরা নতুন পাসওয়ার্ড দেওয়ার লিংক পাঠাব।</p>

                <?php if ($general_err): ?>
                    <div class="alert" role="alert"><?php echo e($general_err); ?></div>
                <?php endif; ?>

                <form action="forgot_password.php" method="post" novalidate data-busy-text="পাঠানো হচ্ছে...">
                    <?php echo csrf_field(); ?>
                    <div class="field <?php echo $email_err ? 'has-error' : ''; ?>">
                        <label for="email">ইমেল</label>
                        <div class="input-wrap">
                            <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                            <input type="email" id="email" name="email" autocomplete="email" autofocus value="<?php echo e($email); ?>" placeholder="you@example.com">
                        </div>
                        <?php if ($email_err): ?><span class="field-error"><?php echo e($email_err); ?></span><?php endif; ?>
                    </div>
                    <button type="submit" class="submit-btn">রিসেট লিংক পাঠান</button>
                </form>

                <p class="switch-link">মনে পড়েছে? <a href="login.php">লগইন করুন</a></p>
            <?php endif; ?>
        </section>
    </main>
    <script src="auth.js"></script>
</body>
</html>
