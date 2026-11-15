<?php
require_once __DIR__ . '/config.php';
start_secure_session();

if (!ALLOW_REGISTRATION) {
    header('location: login.php');
    exit;
}
if (!empty($_SESSION['loggedin'])) {
    header('location: dashboard.php');
    exit;
}

const DEFAULT_CATEGORIES = [
    'income' => ['বেতন', 'ব্যবসা', 'উপহার', 'অন্যান্য আয়'],
    'expense' => ['খাবার', 'বাজার', 'যাতায়াত', 'বাসা ভাড়া', 'বিদ্যুৎ ও ইউটিলিটি', 'মোবাইল ও ইন্টারনেট',
                  'স্বাস্থ্য', 'শিক্ষা', 'কেনাকাটা', 'বিনোদন', 'অন্যান্য ব্যয়'],
];

function create_user(mysqli $link, string $username, string $email, string $password): void
{
    mysqli_begin_transaction($link);
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($link, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $username, $email, $hash);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($link, "INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
        foreach (DEFAULT_CATEGORIES as $type => $names) {
            foreach ($names as $name) {
                mysqli_stmt_bind_param($stmt, 'iss', $user_id, $name, $type);
                mysqli_stmt_execute($stmt);
            }
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($link, "INSERT INTO accounts (user_id, name, type, account_type, initial_balance, current_balance)
                                       VALUES (?, 'নগদ', 'cash', 'current', 0, 0)");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($link);
    } catch (Throwable $e) {
        mysqli_rollback($link);
        throw $e;
    }
}

function value_taken(mysqli $link, string $column, string $value): bool
{
    $sql = $column === 'email'
        ? "SELECT 1 FROM users WHERE email = ? LIMIT 1"
        : "SELECT 1 FROM users WHERE username = ? LIMIT 1";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, 's', $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $taken = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $taken;
}

$username = $email = '';
$username_err = $email_err = $password_err = $confirm_err = $general_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Bots fill every field; people never see this one.
    if (!empty($_POST['website'])) {
        header('location: login.php?registered=1');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $username_err = 'ইউজারনেম ৩-৩০ অক্ষরের হতে হবে, শুধু ইংরেজি অক্ষর, সংখ্যা, _ এবং . ব্যবহার করা যাবে।';
    } elseif (value_taken($link, 'username', $username)) {
        $username_err = 'এই ইউজারনেমটি ইতিমধ্যে ব্যবহৃত হচ্ছে। অন্য একটি চেষ্টা করুন।';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $email_err = 'একটি সঠিক ইমেল ঠিকানা লিখুন।';
    } elseif (value_taken($link, 'email', $email)) {
        $email_err = 'এই ইমেল দিয়ে আগেই একটি অ্যাকাউন্ট খোলা হয়েছে।';
    }

    if (mb_strlen($password) < 8) {
        $password_err = 'পাসওয়ার্ডে কমপক্ষে ৮টি অক্ষর থাকতে হবে।';
    } elseif (strcasecmp($password, $username) === 0) {
        $password_err = 'পাসওয়ার্ড আর ইউজারনেম একই হতে পারবে না।';
    }

    if ($confirm !== $password) {
        $confirm_err = 'দুইটি পাসওয়ার্ড মিলছে না।';
    }

    if (!$username_err && !$email_err && !$password_err && !$confirm_err) {
        if (attempts_since($link, 'register', client_ip(), null, REGISTER_WINDOW_MINUTES) >= REGISTER_MAX_PER_IP) {
            $general_err = 'এই নেটওয়ার্ক থেকে অল্প সময়ে অনেকগুলো অ্যাকাউন্ট খোলা হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
        } else {
            try {
                create_user($link, $username, $email, $password);
                record_attempt($link, 'register', null);
                header('location: login.php?registered=1');
                exit;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) {
                    throw $e;
                }
                $general_err = 'এই ইউজারনেম বা ইমেল এইমাত্র অন্য কেউ নিয়েছে। অন্য একটি চেষ্টা করুন।';
            }
        }
    }
}

function field_class(string $err): string
{
    return 'field' . ($err ? ' has-error' : '');
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>সাইন আপ - ব্যক্তিগত হিসাব</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-card">
        <?php include __DIR__ . '/auth_brand.php'; ?>

        <section class="form-panel">
            <h2>নতুন অ্যাকাউন্ট খুলুন</h2>
            <p class="subtitle">বিনামূল্যে, এক মিনিটেই শুরু করুন</p>

            <?php if ($general_err): ?>
                <div class="alert" role="alert"><?php echo e($general_err); ?></div>
            <?php endif; ?>

            <form action="register.php" method="post" novalidate data-busy-text="অ্যাকাউন্ট তৈরি হচ্ছে...">
                <?php echo csrf_field(); ?>

                <div class="hp-field" aria-hidden="true">
                    <label for="website">ওয়েবসাইট</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="<?php echo field_class($username_err); ?>">
                    <label for="username">ব্যবহারকারীর নাম</label>
                    <div class="input-wrap">
                        <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="username" name="username" autocomplete="username" autofocus
                               value="<?php echo e($username); ?>" placeholder="যেমন: rahim_01" maxlength="30">
                    </div>
                    <?php if ($username_err): ?>
                        <span class="field-error"><?php echo e($username_err); ?></span>
                    <?php else: ?>
                        <span class="field-hint">ইংরেজি অক্ষর, সংখ্যা, _ এবং . ব্যবহার করুন</span>
                    <?php endif; ?>
                </div>

                <div class="<?php echo field_class($email_err); ?>">
                    <label for="email">ইমেল</label>
                    <div class="input-wrap">
                        <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                        <input type="email" id="email" name="email" autocomplete="email"
                               value="<?php echo e($email); ?>" placeholder="you@example.com" maxlength="100">
                    </div>
                    <?php if ($email_err): ?><span class="field-error"><?php echo e($email_err); ?></span><?php endif; ?>
                </div>

                <div class="<?php echo field_class($password_err); ?>">
                    <label for="password">পাসওয়ার্ড</label>
                    <div class="input-wrap">
                        <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="password" name="password" autocomplete="new-password" placeholder="কমপক্ষে ৮ অক্ষর">
                        <button type="button" class="toggle-password" data-target="password" aria-label="পাসওয়ার্ড দেখান">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php if ($password_err): ?><span class="field-error"><?php echo e($password_err); ?></span><?php endif; ?>
                </div>

                <div class="<?php echo field_class($confirm_err); ?>">
                    <label for="confirm_password">পাসওয়ার্ড আবার লিখুন</label>
                    <div class="input-wrap">
                        <svg class="lead-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="••••••••">
                        <button type="button" class="toggle-password" data-target="confirm_password" aria-label="পাসওয়ার্ড দেখান">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php if ($confirm_err): ?><span class="field-error"><?php echo e($confirm_err); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="submit-btn">অ্যাকাউন্ট খুলুন</button>
            </form>

            <p class="switch-link">ইতিমধ্যে অ্যাকাউন্ট আছে? <a href="login.php">লগইন করুন</a></p>
        </section>
    </main>
    <script src="auth.js"></script>
</body>
</html>
