<?php
require_once 'auth.php';
require_once 'account_options.php';

$user_id = $_SESSION['id'];
$data = ['name' => '', 'type' => '', 'account_type' => '', 'initial_balance' => '0'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$data, $errors] = validate_account_input($_POST);

    if (!$errors) {
        $balance = (float)$data['initial_balance'];
        $stmt = mysqli_prepare($link, "INSERT INTO accounts (user_id, name, type, initial_balance, current_balance, account_type) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issdds", $user_id, $data['name'], $data['type'], $balance, $balance, $data['account_type']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('location: view_accounts.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাকাউন্ট যোগ করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>নতুন অ্যাকাউন্ট যোগ করুন</h2>
        <form action="add_account.php" method="post">
            <?php echo csrf_field(); ?>
            <?php account_form_fields($data, $errors); ?>
            <div>
                <input type="submit" value="অ্যাকাউন্ট যোগ করুন">
            </div>
            <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a> | <a href="view_accounts.php">সকল অ্যাকাউন্ট দেখুন</a></p>
        </form>
    </div>
</body>
</html>
