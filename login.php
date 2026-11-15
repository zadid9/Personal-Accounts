<?php
require_once __DIR__ . '/config.php';
start_secure_session();

if (!empty($_SESSION['loggedin'])) {
    header('location: dashboard.php');
    exit;
}

$username = '';
$username_err = $password_err = $general_err = '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    // Passwords have always been trimmed at signup; keep doing so or existing hashes stop matching.
    $password = trim($_POST['password'] ?? '');

    if ($username === '') {
        $username_err = 'অনুগ্রহ করে ব্যবহারকারীর নাম লিখুন।';
    }
    if ($password === '') {
        $password_err = 'অনুগ্রহ করে আপনার পাসওয়ার্ড লিখুন।';
    }

    if (!$username_err && !$password_err) {
        $identifier = mb_strtolower($username);

        if (attempts_since($link, 'login', client_ip(), null, LOGIN_WINDOW_MINUTES) >= LOGIN_MAX_FAILS_PER_IP
            || attempts_since($link, 'login', null, $identifier, LOGIN_WINDOW_MINUTES) >= LOGIN_MAX_FAILS_PER_USER) {
            $general_err = 'অনেকবার ভুল চেষ্টা হয়েছে। ' . LOGIN_WINDOW_MINUTES . ' মিনিট পরে আবার চেষ্টা করুন।';
        } else {
            $stmt = mysqli_prepare($link, "SELECT id, username, password FROM users WHERE username = ?");
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password'])) {
                clear_attempts($link, 'login', $identifier);
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                header('location: dashboard.php');
                exit;
            }

            record_attempt($link, 'login', $identifier);
            $general_err = 'ব্যবহারকারীর নাম অথবা পাসওয়ার্ড সঠিক নয়।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লগইন - ব্যক্তিগত হিসাব</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-card">
        <?php include __DIR__ . '/auth_brand.php'; ?>

        <section class="form-panel">
            <h2>স্বাগতম</h2>
            <p class="subtitle">চালিয়ে যেতে আপনার অ্যাকাউন্টে লগইন করুন</p>

            <?php if ($registered && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                <div class="alert success" role="status">অ্যাকাউন্ট তৈরি হয়েছে। এখন লগইন করুন।</div>
            <?php endif; ?>
            <?php if ($general_err): ?>
                <div class="alert" role="alert"><?php echo e($general_err); ?></div>
            <?php endif; ?>

            <form action="login.php" method="post" novalidate data-busy-text="লগইন হচ্ছে...">
                <?php echo csrf_field(); ?>

                <div class="field <?php echo $username_err ? 'has-error' : ''; ?>">
                    <label for="username">ব্যবহারকারীর নাম</label>
                    <div class="input-wrap">
                        <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="username" name="username" autocomplete="username" autofocus
                               value="<?php echo e($username); ?>" placeholder="আপনার ইউজারনেম">
                    </div>
                    <?php if ($username_err): ?><span class="field-error"><?php echo e($username_err); ?></span><?php endif; ?>
                </div>

                <div class="field <?php echo $password_err ? 'has-error' : ''; ?>">
                    <label for="password">পাসওয়ার্ড</label>
                    <div class="input-wrap">
                        <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••">
                        <button type="button" class="toggle-password" data-target="password" aria-label="পাসওয়ার্ড দেখান">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php if ($password_err): ?><span class="field-error"><?php echo e($password_err); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="submit-btn">লগইন করুন</button>
            </form>

            <?php if (ALLOW_REGISTRATION): ?>
                <p class="switch-link">একটি অ্যাকাউন্ট নেই? <a href="register.php">এখনই সাইন আপ করুন</a></p>
            <?php endif; ?>
        </section>
    </main>
    <script src="auth.js"></script>
</body>
</html>
