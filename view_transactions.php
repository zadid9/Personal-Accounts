<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'ledger.php';

$user_id = $_SESSION['id'];
$transactions = [];

// [পরিবর্তন ১] মাস ফিল্টারিং লজিক
$selected_month = $_GET['month_filter'] ?? '';
if (!is_string($selected_month) || !is_valid_month($selected_month)) {
    $selected_month = date('Y-m');
}

// মাসের শুরু এবং শেষ তারিখ নির্ধারণ করুন
$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// SQL কুয়েরি
// created_at is a DATETIME in the DB server's local time; convert it to Bangladesh time for display.
$sql = "SELECT t.id, t.transaction_date, t.title, t.amount, t.description, t.type, t.category,
               CONVERT_TZ(t.created_at, @@session.time_zone, '+06:00') AS entered_at,
               fa.name AS from_account_name, ta.name AS to_account_name,
               l.party_name AS loan_party_name, l.type AS loan_type
        FROM transactions t
        LEFT JOIN accounts fa ON t.from_account_id = fa.id
        LEFT JOIN accounts ta ON t.to_account_id = ta.id
        LEFT JOIN loans l ON t.loan_id = l.id
        WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?
        ORDER BY t.transaction_date DESC, t.created_at DESC";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $row['title'] = $row['title'] ?? '';
        $row['from_account_name'] = $row['from_account_name'] ?? '';
        $row['to_account_name'] = $row['to_account_name'] ?? '';
        $row['loan_party_name'] = $row['loan_party_name'] ?? '';
        $row['loan_type'] = $row['loan_type'] ?? '';
        $row['description'] = $row['description'] ?? '';
        $row['category'] = $row['category'] ?? '';

        $transactions[] = $row;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>লেনদেনের তালিকা</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .header-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-box h2 {
            margin: 0;
            text-align: left;
        }
        .filter-section {
            background: #fff;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
        .filter-section input[type="month"] {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter-section input[type="submit"] {
            padding: 6px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-section input[type="submit"]:hover {
            background-color: #0056b3;
        }
        /* Compact transaction table: short columns shrink to their content, the title column takes the rest. */
        .tx-table { font-size: 0.92rem; }
        .tx-table th, .tx-table td { padding: 6px 8px; vertical-align: top; }
        .tx-table .fit { width: 1%; white-space: nowrap; }
        .tx-table .tx-title { word-break: break-word; }
        .tx-table .tx-title small { color: #777; }
        .tx-table .amount { text-align: right; }
        .tx-table .entered-at { display: block; color: #888; font-size: 0.8em; margin: 0; }
        .tx-table .row-actions { display: flex; flex-wrap: nowrap; align-items: center; gap: 12px; margin: 0; white-space: nowrap; }
        .tx-table .row-actions a, .tx-table .row-actions form { margin: 0; padding: 0; display: inline-block; }
        .tx-table .row-actions button { width: auto; margin: 0; line-height: inherit; }
        @media (max-width: 768px) {
            .tx-table tr { margin-bottom: 8px; }
            .tx-table td {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                gap: 10px;
                padding: 5px 10px;
                text-align: right;
                width: auto;
                white-space: normal;
            }
            .tx-table td:before {
                position: static;
                width: auto;
                flex-shrink: 0;
                padding-right: 0;
            }
        }
        @media (max-width: 600px) {
            .header-box {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-section {
                width: 100%;
                display: flex;
                justify-content: space-between;
            }
            .filter-section form {
                width: 100%;
                display: flex;
                gap: 5px;
            }
            .filter-section input[type="month"] {
                flex-grow: 1;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <!-- হেডার এবং ফিল্টার এখন একই লাইনে -->
        <div class="header-box">
            <h2>লেনদেনের তালিকা</h2>
            <div class="filter-section">
                <form action="" method="GET" style="display: flex; gap: 8px; align-items: center;">
                    <input type="month" id="month_filter" name="month_filter" value="<?php echo e($selected_month); ?>">
                    <input type="submit" value="দেখুন">
                </form>
            </div>
        </div>

        <p style="text-align: left; margin-bottom: 20px;">
            <a href="dashboard.php">ড্যাশবোর্ড</a> | <a href="add_transaction.php">নতুন লেনদেন যোগ করুন</a>
        </p>

        <h3 style="text-align: center; color: #555; background: #f9f9f9; padding: 10px; border-radius: 5px;">
            <?php echo date('F Y', strtotime($start_date)); ?> মাসের লেনদেনসমূহ
        </h3>

        <?php if (empty($transactions)): ?>
            <p style="text-align: center; padding: 20px; color: #888;">এই মাসে কোনো লেনদেন পাওয়া যায়নি।</p>
        <?php else: ?>
            <table class="tx-table">
                <thead>
                    <tr>
                        <th class="fit">তারিখ</th>
                        <th>নাম/বিবরণ</th>
                        <th class="fit">প্রকার</th>
                        <th>ক্যাটাগরি</th>
                        <th class="fit amount">পরিমাণ (BDT)</th>
                        <th>অ্যাকাউন্ট</th>
                        <th class="fit">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td data-label="তারিখ" class="fit">
                            <div>
                                <?php echo e($transaction['transaction_date']); ?>
                                <?php if (!empty($transaction['entered_at'])): ?>
                                    <small class="entered-at" title="যখন এন্ট্রি করা হয়েছে">এন্ট্রি: <?php echo e(date('d M Y, h:i A', strtotime($transaction['entered_at']))); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="নাম/বিবরণ" class="tx-title">
                            <strong><?php echo htmlspecialchars($transaction['title'] ?: 'নাম নেই'); ?></strong>
                            <?php if(!empty($transaction['description'])): ?>
                                <br><small><i><?php echo htmlspecialchars($transaction['description']); ?></i></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="প্রকার" class="fit"><?php echo e(TRANSACTION_TYPES[$transaction['type']] ?? $transaction['type']); ?></td>
                        <td data-label="ক্যাটাগরি"><?php echo htmlspecialchars($transaction['category']); ?></td>
                        <td data-label="পরিমাণ (BDT)" class="fit amount"><strong><?php echo number_format($transaction['amount'], 2); ?></strong></td>
                        <td data-label="অ্যাকাউন্ট">
                            <?php 
                                if($transaction['type'] == 'income' || $transaction['type'] == 'loan_received') echo "To: " . htmlspecialchars($transaction['to_account_name']);
                                elseif($transaction['type'] == 'expense' || $transaction['type'] == 'loan_given') echo "From: " . htmlspecialchars($transaction['from_account_name']);
                                else echo htmlspecialchars($transaction['from_account_name']) . " -> " . htmlspecialchars($transaction['to_account_name']);
                            ?>
                        </td>
                        <td data-label="অ্যাকশন" class="fit action-links">
                            <div class="row-actions">
                                <a href="edit_transaction.php?id=<?php echo (int)$transaction['id']; ?>">সম্পাদনা</a>
                                <form action="delete_transaction.php" method="post" class="inline-form" onsubmit="return confirm('মুছে ফেলবেন?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$transaction['id']; ?>">
                                    <button type="submit" class="link-button delete">মুছুন</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>