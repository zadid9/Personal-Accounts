<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$loan_id = $amount = $transaction_date = $description = '';
$account_id = '';
$loan_id_err = $amount_err = $date_err = $account_err = '';

// Fetch user's outstanding loans (both borrowed and lent) for dropdowns
$loans = [];
$sql_loans = "SELECT id, party_name, type, amount, paid_amount FROM loans WHERE user_id = ? AND status != 'fully_paid'";
if ($stmt_loans = mysqli_prepare($link, $sql_loans)) {
    mysqli_stmt_bind_param($stmt_loans, "i", $user_id);
    mysqli_stmt_execute($stmt_loans);
    $result_loans = mysqli_stmt_get_result($stmt_loans);
    while ($row = mysqli_fetch_assoc($result_loans)) {
        $loans[] = $row;
    }
    mysqli_stmt_close($stmt_loans);
}

// Fetch user's accounts for dropdowns
$accounts = [];
$sql_accounts = "SELECT id, name, type, current_balance FROM accounts WHERE user_id = ? ORDER BY name";
if ($stmt_accounts = mysqli_prepare($link, $sql_accounts)) {
    mysqli_stmt_bind_param($stmt_accounts, "i", $user_id);
    mysqli_stmt_execute($stmt_accounts);
    $result_accounts = mysqli_stmt_get_result($stmt_accounts);
    while ($row = mysqli_fetch_assoc($result_accounts)) {
        $accounts[] = $row;
    }
    mysqli_stmt_close($stmt_accounts);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loan_id = trim($_POST['loan_id']);
    $amount = trim($_POST['amount']);
    $transaction_date = trim($_POST['transaction_date']);
    $description = trim($_POST['description']);
    $account_id = trim($_POST['account_id']);

    // Basic Validations
    if (empty($loan_id)) { $loan_id_err = 'অনুগ্রহ করে একটি ধার/ঋণ নির্বাচন করুন।'; }
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) { $amount_err = 'পরিমাণের জন্য একটি বৈধ ধনাত্মক সংখ্যা লিখুন।'; }
    if (empty($transaction_date)) { $date_err = 'অনুগ্রহ করে একটি তারিখ লিখুন।'; }
    if (empty($account_id)) { $account_err = 'অনুগ্রহ করে একটি অ্যাকাউন্ট নির্বাচন করুন।'; }

    if (!$loan_id_err && !in_array((int)$loan_id, array_map('intval', array_column($loans, 'id')), true)) {
        $loan_id_err = 'অবৈধ ধার/ঋণ নির্বাচন করা হয়েছে।';
    }
    if (!$account_err && !in_array((int)$account_id, array_map('intval', array_column($accounts, 'id')), true)) {
        $account_err = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
    }

    // If no validation errors, proceed with database operations
    if (empty($loan_id_err) && empty($amount_err) && empty($date_err) && empty($account_err)) {
        // Find the loan type to determine transaction type
        $loan_type = '';
        foreach ($loans as $loan) {
            if ($loan['id'] == $loan_id) {
                $loan_type = $loan['type'];
                break;
            }
        }

        $transaction_type = '';
        if ($loan_type == 'borrowed') {
            $transaction_type = 'loan_repayment_made';
            $from_account_id = $account_id;
            $to_account_id = null;
        } elseif ($loan_type == 'lent') {
            $transaction_type = 'loan_repayment_received';
            $from_account_id = null;
            $to_account_id = $account_id;
        }

        // Start transaction for atomicity
        mysqli_begin_transaction($link);
        $success = true;

        try {
            // Update the loan's paid_amount
            $sql_update_loan = "UPDATE loans SET paid_amount = paid_amount + ? WHERE id = ? AND user_id = ?";
            if ($stmt_update = mysqli_prepare($link, $sql_update_loan)) {
                mysqli_stmt_bind_param($stmt_update, "dii", $amount, $loan_id, $user_id);
                if (!mysqli_stmt_execute($stmt_update)) {
                    $success = false;
                }
                mysqli_stmt_close($stmt_update);
            } else {
                $success = false;
            }

            // Update account balance
            if ($success) {
                if ($transaction_type == 'loan_repayment_made') {
                    $sql_update_account = "UPDATE accounts SET current_balance = current_balance - ? WHERE id = ? AND user_id = ?";
                    if ($stmt_update_acc = mysqli_prepare($link, $sql_update_account)) {
                        mysqli_stmt_bind_param($stmt_update_acc, "dii", $amount, $from_account_id, $user_id);
                        if (!mysqli_stmt_execute($stmt_update_acc)) {
                            $success = false;
                        }
                        mysqli_stmt_close($stmt_update_acc);
                    } else {
                        $success = false;
                    }
                } elseif ($transaction_type == 'loan_repayment_received') {
                    $sql_update_account = "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?";
                    if ($stmt_update_acc = mysqli_prepare($link, $sql_update_account)) {
                        mysqli_stmt_bind_param($stmt_update_acc, "dii", $amount, $to_account_id, $user_id);
                        if (!mysqli_stmt_execute($stmt_update_acc)) {
                            $success = false;
                        }
                        mysqli_stmt_close($stmt_update_acc);
                    } else {
                        $success = false;
                    }
                }
            }

            // Insert a new transaction record
            if ($success) {
                $sql_insert_transaction = "INSERT INTO transactions (user_id, transaction_date, amount, description, type, from_account_id, to_account_id, loan_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt_insert = mysqli_prepare($link, $sql_insert_transaction)) {
                    $to_account_id_to_bind = ($to_account_id === null) ? NULL : $to_account_id;
                    $from_account_id_to_bind = ($from_account_id === null) ? NULL : $from_account_id;
                    mysqli_stmt_bind_param($stmt_insert, "isdssiii", $user_id, $transaction_date, $amount, $description, $transaction_type, $from_account_id_to_bind, $to_account_id_to_bind, $loan_id);

                    if (!mysqli_stmt_execute($stmt_insert)) {
                        $success = false;
                    }
                    mysqli_stmt_close($stmt_insert);
                } else {
                    $success = false;
                }
            }


            // Check if loan is fully paid and update status
            if ($success) {
                $sql_check_loan = "SELECT amount, paid_amount FROM loans WHERE id = ? AND user_id = ?";
                if ($stmt_check_loan = mysqli_prepare($link, $sql_check_loan)) {
                    mysqli_stmt_bind_param($stmt_check_loan, "ii", $loan_id, $user_id);
                    mysqli_stmt_execute($stmt_check_loan);
                    mysqli_stmt_bind_result($stmt_check_loan, $total_amount, $paid_amount);
                    mysqli_stmt_fetch($stmt_check_loan);
                    mysqli_stmt_close($stmt_check_loan);

                    if ($paid_amount >= $total_amount) {
                        $sql_update_status = "UPDATE loans SET status = 'fully_paid' WHERE id = ? AND user_id = ?";
                        if ($stmt_update_status = mysqli_prepare($link, $sql_update_status)) {
                            mysqli_stmt_bind_param($stmt_update_status, "ii", $loan_id, $user_id);
                            if (!mysqli_stmt_execute($stmt_update_status)) {
                                $success = false;
                            }
                            mysqli_stmt_close($stmt_update_status);
                        } else {
                            $success = false;
                        }
                    } else {
                        $sql_update_status = "UPDATE loans SET status = 'partially_paid' WHERE id = ? AND user_id = ?";
                        if ($stmt_update_status = mysqli_prepare($link, $sql_update_status)) {
                            mysqli_stmt_bind_param($stmt_update_status, "ii", $loan_id, $user_id);
                            if (!mysqli_stmt_execute($stmt_update_status)) {
                                $success = false;
                            }
                            mysqli_stmt_close($stmt_update_status);
                        } else {
                            $success = false;
                        }
                    }
                } else {
                    $success = false;
                }
            }

            if ($success) {
                mysqli_commit($link);
                header('location: view_loans.php');
            } else {
                mysqli_rollback($link);
                echo 'পরিশোধ যোগ করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
            }

        } catch (Exception $e) {
            mysqli_rollback($link);
            echo 'একটি অপ্রত্যাশিত ত্রুটি হয়েছে: ' . $e->getMessage();
        }
    }
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ধার/ঋণ পরিশোধ যোগ করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>ধার/ঋণ পরিশোধ যোগ করুন</h2>
        <form action="record_repayment.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label>ধার/ঋণ নির্বাচন করুন:</label>
                <select name="loan_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($loans as $loan): ?>
                        <option value="<?php echo $loan['id']; ?>">
                            <?php
                                $loan_type_text = ($loan['type'] == 'borrowed') ? 'গ্রহণ' : 'প্রদান';
                                $balance = $loan['amount'] - $loan['paid_amount'];
                                $balance_text = ($balance <= 0) ? ' (পরিশোধিত)' : ' (বাকি: BDT ' . number_format($balance, 2) . ')';
                                echo htmlspecialchars($loan['party_name'] . ' (প্রকার: ' . $loan_type_text . ') - BDT ' . number_format($loan['amount'], 2) . $balance_text);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $loan_id_err; ?></span>
            </div>
            <div>
                <label>পরিশোধের পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" value="<?php echo htmlspecialchars($amount); ?>">
                <span><?php echo $amount_err; ?></span>
            </div>
            <div>
                <label>তারিখ:</label>
                <input type="date" name="transaction_date" value="<?php echo htmlspecialchars($transaction_date); ?>">
                <span><?php echo $date_err; ?></span>
            </div>
            <div>
                <label>অ্যাকাউন্ট:</label>
                <select name="account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>">
                            <?php echo htmlspecialchars($account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format($account['current_balance'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $account_err; ?></span>
            </div>
            <div>
                <label>বর্ণনা (ঐচ্ছিক):</label>
                <textarea name="description"><?php echo htmlspecialchars($description); ?></textarea>
            </div>
            <div>
                <input type="submit" value="পরিশোধ যোগ করুন">
            </div>
            <p><a href="view_loans.php">ধারের তালিকায় ফিরে যান</a></p>
        </form>
    </div>
</body>
</html>
