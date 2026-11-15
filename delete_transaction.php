<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int)($_POST['id'] ?? 0) > 0) {
    $id = (int)$_POST['id'];

    mysqli_begin_transaction($link);
    $success = true;

    try {
        // Step 1: Fetch transaction details to reverse its effect
        $sql_fetch = "SELECT amount, type, from_account_id, to_account_id, loan_id FROM transactions WHERE id = ? AND user_id = ?";
        if ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
            mysqli_stmt_bind_param($stmt_fetch, "ii", $id, $user_id);
            mysqli_stmt_execute($stmt_fetch);
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            $transaction = mysqli_fetch_assoc($result_fetch);
            mysqli_stmt_close($stmt_fetch);
        } else {
            $success = false;
            throw new Exception("লেনদেনের ডেটা লোড করতে সমস্যা হয়েছে।");
        }

        if (!$transaction) {
            $success = false;
            throw new Exception("লেনদেন পাওয়া যায়নি।");
        }

        $amount = $transaction['amount'];
        $type = $transaction['type'];
        $from_account_id = $transaction['from_account_id'];
        $to_account_id = $transaction['to_account_id'];
        $loan_id = $transaction['loan_id'];

        // Step 2: Reverse the effect on account balances
        if ($type == 'income' || $type == 'loan_received' || $type == 'loan_repayment_received') {
            if ($to_account_id) {
                $sql_update_account = "UPDATE accounts SET current_balance = current_balance - ? WHERE id = ? AND user_id = ?";
                if ($stmt_update = mysqli_prepare($link, $sql_update_account)) {
                    mysqli_stmt_bind_param($stmt_update, "dii", $amount, $to_account_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_update)) { $success = false; }
                    mysqli_stmt_close($stmt_update);
                } else { $success = false; }
            }
        } elseif ($type == 'expense' || $type == 'loan_given' || $type == 'loan_repayment_made') {
            if ($from_account_id) {
                $sql_update_account = "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?";
                if ($stmt_update = mysqli_prepare($link, $sql_update_account)) {
                    mysqli_stmt_bind_param($stmt_update, "dii", $amount, $from_account_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_update)) { $success = false; }
                    mysqli_stmt_close($stmt_update);
                } else { $success = false; }
            }
        } elseif ($type == 'transfer' || $type == 'reimbursement') {
            if ($from_account_id) {
                $sql_update_from = "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?";
                if ($stmt_from = mysqli_prepare($link, $sql_update_from)) {
                    mysqli_stmt_bind_param($stmt_from, "dii", $amount, $from_account_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_from)) { $success = false; }
                    mysqli_stmt_close($stmt_from);
                } else { $success = false; }
            }
            if ($success && $to_account_id) {
                $sql_update_to = "UPDATE accounts SET current_balance = current_balance - ? WHERE id = ? AND user_id = ?";
                if ($stmt_to = mysqli_prepare($link, $sql_update_to)) {
                    mysqli_stmt_bind_param($stmt_to, "dii", $amount, $to_account_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_to)) { $success = false; }
                    mysqli_stmt_close($stmt_to);
                } else { $success = false; }
            }
        }

        // Step 3: Reverse the effect on loan paid_amount if applicable
        // Only repayments change paid_amount; loan_received/loan_given never touched it.
        if ($success && $loan_id && ($type == 'loan_repayment_made' || $type == 'loan_repayment_received')) {
            $sql_update_loan = "UPDATE loans SET paid_amount = paid_amount - ? WHERE id = ? AND user_id = ?";
            if ($stmt_loan_update = mysqli_prepare($link, $sql_update_loan)) {
                mysqli_stmt_bind_param($stmt_loan_update, "dii", $amount, $loan_id, $user_id);
                if (!mysqli_stmt_execute($stmt_loan_update)) { $success = false; }
                mysqli_stmt_close($stmt_loan_update);
            } else { $success = false; }

            if ($success) {
                $sql_update_loan_status = "UPDATE loans SET status = CASE
                        WHEN paid_amount >= amount THEN 'fully_paid'
                        WHEN paid_amount > 0 THEN 'partially_paid'
                        ELSE 'outstanding' END
                    WHERE id = ? AND user_id = ?";
                if ($stmt_update_loan_status = mysqli_prepare($link, $sql_update_loan_status)) {
                    mysqli_stmt_bind_param($stmt_update_loan_status, "ii", $loan_id, $user_id);
                    if (!mysqli_stmt_execute($stmt_update_loan_status)) { $success = false; }
                    mysqli_stmt_close($stmt_update_loan_status);
                } else { $success = false; }
            }
        }

        // Step 4: Delete the transaction record
        if ($success) {
            $sql_delete = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
            if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                mysqli_stmt_bind_param($stmt_delete, "ii", $id, $user_id);
                if (!mysqli_stmt_execute($stmt_delete)) {
                    $success = false;
                }
                mysqli_stmt_close($stmt_delete);
            } else {
                $success = false;
            }
        }

        if ($success) {
            mysqli_commit($link);
            header("location: view_transactions.php");
            exit();
        } else {
            mysqli_rollback($link);
            echo "লেনদেন মুছে ফেলতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।";
        }

    } catch (Exception $e) {
        mysqli_rollback($link);
        echo "লেনদেন মুছে ফেলতে একটি অপ্রত্যাশিত ত্রুটি হয়েছে: " . $e->getMessage();
    }
    mysqli_close($link);
} else {
    // If ID is not provided, redirect to view transactions page
    header("location: view_transactions.php");
    exit();
}
?>
