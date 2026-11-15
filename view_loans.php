<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$loans = [];

// Fetch all loans for the user
$sql = "SELECT id, party_name, amount, type, status, paid_amount, due_date, notes 
        FROM loans WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $loans[] = $row;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>ধার/ঋণ অবস্থা</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>আপনার ধার/ঋণ অবস্থা</h2>
        <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a> | <a href="add_loan.php">নতুন ধার/ঋণ যোগ করুন</a> | <a href="record_repayment.php">পরিশোধ রেকর্ড করুন</a></p>

        <?php if (empty($loans)): ?>
            <p>আপনার কোনো ধার বা ঋণ নেই।</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>পার্টির নাম</th>
                        <th>মোট পরিমাণ</th>
                        <th>প্রকার</th>
                        <th>পরিশোধিত পরিমাণ</th>
                        <th>বাকি পরিমাণ</th>
                        <th>অবস্থা</th>
                        <th>শেষ তারিখ</th>
                        <th>নোটস</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($loan['party_name']); ?></td>
                        <td>BDT <?php echo number_format(htmlspecialchars($loan['amount']), 2); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($loan['type'])); ?></td>
                        <td>BDT <?php echo number_format(htmlspecialchars($loan['paid_amount']), 2); ?></td>
                        <td>BDT <?php echo number_format(htmlspecialchars($loan['amount'] - $loan['paid_amount']), 2); ?></td>
                        <td class="loan-status-<?php echo htmlspecialchars($loan['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($loan['status']))); ?></td>
                        <td><?php echo e($loan['due_date']); ?></td>
                        <td><?php echo e($loan['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
