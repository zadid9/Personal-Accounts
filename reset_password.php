<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/account_mail.php';
start_secure_session();

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$row = find_user_token($link, $token, 'password_reset');
$password_err = $confirm_err = '';

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (mb_strlen($password) < 8) {
        $password_err = 'পাসওয়ার্ডে কমপক্ষে ৮টি অক্ষর থাকতে হবে।';
    } elseif (strcasecmp($password, $row['username']) === 0) {
        $password_err = 'পাসওয়ার্ড আর ইউজারনেম একই হতে পারবে না।';
    }
    if ($confirm !== $password) {
        $confirm_err = 'দুইটি পাসওয়ার্ড মিলছে না।';
    }

    if (!$password_err && !$confirm_err) {
        $user_id = (int)$row['user_id'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        mysqli_begin_transaction($link);
        $stmt = mysqli_prepare($link, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mark_token_used($link, (int)$row['token_id']);
        // Receiving the link also proves the address belongs to them.
        $stmt = mysqli_prepare($link, "UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($link);

        clear_attempts($link, 'login', mb_strtolower($row['username']));
        $_SESSION = [];
        session_regenerate_id(true);
        header('location: login.php?reset=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>নতুন পাসওয়ার্ড - ব্যক্তিগত হিসাব</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-card">
        <?php include __DIR__ . '/auth_brand.php'; ?>

        <section class="form-panel">
            <h2>নতুন পাসওয়ার্ড দিন</h2>

            <?php if (!$row): ?>
                <div class="alert" role="alert">এই লিংকটি আর কাজ করছে না। লিংকের মেয়াদ ১ ঘণ্টা এবং একবারই ব্যবহার করা যায়।</div>
                <p class="switch-link"><a href="forgot_password.php">নতুন লিংক চান</a></p>
            <?php else: ?>
                <p class="subtitle">অ্যাকাউন্ট: <strong><?php echo e($row['username']); ?></strong></p>

                <form action="reset_password.php" method="post" novalidate data-busy-text="সেভ হচ্ছে...">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo e($token); ?>">

                    <div class="field <?php echo $password_err ? 'has-error' : ''; ?>">
                        <label for="password">নতুন পাসওয়ার্ড</label>
                        <div class="input-wrap">
                            <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="password" name="password" autocomplete="new-password" autofocus placeholder="কমপক্ষে ৮ অক্ষর">
                            <button type="button" class="toggle-password" data-target="password" aria-label="পাসওয়ার্ড দেখান">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <?php if ($password_err): ?><span class="field-error"><?php echo e($password_err); ?></span><?php endif; ?>
                    </div>

                    <div class="field <?php echo $confirm_err ? 'has-error' : ''; ?>">
                        <label for="confirm_password">আবার লিখুন</label>
                        <div class="input-wrap">
                            <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="••••••••">
                            <button type="button" class="toggle-password" data-target="confirm_password" aria-label="পাসওয়ার্ড দেখান">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <?php if ($confirm_err): ?><span class="field-error"><?php echo e($confirm_err); ?></span><?php endif; ?>
                    </div>

                    <button type="submit" class="submit-btn">পাসওয়ার্ড সেভ করুন</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script src="auth.js"></script>
</body>
</html>
