<?php
require_once 'auth.php';
require_once 'ledger.php';

$user_id = $_SESSION['id'];

$stmt = mysqli_prepare($link, "SELECT id, party_name, type, amount, paid_amount FROM loans WHERE user_id = ? AND status != 'fully_paid' ORDER BY party_name");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$loans = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($link, "SELECT id, name, type, current_balance FROM accounts WHERE user_id = ? ORDER BY name");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$accounts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$loan_id = (int)($_POST['loan_id'] ?? $_GET['loan_id'] ?? 0);
$account_id = (int)($_POST['account_id'] ?? 0);
$amount = trim($_POST['amount'] ?? '');
$transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));
$description = trim($_POST['description'] ?? '');
$errors = [];
$form_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_kind = null;
    foreach ($loans as $loan) {
        if ((int)$loan['id'] === $loan_id) {
            $loan_kind = $loan['type'];
        }
    }
    // A repayment on a borrowed loan leaves your account; on a lent loan it comes in.
    $type = $loan_kind === 'lent' ? 'loan_repayment_received' : 'loan_repayment_made';

    [$d, $errors] = ledger_validate([
        'type' => $type,
        'amount' => $amount,
        'transaction_date' => $transaction_date,
        'description' => $description,
        'loan_id' => $loan_id,
        'from_account_id' => $account_id,
        'to_account_id' => $account_id,
    ], $accounts, $loans);

    if (!$errors) {
        try {
            ledger_create($link, $user_id, $d);
            header('location: view_loans.php');
            exit;
        } catch (Throwable $e) {
            error_log('record_repayment: ' . $e->getMessage());
            $form_err = 'পরিশোধ যোগ করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
        }
    }
}

$account_err = $errors['from_account_id'] ?? $errors['to_account_id'] ?? '';
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

        <?php if ($form_err): ?>
            <div style="color:#c0392b; background:#fdecea; padding:10px; border-radius:5px; margin-bottom:15px;"><?php echo e($form_err); ?></div>
        <?php endif; ?>

        <?php if (!$loans): ?>
            <p>কোনো বকেয়া ধার/ঋণ নেই। <a href="add_loan.php">নতুন ধার/ঋণ যোগ করুন</a></p>
        <?php else: ?>
        <form action="record_repayment.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label>ধার/ঋণ নির্বাচন করুন:</label>
                <select name="loan_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                            $kind = $loan['type'] === 'borrowed' ? 'আপনি নিয়েছেন' : 'আপনি দিয়েছেন';
                            $remaining = $loan['amount'] - $loan['paid_amount'];
                        ?>
                        <option value="<?php echo (int)$loan['id']; ?>" <?php echo (int)$loan['id'] === $loan_id ? 'selected' : ''; ?>>
                            <?php echo e($loan['party_name'] . ' (' . $kind . ') - BDT ' . number_format((float)$loan['amount'], 2) . ' (বাকি: BDT ' . number_format($remaining, 2) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo e($errors['loan_id'] ?? ''); ?></span>
            </div>
            <div>
                <label>পরিশোধের পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo e($amount); ?>">
                <span><?php echo e($errors['amount'] ?? ''); ?></span>
            </div>
            <div>
                <label>তারিখ:</label>
                <input type="date" name="transaction_date" value="<?php echo e($transaction_date); ?>">
                <span><?php echo e($errors['transaction_date'] ?? ''); ?></span>
            </div>
            <div>
                <label>অ্যাকাউন্ট:</label>
                <select name="account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo (int)$account['id']; ?>" <?php echo (int)$account['id'] === $account_id ? 'selected' : ''; ?>>
                            <?php echo e($account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format((float)$account['current_balance'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>আপনি পরিশোধ করলে এই অ্যাকাউন্ট থেকে টাকা যাবে; আপনি পেলে এই অ্যাকাউন্টে আসবে।</small>
                <span><?php echo e($account_err); ?></span>
            </div>
            <div>
                <label>বর্ণনা (ঐচ্ছিক):</label>
                <textarea name="description"><?php echo e($description); ?></textarea>
            </div>
            <div>
                <input type="submit" value="পরিশোধ যোগ করুন">
            </div>
        </form>
        <?php endif; ?>
        <p><a href="view_loans.php">ধারের তালিকায় ফিরে যান</a></p>
    </div>
</body>
</html>
