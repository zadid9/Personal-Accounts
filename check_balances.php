<?php
require_once 'auth.php';
require_once 'ledger.php';

$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mismatches = ledger_find_mismatches($link, $user_id);
    $action = $_POST['action'] ?? '';
    $target = (int)($_POST['id'] ?? 0);

    foreach ($mismatches['accounts'] as $acc) {
        if ((int)$acc['id'] !== $target) {
            continue;
        }
        if ($action === 'use_computed') {
            $value = (float)$acc['computed_balance'];
            $stmt = mysqli_prepare($link, "UPDATE accounts SET current_balance = ? WHERE id = ? AND user_id = ?");
        } elseif ($action === 'keep_current') {
            $value = round($acc['current_balance'] - $acc['net_movement'], 2);
            $stmt = mysqli_prepare($link, "UPDATE accounts SET initial_balance = ? WHERE id = ? AND user_id = ?");
        } else {
            break;
        }
        mysqli_stmt_bind_param($stmt, "dii", $value, $target, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    foreach ($mismatches['loans'] as $loan) {
        if ($action === 'fix_loan' && (int)$loan['id'] === $target) {
            $value = (float)$loan['computed_paid'];
            $stmt = mysqli_prepare($link, "UPDATE loans SET paid_amount = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "dii", $value, $target, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            ledger_refresh_loan_status($link, $user_id, $target);
        }
    }

    header('location: check_balances.php?fixed=1');
    exit;
}

$mismatches = ledger_find_mismatches($link, $user_id);
$all_ok = !$mismatches['accounts'] && !$mismatches['loans'];

function money($v): string
{
    return 'BDT ' . number_format((float)$v, 2);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>হিসাব যাচাই</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ok-box { background: #e8f8ee; color: #1e7b45; padding: 14px; border-radius: 6px; text-align: center; }
        .help { color: #555; text-align: left; }
        .fix-buttons form { display: block; margin: 4px 0; }
        .fix-buttons button { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; background: #fff; cursor: pointer; font: inherit; }
        .fix-buttons button:hover { background: #f2f2f2; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>হিসাব যাচাই</h2>
        <p class="help">প্রতিটি অ্যাকাউন্টের ব্যালেন্স আর প্রতিটি ধারের পরিশোধিত পরিমাণ আপনার লেনদেন থেকে নতুন করে হিসাব করে মিলিয়ে দেখা হয়। কোথাও না মিললে এখানে দেখাবে।</p>

        <?php if ($all_ok): ?>
            <div class="ok-box">সব হিসাব মিলে গেছে। কোনো অমিল নেই।</div>
        <?php endif; ?>

        <?php if ($mismatches['accounts']): ?>
            <h3>অ্যাকাউন্ট ব্যালেন্সে অমিল</h3>
            <table>
                <thead>
                    <tr><th>অ্যাকাউন্ট</th><th>প্রাথমিক ব্যালেন্স</th><th>সংরক্ষিত ব্যালেন্স</th><th>লেনদেন থেকে হিসাব</th><th>পার্থক্য</th><th>ঠিক করুন</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($mismatches['accounts'] as $acc): ?>
                        <?php $implied_initial = $acc['current_balance'] - $acc['net_movement']; ?>
                        <tr>
                            <td data-label="অ্যাকাউন্ট"><?php echo e($acc['name']); ?></td>
                            <td data-label="প্রাথমিক ব্যালেন্স"><?php echo money($acc['initial_balance']); ?></td>
                            <td data-label="সংরক্ষিত ব্যালেন্স"><?php echo money($acc['current_balance']); ?></td>
                            <td data-label="লেনদেন থেকে হিসাব"><?php echo money($acc['computed_balance']); ?></td>
                            <td data-label="পার্থক্য"><?php echo money($acc['current_balance'] - $acc['computed_balance']); ?></td>
                            <td data-label="ঠিক করুন" class="fix-buttons">
                                <form method="post" onsubmit="return confirm('বর্তমান ব্যালেন্স <?php echo money($acc['computed_balance']); ?> করা হবে। নিশ্চিত?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="use_computed">
                                    <input type="hidden" name="id" value="<?php echo (int)$acc['id']; ?>">
                                    <button type="submit">লেনদেন অনুযায়ী ব্যালেন্স ঠিক করুন (<?php echo money($acc['computed_balance']); ?>)</button>
                                </form>
                                <form method="post" onsubmit="return confirm('প্রাথমিক ব্যালেন্স <?php echo money($implied_initial); ?> করা হবে, বর্তমান ব্যালেন্স একই থাকবে। নিশ্চিত?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="keep_current">
                                    <input type="hidden" name="id" value="<?php echo (int)$acc['id']; ?>">
                                    <button type="submit">বর্তমান ব্যালেন্স ঠিক আছে, প্রাথমিক ব্যালেন্স বদলান (<?php echo money($implied_initial); ?>)</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="help">কোনটা বেছে নেবেন? আপনার আসল ব্যাংক/ওয়ালেট ব্যালেন্সের সাথে মেলান। সংরক্ষিত ব্যালেন্স যদি আসলটার সমান হয়, তাহলে দ্বিতীয় বাটনটি চাপুন।</p>
        <?php endif; ?>

        <?php if ($mismatches['loans']): ?>
            <h3>ধারের পরিশোধে অমিল</h3>
            <table>
                <thead>
                    <tr><th>পার্টি</th><th>মোট</th><th>সংরক্ষিত পরিশোধ</th><th>লেনদেন থেকে হিসাব</th><th>ঠিক করুন</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($mismatches['loans'] as $loan): ?>
                        <tr>
                            <td data-label="পার্টি"><?php echo e($loan['party_name']); ?></td>
                            <td data-label="মোট"><?php echo money($loan['amount']); ?></td>
                            <td data-label="সংরক্ষিত পরিশোধ"><?php echo money($loan['paid_amount']); ?></td>
                            <td data-label="লেনদেন থেকে হিসাব"><?php echo money($loan['computed_paid']); ?></td>
                            <td data-label="ঠিক করুন" class="fix-buttons">
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="fix_loan">
                                    <input type="hidden" name="id" value="<?php echo (int)$loan['id']; ?>">
                                    <button type="submit">লেনদেন অনুযায়ী ঠিক করুন</button>
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
