<?php
require_once 'auth.php';
require_once 'config.php';

const TYPE_OPTIONS = [
    'income' => 'আয় (Income)',
    'expense' => 'ব্যয় (Expense)',
    'transfer' => 'ট্রান্সফার (Transfer)',
    'reimbursement' => 'ফেরতযোগ্য লেনদেন (Reimbursement)',
    'loan_received' => 'ধার গ্রহণ (Loan Received)',
    'loan_given' => 'ধার প্রদান (Loan Given)',
    'loan_repayment_made' => 'ধার পরিশোধ (Repayment Made)',
    'loan_repayment_received' => 'ধার প্রাপ্তি (Repayment Received)',
];
const FROM_TYPES = ['expense', 'transfer', 'reimbursement', 'loan_given', 'loan_repayment_made'];
const TO_TYPES = ['income', 'transfer', 'reimbursement', 'loan_received', 'loan_repayment_received'];
const LOAN_TYPES = ['loan_received', 'loan_given', 'loan_repayment_made', 'loan_repayment_received'];
const REPAYMENT_TYPES = ['loan_repayment_made', 'loan_repayment_received'];
const CATEGORY_TYPES = ['income', 'expense', 'reimbursement'];

function adjust_account($link, $user_id, $account_id, $delta) {
    $stmt = mysqli_prepare($link, "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "dii", $delta, $account_id, $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('অ্যাকাউন্ট ব্যালেন্স আপডেট ব্যর্থ হয়েছে।');
    }
    mysqli_stmt_close($stmt);
}

// $sign = 1 applies the transaction's effect, -1 reverses it.
function apply_effect($link, $user_id, $type, $amount, $from_id, $to_id, $loan_id, $sign) {
    $amount = $sign * (float)$amount;

    if ($from_id && in_array($type, FROM_TYPES)) {
        adjust_account($link, $user_id, $from_id, -$amount);
    }
    if ($to_id && in_array($type, TO_TYPES)) {
        adjust_account($link, $user_id, $to_id, $amount);
    }
    if ($loan_id && in_array($type, REPAYMENT_TYPES)) {
        $stmt = mysqli_prepare($link, "UPDATE loans SET paid_amount = paid_amount + ? WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "dii", $amount, $loan_id, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('ধারের পরিশোধিত পরিমাণ আপডেট ব্যর্থ হয়েছে।');
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($link, "UPDATE loans SET status = CASE
                WHEN paid_amount >= amount THEN 'fully_paid'
                WHEN paid_amount > 0 THEN 'partially_paid'
                ELSE 'outstanding' END
            WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $loan_id, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('ধারের অবস্থা আপডেট ব্যর্থ হয়েছে।');
        }
        mysqli_stmt_close($stmt);
    }
}

$user_id = $_SESSION['id'];

$type_err = $amount_err = $date_err = $from_account_err = $to_account_err = $loan_id_err = $form_err = '';

$accounts = [];
$stmt = mysqli_prepare($link, "SELECT id, name, type, current_balance FROM accounts WHERE user_id = ? ORDER BY name");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $accounts[] = $row;
}
mysqli_stmt_close($stmt);

$loans = [];
$stmt = mysqli_prepare($link, "SELECT id, party_name, amount, paid_amount, type FROM loans WHERE user_id = ? ORDER BY party_name");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $loans[] = $row;
}
mysqli_stmt_close($stmt);

$categories = [];
$stmt = mysqli_prepare($link, "SELECT DISTINCT category FROM transactions WHERE user_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row['category'];
}
mysqli_stmt_close($stmt);

// The id arrives via GET when the page is opened and via the hidden field when the form is posted.
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
    header("location: view_transactions.php");
    exit();
}

$stmt = mysqli_prepare($link, "SELECT type, title, category, amount, transaction_date, description, from_account_id, to_account_id, loan_id
                               FROM transactions WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$old = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$old) {
    echo "ত্রুটি: লেনদেনটি পাওয়া যায়নি।";
    exit();
}

$type = $old['type'];
$title = $old['title'] ?? '';
$category = $old['category'] ?? '';
$amount = $old['amount'];
$transaction_date = $old['transaction_date'];
$description = $old['description'] ?? '';
$from_account_id = $old['from_account_id'];
$to_account_id = $old['to_account_id'];
$loan_id = $old['loan_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = trim($_POST['type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $from_account_id = (in_array($type, FROM_TYPES) && !empty($_POST['from_account_id'])) ? (int)$_POST['from_account_id'] : null;
    $to_account_id = (in_array($type, TO_TYPES) && !empty($_POST['to_account_id'])) ? (int)$_POST['to_account_id'] : null;
    $loan_id = (in_array($type, LOAN_TYPES) && !empty($_POST['loan_id'])) ? (int)$_POST['loan_id'] : null;

    if (!array_key_exists($type, TYPE_OPTIONS)) {
        $type_err = 'অনুগ্রহ করে লেনদেনের প্রকার নির্বাচন করুন।';
    }
    if ($amount === '' || !is_numeric($amount) || $amount <= 0) {
        $amount_err = 'পরিমাণের জন্য একটি বৈধ ধনাত্মক সংখ্যা লিখুন।';
    }
    if ($transaction_date === '') {
        $date_err = 'অনুগ্রহ করে তারিখ নির্বাচন করুন।';
    }
    if (in_array($type, FROM_TYPES) && !$from_account_id) {
        $from_account_err = 'কোথা থেকে লেনদেন হয়েছে সেই অ্যাকাউন্টটি নির্বাচন করুন।';
    }
    if (in_array($type, TO_TYPES) && !$to_account_id) {
        $to_account_err = 'কোথায় লেনদেন হয়েছে সেই অ্যাকাউন্টটি নির্বাচন করুন।';
    }
    if (in_array($type, LOAN_TYPES) && !$loan_id) {
        $loan_id_err = 'অনুগ্রহ করে সংশ্লিষ্ট ধার/ঋণ নির্বাচন করুন।';
    }
    $owned_account_ids = array_map('intval', array_column($accounts, 'id'));
    $owned_loan_ids = array_map('intval', array_column($loans, 'id'));
    if ($from_account_id && !in_array($from_account_id, $owned_account_ids, true)) {
        $from_account_err = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
    }
    if ($to_account_id && !in_array($to_account_id, $owned_account_ids, true)) {
        $to_account_err = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
    }
    if ($loan_id && !in_array($loan_id, $owned_loan_ids, true)) {
        $loan_id_err = 'অবৈধ ধার/ঋণ নির্বাচন করা হয়েছে।';
    }
    if ($from_account_id && $to_account_id && $from_account_id == $to_account_id) {
        $from_account_err = $to_account_err = 'উৎস এবং গন্তব্য অ্যাকাউন্ট একই হতে পারে না।';
    }
    if (!in_array($type, CATEGORY_TYPES) || $category === '') {
        $category = null;
    }

    if ($type_err || $amount_err || $date_err || $from_account_err || $to_account_err || $loan_id_err) {
        $form_err = 'লেনদেন সেভ হয়নি। নিচের ত্রুটিগুলো ঠিক করুন।';
    } else {
        mysqli_begin_transaction($link);
        try {
            apply_effect($link, $user_id, $old['type'], $old['amount'], $old['from_account_id'], $old['to_account_id'], $old['loan_id'], -1);

            $amount_f = (float)$amount;
            $stmt = mysqli_prepare($link, "UPDATE transactions SET
                    type = ?, title = ?, category = ?, amount = ?, transaction_date = ?, description = ?,
                    from_account_id = ?, to_account_id = ?, loan_id = ?
                WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "sssdssiiiii",
                $type, $title, $category, $amount_f, $transaction_date, $description,
                $from_account_id, $to_account_id, $loan_id, $id, $user_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('লেনদেন আপডেট ব্যর্থ হয়েছে।');
            }
            mysqli_stmt_close($stmt);

            apply_effect($link, $user_id, $type, $amount_f, $from_account_id, $to_account_id, $loan_id, 1);

            mysqli_commit($link);
            header("location: view_transactions.php?month_filter=" . urlencode(substr($transaction_date, 0, 7)));
            exit();
        } catch (Throwable $e) {
            mysqli_rollback($link);
            $form_err = 'লেনদেন আপডেট করার সময় ত্রুটি হয়েছে: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>লেনদেন সম্পাদনা করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>লেনদেন সম্পাদনা করুন</h2>

        <?php if ($form_err): ?>
            <div class="error-message" style="color:#c0392b; background:#fdecea; padding:10px; border-radius:5px; margin-bottom:15px;">
                <?php echo htmlspecialchars($form_err); ?>
            </div>
        <?php endif; ?>

        <form action="edit_transaction.php?id=<?php echo $id; ?>" method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php echo csrf_field(); ?>

            <div>
                <label>লেনদেনের প্রকার:</label>
                <select id="transaction_type" name="type" required>
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach (TYPE_OPTIONS as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($type == $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $type_err; ?></span>
            </div>

            <div>
                <label>লেনদেনের নাম (টাইটেল):</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" placeholder="উদা: বাজার খরচ, বেতন, ইত্যাদি">
            </div>

            <div id="category_field">
                <label>ক্যাটাগরি:</label>
                <input type="text" name="category" list="existingCategories" value="<?php echo htmlspecialchars($category ?? ''); ?>" placeholder="যেমন: খাবার, বেতন, ইউটিলিটি">
                <datalist id="existingCategories">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div>
                <label>পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" value="<?php echo htmlspecialchars($amount); ?>" required>
                <span><?php echo $amount_err; ?></span>
            </div>

            <div>
                <label>তারিখ:</label>
                <input type="date" name="transaction_date" value="<?php echo htmlspecialchars($transaction_date); ?>" required>
                <span><?php echo $date_err; ?></span>
            </div>

            <div id="from_account_field">
                <label>থেকে অ্যাকাউন্ট (From Account):</label>
                <select name="from_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>" <?php echo ($from_account_id == $account['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format($account['current_balance'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $from_account_err; ?></span>
            </div>

            <div id="to_account_field">
                <label>তে অ্যাকাউন্ট (To Account):</label>
                <select name="to_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>" <?php echo ($to_account_id == $account['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format($account['current_balance'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $to_account_err; ?></span>
            </div>

            <div id="loan_field">
                <label>ধার/ঋণ নির্বাচন করুন:</label>
                <select id="loan_id_select" name="loan_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($loans as $loan): ?>
                        <option value="<?php echo $loan['id']; ?>" data-type="<?php echo htmlspecialchars($loan['type']); ?>" <?php echo ($loan_id == $loan['id']) ? 'selected' : ''; ?>>
                            <?php
                                $remaining = $loan['amount'] - $loan['paid_amount'];
                                $loan_status = ($remaining <= 0) ? ' (পরিশোধিত)' : ' (বাকি: BDT ' . number_format($remaining, 2) . ')';
                                echo htmlspecialchars($loan['party_name'] . ' (' . ucfirst($loan['type']) . ') - BDT ' . number_format($loan['amount'], 2) . $loan_status);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $loan_id_err; ?></span>
            </div>

            <div>
                <label>বর্ণনা (ঐচ্ছিক):</label>
                <textarea name="description"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
            </div>

            <div>
                <input type="submit" value="আপডেট করুন">
            </div>
            <p><a href="view_transactions.php">সকল লেনদেনে ফিরে যান</a></p>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const FROM_TYPES = <?php echo json_encode(FROM_TYPES); ?>;
            const TO_TYPES = <?php echo json_encode(TO_TYPES); ?>;
            const LOAN_TYPES = <?php echo json_encode(LOAN_TYPES); ?>;
            const CATEGORY_TYPES = <?php echo json_encode(CATEGORY_TYPES); ?>;
            const BORROWED_LOAN_TYPES = ['loan_received', 'loan_repayment_made'];

            const transactionType = document.getElementById('transaction_type');
            const categoryField = document.getElementById('category_field');
            const fromAccountField = document.getElementById('from_account_field');
            const toAccountField = document.getElementById('to_account_field');
            const loanField = document.getElementById('loan_field');
            const loanSelect = document.getElementById('loan_id_select');

            function toggleFields() {
                const t = transactionType.value;
                categoryField.classList.toggle('hidden-field', !CATEGORY_TYPES.includes(t));
                fromAccountField.classList.toggle('hidden-field', !FROM_TYPES.includes(t));
                toAccountField.classList.toggle('hidden-field', !TO_TYPES.includes(t));
                loanField.classList.toggle('hidden-field', !LOAN_TYPES.includes(t));

                if (LOAN_TYPES.includes(t)) {
                    const wanted = BORROWED_LOAN_TYPES.includes(t) ? 'borrowed' : 'lent';
                    Array.from(loanSelect.options).forEach(option => {
                        option.style.display = (option.value === '' || option.dataset.type === wanted) ? '' : 'none';
                    });
                }
            }

            transactionType.addEventListener('change', toggleFields);
            toggleFields();
        });
    </script>
</body>
</html>
