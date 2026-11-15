<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$accounts = [];

// Fetch all accounts for the user, including account_type
$sql = "SELECT id, name, type, current_balance, initial_balance, created_at, account_type FROM accounts WHERE user_id = ? ORDER BY name";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $accounts[] = $row;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>আপনার অ্যাকাউন্ট</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>আপনার অ্যাকাউন্ট</h2>
        <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a> | <a href="add_account.php">নতুন অ্যাকাউন্ট যোগ করুন</a></p>

        <?php if (($_GET['error'] ?? '') === 'has_transactions'): ?>
            <p style="color:#c0392b; background:#fdecea; padding:10px; border-radius:5px;">
                এই অ্যাকাউন্টে লেনদেন আছে, তাই মুছে ফেলা যাবে না। আগে সংশ্লিষ্ট লেনদেনগুলো মুছুন বা অন্য অ্যাকাউন্টে সরান।
            </p>
        <?php endif; ?>

        <?php if (empty($accounts)): ?>
            <p>আপনার কোনো অ্যাকাউন্ট সেট করা হয়নি।</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>নাম</th>
                        <th>প্রকার</th>
                        <th>ধরন</th> <!-- New Column Header -->
                        <th>প্রাথমিক ব্যালেন্স</th>
                        <th>বর্তমান ব্যালেন্স</th>
                        <th>তৈরির তারিখ</th>
                        <th>অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td data-label="নাম"><?php echo htmlspecialchars($account['name']); ?></td>
                        <td data-label="প্রকার"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($account['type']))); ?></td>
                        <td data-label="ধরন"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($account['account_type']))); ?></td> <!-- Display Account Type -->
                        <td data-label="প্রাথমিক ব্যালেন্স">BDT <?php echo number_format(htmlspecialchars($account['initial_balance']), 2); ?></td>
                        <td data-label="বর্তমান ব্যালেন্স">BDT <?php echo number_format(htmlspecialchars($account['current_balance']), 2); ?></td>
                        <td data-label="তৈরির তারিখ"><?php echo htmlspecialchars(date('Y-m-d', strtotime($account['created_at']))); ?></td>
                        <td data-label="অ্যাকশন" class="action-links">
                            <a href="edit_account.php?id=<?php echo $account['id']; ?>">সম্পাদনা করুন</a>
                            <form action="delete_account.php" method="post" class="inline-form" onsubmit="return confirm('আপনি কি নিশ্চিত যে এই অ্যাকাউন্টটি মুছে ফেলতে চান?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$account['id']; ?>">
                                <button type="submit" class="link-button delete">মুছে ফেলুন</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
