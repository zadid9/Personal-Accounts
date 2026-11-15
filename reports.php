<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];

// --- [১] মাস নির্বাচন লজিক সংশোধন শুরু ---

// বর্তমান মাস/বছর ডিফল্ট হিসেবে সেট করুন
$current_month_year = date('Y-m');
$selected_month_year = $current_month_year;

// চেক করুন ফর্মে কোনো মাস/বছর সাবমিট হয়েছে কিনা (ফর্মটি GET ব্যবহার করে)
if (is_string($_GET['month_year'] ?? null) && is_valid_month($_GET['month_year'])) {
    $selected_month_year = $_GET['month_year'];
}

// নির্বাচিত মাস/বছরের জন্য শুরুর ও শেষের তারিখ গণনা করুন
$start_date = $selected_month_year . '-01';
// নির্বাচিত মাসের শেষ দিনটি খুঁজে বের করুন
$end_date = date('Y-m-t', strtotime($start_date));

// এই ভেরিয়েবলটি ইনপুট ফিল্ডের ভ্যালু ধরে রাখার জন্য ব্যবহৃত হবে
$month_year_value = $selected_month_year;

// এখন $start_date এবং $end_date-এ নির্বাচিত মাসের সঠিক তারিখ রেঞ্জ আছে।
// $filter_type-এর অতিরিক্ত লজিক এখানে আর প্রয়োজন নেই কারণ এটি শুধুমাত্র মাসিক রিপোর্ট।

// --- [১] মাস নির্বাচন লজিক সংশোধন শেষ ---


// Fetching data for the selected period
// Total Income and Expense for the selected month
$total_income = 0;
$total_expense = 0;

// Total Income
$sql_income = "SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?";
if ($stmt_income = mysqli_prepare($link, $sql_income)) {
    mysqli_stmt_bind_param($stmt_income, "iss", $user_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt_income);
    mysqli_stmt_bind_result($stmt_income, $total_income);
    mysqli_stmt_fetch($stmt_income);
    if ($total_income === null) {
        $total_income = 0;
    }
    mysqli_stmt_close($stmt_income);
}

// Total Expense (including transfer_out and loan_repayment_made)
$sql_expense = "SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND (type = 'expense' OR type = 'transfer_out' OR type = 'loan_repayment_made') AND transaction_date BETWEEN ? AND ?";
if ($stmt_expense = mysqli_prepare($link, $sql_expense)) {
    mysqli_stmt_bind_param($stmt_expense, "iss", $user_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt_expense);
    mysqli_stmt_bind_result($stmt_expense, $total_expense);
    mysqli_stmt_fetch($stmt_expense);
    if ($total_expense === null) {
        $total_expense = 0;
    }
    mysqli_stmt_close($stmt_expense);
}

// Income Breakdown by Category
$income_by_category = [];
$sql_income_cat = "SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC";
if ($stmt_income_cat = mysqli_prepare($link, $sql_income_cat)) {
    mysqli_stmt_bind_param($stmt_income_cat, "iss", $user_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt_income_cat);
    $result_income_cat = mysqli_stmt_get_result($stmt_income_cat);
    while ($row = mysqli_fetch_assoc($result_income_cat)) {
        $income_by_category[$row['category']] = $row['total'];
    }
    mysqli_stmt_close($stmt_income_cat);
}

// Expense Breakdown by Category
$expense_by_category = [];
$sql_expense_cat = "SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC";
if ($stmt_expense_cat = mysqli_prepare($link, $sql_expense_cat)) {
    mysqli_stmt_bind_param($stmt_expense_cat, "iss", $user_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt_expense_cat);
    $result_expense_cat = mysqli_stmt_get_result($stmt_expense_cat);
    while ($row = mysqli_fetch_assoc($result_expense_cat)) {
        $expense_by_category[$row['category']] = $row['total'];
    }
    mysqli_stmt_close($stmt_expense_cat);
}

// Data for Chart.js
$income_chart_labels = array_keys($income_by_category);
$income_chart_data = array_values($income_by_category);
$expense_chart_labels = array_keys($expense_by_category);
$expense_chart_data = array_values($expense_by_category);

// Calculate Savings/Deficit
$net_flow = $total_income - $total_expense;


?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>মাসিক রিপোর্ট - পার্সোনাল ফাইনান্স ম্যানেজার</title>
    <link rel="stylesheet" href="style.css"> <!-- Assuming a style.css exists -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* নেভিগেশন বারের জন্য নতুন স্টাইল */
        .navbar {
            overflow: hidden;
            background-color: #333;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start;
        }

        .navbar a {
            display: block;
            color: white;
            text-align: center;
            padding: 14px 20px;
            text-decoration: none;
            font-size: 17px;
        }

        .navbar a:hover {
            background-color: #555;
            color: white;
        }

        .navbar a.active {
            background-color: #4CAF50;
            color: white;
        }
        .navbar .logout-link {
            margin-left: auto; /* Push logout to the right */
            background-color: #f44336;
        }
        .navbar .logout-link:hover {
            background-color: #e53935;
        }
        /* অন্য স্টাইলগুলি অপরিবর্তিত */
        .report-summary {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            text-align: center;
        }
        .summary-box {
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 30%;
        }
        .income { background-color: #e6ffed; border-left: 5px solid #4CAF50; }
        .expense { background-color: #ffe6e6; border-left: 5px solid #F44336; }
        .net-flow-positive { background-color: #e0f7fa; border-left: 5px solid #00BCD4; }
        .net-flow-negative { background-color: #fce4ec; border-left: 5px solid #E91E63; }
        .chart-container {
            width: 45%;
            margin: 20px;
        }
        .charts-flex {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        .report-filters {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- নেভিগেশন বার যোগ করা হলো -->
        <nav class="navbar">
            <a href="dashboard.php">ড্যাশবোর্ড</a>
            <a href="add_transaction.php">লেনদেন যোগ করুন</a>
            <a href="view_transactions.php">লেনদেন দেখুন</a>
            <a href="reports.php" class="active">রিপোর্ট</a>
            <a href="view_accounts.php">অ্যাকাউন্ট</a>
            <a href="logout.php" class="logout-link">লগআউট</a>
        </nav>
        
        <h2>মাসিক রিপোর্ট</h2>

        <div class="report-filters">
            <!-- ফর্মে নির্বাচিত মাসটি ধরে রাখার জন্য PHP ভেরিয়েবল ব্যবহার করা হয়েছে -->
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <label for="month_year">মাস নির্বাচন করুন:</label>
                <!-- [২] HTML ইনপুট ভ্যালু সংশোধন -->
                <input type="month" id="month_year" name="month_year" value="<?php echo htmlspecialchars($month_year_value); ?>">
                <input type="submit" value="রিপোর্ট দেখান">
            </form>
            <p style="margin-top: 10px;">
                <strong>রিপোর্ট সময়কাল:</strong> 
                <?php 
                    echo date('F, Y', strtotime($start_date)); 
                    // আপনার ব্যবহৃত বাংলা তারিখ স্ট্রিং ফরম্যাটিং এর জন্য কোনো কাস্টম ফাংশন থাকলে তা এখানে ব্যবহার করা যেতে পারে
                ?> 
            </p>
        </div>

        <h3>লেনদেনের সংক্ষিপ্ত বিবরণ</h3>
        <div class="report-summary">
            <div class="summary-box income">
                <h4>মোট আয়</h4>
                <p>BDT <?php echo number_format($total_income, 2); ?></p>
            </div>
            <div class="summary-box expense">
                <h4>মোট ব্যয়</h4>
                <p>BDT <?php echo number_format($total_expense, 2); ?></p>
            </div>
            <div class="summary-box <?php echo ($net_flow >= 0) ? 'net-flow-positive' : 'net-flow-negative'; ?>">
                <h4>নীট সঞ্চয় / ঘাটতি</h4>
                <p>BDT <?php echo number_format($net_flow, 2); ?></p>
                <small><?php echo ($net_flow >= 0) ? 'এই মাসে সঞ্চয় হয়েছে' : 'এই মাসে ঘাটতি হয়েছে'; ?></small>
            </div>
        </div>

        <h3>ক্যাটাগরি অনুযায়ী বিশ্লেষণ</h3>
        <div class="charts-flex">
            <div class="chart-container">
                <?php if (!empty($income_by_category)): ?>
                    <canvas id="incomeChart"></canvas>
                <?php else: ?>
                    <p>এই সময়ের মধ্যে আয়ের কোনো ডেটা নেই।</p>
                <?php endif; ?>
            </div>

            <div class="chart-container">
                <?php if (!empty($expense_by_category)): ?>
                    <canvas id="expenseChart"></canvas>
                <?php else: ?>
                    <p>এই সময়ের মধ্যে ব্যয়ের কোনো ডেটা নেই।</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail table of income and expense by category (Optional but helpful) -->
        <h3 style="margin-top: 30px;">ক্যাটাগরিভিত্তিক বিস্তারিত</h3>
        <div style="display: flex; justify-content: space-around;">
            <div style="width: 45%;">
                <h4>আয়ের তালিকা</h4>
                <?php if (!empty($income_by_category)): ?>
                    <table>
                        <thead>
                            <tr><th>ক্যাটাগরি</th><th>পরিমাণ (BDT)</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($income_by_category as $category => $amount): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($amount, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>এই মাসে কোনো আয়ের লেনদেন নেই।</p>
                <?php endif; ?>
            </div>

            <div style="width: 45%;">
                <h4>ব্যয়ের তালিকা</h4>
                <?php if (!empty($expense_by_category)): ?>
                    <table>
                        <thead>
                            <tr><th>ক্যাটাগরি</th><th>পরিমাণ (BDT)</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_by_category as $category => $amount): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category); ?></td>
                                    <td style="text-align: right;"><?php echo number_format($amount, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>এই মাসে কোনো ব্যয়ের লেনদেন নেই।</p>
                <?php endif; ?>
            </div>
        </div>


    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Income Chart
            const incomeCtx = document.getElementById('incomeChart');
            if (incomeCtx && <?php echo json_encode(!empty($income_by_category)); ?>) {
                new Chart(incomeCtx, {
                    type: 'doughnut', // Doughnut chart for income breakdown
                    data: {
                        labels: <?php echo json_encode($income_chart_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($income_chart_data); ?>,
                            backgroundColor: [
                                '#4CAF50', '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800', '#FF5722', '#F44336', '#E91E63', '#9C27B0',
                                '#673AB7', '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39', '#FFEB3B'
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'আয় (ক্যাটাগরি অনুযায়ী)'
                            }
                        }
                    }
                });
            }

            // Expense Chart
            const expenseCtx = document.getElementById('expenseChart');
            if (expenseCtx && <?php echo json_encode(!empty($expense_by_category)); ?>) {
                new Chart(expenseCtx, {
                    type: 'doughnut', // Doughnut chart for expense breakdown
                    data: {
                        labels: <?php echo json_encode($expense_chart_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($expense_chart_data); ?>,
                            backgroundColor: [
                                '#F44336', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50',
                                '#8BC34A', '#CDDC39', '#FFEB3B', '#FFC107', '#FF9800', '#FF5722', '#F44336', '#E91E63', '#9C27B0', '#673AB7'
                            ],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'ব্যয় (ক্যাটাগরি অনুযায়ী)'
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>