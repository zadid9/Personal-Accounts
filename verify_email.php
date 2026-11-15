<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/account_mail.php';
start_secure_session();

$row = find_user_token($link, (string)($_GET['token'] ?? ''), 'verify_email');
if ($row) {
    $user_id = (int)$row['user_id'];
    $stmt = mysqli_prepare($link, "UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mark_token_used($link, (int)$row['token_id']);
}
$logged_in = !empty($_SESSION['loggedin']);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ইমেল যাচাই - ব্যক্তিগত হিসাব</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-card">
        <?php include __DIR__ . '/auth_brand.php'; ?>
        <section class="form-panel">
            <h2>ইমেল যাচাই</h2>
            <?php if ($row): ?>
                <div class="alert success" role="status">ধন্যবাদ! আপনার ইমেল <strong><?php echo e($row['email']); ?></strong> যাচাই হয়েছে।</div>
            <?php else: ?>
                <div class="alert" role="alert">এই লিংকটি আর কাজ করছে না, হয়তো আগেই ব্যবহার হয়েছে বা মেয়াদ শেষ। ড্যাশবোর্ড থেকে নতুন লিংক পাঠাতে পারবেন।</div>
            <?php endif; ?>
            <p class="switch-link"><a href="<?php echo $logged_in ? 'dashboard.php' : 'login.php'; ?>"><?php echo $logged_in ? 'ড্যাশবোর্ডে যান' : 'লগইন করুন'; ?></a></p>
        </section>
    </main>
</body>
</html>
