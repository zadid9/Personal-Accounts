<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$budgets = [];

// Fetch all budgets for the user along with spent amount for each budget period
$sql = "SELECT b.id, b.category, b.budgeted_amount, b.start_date, b.end_date,
               SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) AS spent_amount
        FROM budgets b
        LEFT JOIN transactions t ON b.user_id = t.user_id AND b.category = t.category 
                                 AND t.transaction_date BETWEEN b.start_date AND b.end_date
        WHERE b.user_id = ?
        GROUP BY b.id, b.category, b.budgeted_amount, b.start_date, b.end_date
        ORDER BY b.start_date DESC, b.category";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $budgets[] = $row;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>আপনার বাজেট</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>আপনার বাজেট</h2>
        <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a> | <a href="add_budget.php">নতুন বাজেট সেট করুন</a></p>

        <?php if (empty($budgets)): ?>
            <p>আপনার কোনো বাজেট সেট করা হয়নি।</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ক্যাটাগরি</th>
                        <th>বাজেট পরিমাণ</th>
                        <th>শুরুর তারিখ</th>
                        <th>শেষের তারিখ</th>
                        <th>ব্যয়িত পরিমাণ</th>
                        <th>বাকি/অতিরিক্ত</th>
                        <th>অবস্থা</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $budget): ?>
                    <?php
                        $remaining = $budget['budgeted_amount'] - $budget['spent_amount'];
                        $status_class = '';
                        $status_text = '';

                        if ($remaining < 0) {
                            $status_class = 'budget-status-over';
                            $status_text = 'অতিরিক্ত ব্যয়';
                        } elseif ($remaining <= $budget['budgeted_amount'] * 0.10 && $remaining > 0) { // 10% remaining
                            $status_class = 'budget-status-warning';
                            $status_text = 'প্রায় শেষ';
                        } elseif ($remaining == 0) {
                            $status_class = 'budget-status-ok';
                            $status_text = 'বাজেটের মধ্যে (শেষ)';
                        } else {
                            $status_class = 'budget-status-ok';
                            $status_text = 'বাজেটের মধ্যে';
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($budget['category']); ?></td>
                        <td>BDT <?php echo number_format(htmlspecialchars($budget['budgeted_amount']), 2); ?></td>
                        <td><?php echo htmlspecialchars($budget['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($budget['end_date']); ?></td>
                        <td>BDT <?php echo number_format(htmlspecialchars($budget['spent_amount']), 2); ?></td>
                        <td>BDT <?php echo number_format(abs($remaining), 2); ?></td>
                        <td class="<?php echo $status_class; ?>"><?php echo $status_text; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
