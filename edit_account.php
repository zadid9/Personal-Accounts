<?php
require_once 'auth.php';
require_once 'account_options.php';

$user_id = $_SESSION['id'];
$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($link, "SELECT name, type, initial_balance, current_balance, account_type FROM accounts WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$account) {
    header('location: view_accounts.php');
    exit;
}

$data = $account;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$data, $errors] = validate_account_input($_POST);

    if (!$errors) {
        $new_initial = (float)$data['initial_balance'];
        // Assignments run left to right, so current_balance shifts by (new - old) opening balance.
        $stmt = mysqli_prepare($link, "UPDATE accounts
            SET name = ?, type = ?, current_balance = current_balance + (? - initial_balance), initial_balance = ?, account_type = ?
            WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ssddsii", $data['name'], $data['type'], $new_initial, $new_initial, $data['account_type'], $id, $user_id);
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
    <title>অ্যাকাউন্ট সম্পাদনা করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>অ্যাকাউন্ট সম্পাদনা করুন</h2>
        <form action="edit_account.php?id=<?php echo $id; ?>" method="post">
            <?php echo csrf_field(); ?>
            <?php account_form_fields($data, $errors); ?>
            <div>
                <label>বর্তমান ব্যালেন্স (শুধুমাত্র দেখার জন্য):</label>
                <input type="text" value="BDT <?php echo number_format((float)$account['current_balance'], 2); ?>" disabled>
                <small>প্রাথমিক ব্যালেন্স বদলালে বর্তমান ব্যালেন্সও সমান পরিমাণে বদলাবে।</small>
            </div>
            <div>
                <input type="submit" value="আপডেট করুন">
            </div>
            <p><a href="view_accounts.php">অ্যাকাউন্ট তালিকায় ফিরে যান</a></p>
        </form>
    </div>
</body>
</html>
