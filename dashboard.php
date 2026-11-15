<?php
require_once 'auth.php'; // Include authentication check
require_once 'config.php'; // Include database connection

$user_id = $_SESSION['id'];
$total_income = 0;
$total_expense = 0;
$current_liquid_balance = 0; // Renamed from current_balance_all_accounts
$total_long_term_savings = 0; // New variable for long-term savings
$outstanding_loans = 0;
$lent_loans_outstanding = 0;

// Fetch total income and expense for the current month
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

// Total Income for current month
$sql_income = "SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?";
if ($stmt = mysqli_prepare($link, $sql_income)) {
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $current_month_start, $current_month_end);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_income);
    mysqli_stmt_fetch($stmt);
    // Handle null result for total_income
    if ($total_income === null) {
        $total_income = 0;
    }
    mysqli_stmt_close($stmt);
}

// Total Expense for current month
$sql_expense = "SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?";
if ($stmt = mysqli_prepare($link, $sql_expense)) {
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $current_month_start, $current_month_end);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_expense);
    mysqli_stmt_fetch($stmt);
    // Handle null result for total_expense
    if ($total_expense === null) {
        $total_expense = 0;
    }
    mysqli_stmt_close($stmt);
}

// Total current/liquid balance (excluding long_term_savings)
$sql_liquid_balance = "SELECT SUM(current_balance) AS total_balance FROM accounts WHERE user_id = ? AND account_type IN ('current', 'savings')";
if ($stmt = mysqli_prepare($link, $sql_liquid_balance)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $current_liquid_balance);
    mysqli_stmt_fetch($stmt);
    // Handle null result for current_liquid_balance
    if ($current_liquid_balance === null) {
        $current_liquid_balance = 0;
    }
    mysqli_stmt_close($stmt);
}

// Total long-term savings balance
$sql_long_term_savings = "SELECT SUM(current_balance) AS total_balance FROM accounts WHERE user_id = ? AND account_type = 'long_term_savings'";
if ($stmt = mysqli_prepare($link, $sql_long_term_savings)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_long_term_savings);
    mysqli_stmt_fetch($stmt);
    // Handle null result for total_long_term_savings
    if ($total_long_term_savings === null) {
        $total_long_term_savings = 0;
    }
    mysqli_stmt_close($stmt);
}


// Outstanding borrowed loans (you owe)
$sql_borrowed_loans = "SELECT SUM(amount - paid_amount) AS outstanding FROM loans WHERE user_id = ? AND type = 'borrowed' AND status != 'fully_paid'";
if ($stmt = mysqli_prepare($link, $sql_borrowed_loans)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $outstanding_loans);
    mysqli_stmt_fetch($stmt);
    // Handle null result for outstanding_loans
    if ($outstanding_loans === null) {
        $outstanding_loans = 0;
    }
    mysqli_stmt_close($stmt);
}

// Outstanding lent loans (others owe you)
$sql_lent_loans = "SELECT SUM(amount - paid_amount) AS outstanding FROM loans WHERE user_id = ? AND type = 'lent' AND status != 'fully_paid'";
if ($stmt = mysqli_prepare($link, $sql_lent_loans)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $lent_loans_outstanding);
    mysqli_stmt_fetch($stmt);
    // Handle null result for lent_loans_outstanding
    if ($lent_loans_outstanding === null) {
        $lent_loans_outstanding = 0;
    }
    mysqli_stmt_close($stmt);
}

// Fetch recent transactions (last 10) for dashboard display
$recent_transactions = [];
$sql_recent_transactions = "SELECT transaction_date, type, category, amount, description FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
if ($stmt = mysqli_prepare($link, $sql_recent_transactions)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        // Handle null values for category and description before passing to htmlspecialchars()
        $row['category'] = $row['category'] ?? ''; // Ensure category is handled
        $row['description'] = $row['description'] ?? ''; // Ensure description is handled
        $recent_transactions[] = $row; // Corrected: Store in $recent_transactions
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($link); // Moved this line to the very end
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ড্যাশবোর্ড - ব্যক্তিগত হিসাব</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>স্বাগতম, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>

        <h3>সারসংক্ষেপ (চলতি মাস)</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <h4>মোট আয়</h4>
                <p>BDT <?php echo number_format($total_income, 2); ?></p>
            </div>
            <div class="summary-item">
                <h4>মোট ব্যয়</h4>
                <p>BDT <?php echo number_format($total_expense, 2); ?></p>
            </div>
            <div class="summary-item">
                <h4>হাতে নগদ ও সঞ্চয় (খরচযোগ্য)</h4>
                <p>BDT <?php echo number_format($current_liquid_balance, 2); ?></p>
            </div>
            <div class="summary-item">
                <h4>দীর্ঘমেয়াদী সঞ্চয়</h4>
                <p>BDT <?php echo number_format($total_long_term_savings, 2); ?></p>
            </div>
            <div class="summary-item">
                <h4>আপনার বকেয়া ধার (আপনি দেনা)</h4>
                <p>BDT <?php echo number_format($outstanding_loans, 2); ?></p>
            </div>
            <div class="summary-item">
                <h4>আপনার প্রাপ্য ধার (অন্যরা দেনা)</h4>
                <p>BDT <?php echo number_format($lent_loans_outstanding, 2); ?></p>
            </div>
        </div>

        <h3>সাম্প্রতিক লেনদেনসমূহ</h3>
        <?php if (empty($recent_transactions)): ?>
            <p>কোনো সাম্প্রতিক লেনদেন নেই।</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>তারিখ</th>
                        <th>প্রকার</th>
                        <th>ক্যাটাগরি</th>
                        <th>পরিমাণ</th>
                        <th>বর্ণনা</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $transaction): ?>
                    <tr>
                        <td data-label="তারিখ"><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                        <td data-label="প্রকার"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($transaction['type']))); ?></td>
                        <td data-label="ক্যাটাগরি"><?php echo htmlspecialchars($transaction['category']); ?></td>
                        <td data-label="পরিমাণ">BDT <?php echo number_format(htmlspecialchars($transaction['amount']), 2); ?></td>
                        <td data-label="বর্ণনা"><?php echo htmlspecialchars($transaction['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
