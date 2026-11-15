<?php
require_once 'auth.php';
require_once 'ledger.php';

$user_id = $_SESSION['id'];

function fetch_user_rows(mysqli $link, string $sql, int $user_id): array
{
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

$accounts = fetch_user_rows($link, "SELECT id, name, type, current_balance FROM accounts WHERE user_id = ? ORDER BY name", $user_id);
// All loans, including fully paid ones, since an old transaction may point at one.
$loans = fetch_user_rows($link, "SELECT id, party_name, amount, paid_amount, type FROM loans WHERE user_id = ? ORDER BY party_name", $user_id);
$categories = array_column(fetch_user_rows($link, "SELECT DISTINCT name FROM categories WHERE user_id = ? ORDER BY name", $user_id), 'name');

// The id arrives via GET when the page is opened and via the hidden field when the form is posted.
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$old = $id > 0 ? ledger_find($link, $user_id, $id) : null;
if (!$old) {
    header("location: view_transactions.php");
    exit;
}

$d = $old;
$d['category'] = $old['category'] ?? '';
$d['description'] = $old['description'] ?? '';
$errors = [];
$form_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $errors] = ledger_validate($_POST, $accounts, $loans);

    if ($errors) {
        $form_err = 'লেনদেন সেভ হয়নি। নিচের ত্রুটিগুলো ঠিক করুন।';
    } else {
        try {
            ledger_update($link, $user_id, $id, $d);
            header("location: view_transactions.php?month_filter=" . urlencode(substr($d['transaction_date'], 0, 7)));
            exit;
        } catch (Throwable $e) {
            error_log('edit_transaction: ' . $e->getMessage());
            $form_err = 'লেনদেন আপডেট করার সময় ত্রুটি হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
        }
    }
}

function err(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span>' . e($errors[$key]) . '</span>' : '';
}

function account_options(array $accounts, int $selected): void
{
    foreach ($accounts as $account) {
        $label = $account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format((float)$account['current_balance'], 2);
        echo '<option value="' . (int)$account['id'] . '"' . ((int)$account['id'] === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
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
            <div style="color:#c0392b; background:#fdecea; padding:10px; border-radius:5px; margin-bottom:15px;"><?php echo e($form_err); ?></div>
        <?php endif; ?>

        <form action="edit_transaction.php?id=<?php echo $id; ?>" method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php echo csrf_field(); ?>

            <div>
                <label>লেনদেনের প্রকার:</label>
                <select id="transaction_type" name="type" required>
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach (TRANSACTION_TYPES as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $d['type'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php echo err($errors, 'type'); ?>
            </div>

            <div>
                <label>লেনদেনের নাম (টাইটেল):</label>
                <input type="text" name="title" maxlength="255" value="<?php echo e($d['title']); ?>" placeholder="উদা: বাজার খরচ, বেতন, ইত্যাদি">
            </div>

            <div id="category_field">
                <label>ক্যাটাগরি:</label>
                <input type="text" name="category" list="existingCategories" maxlength="100" value="<?php echo e($d['category']); ?>" placeholder="যেমন: খাবার, বেতন, ইউটিলিটি">
                <datalist id="existingCategories">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo e($cat); ?>">
                    <?php endforeach; ?>
                </datalist>
                <?php echo err($errors, 'category'); ?>
            </div>

            <div>
                <label>পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo e($d['amount']); ?>" required>
                <?php echo err($errors, 'amount'); ?>
            </div>

            <div>
                <label>তারিখ:</label>
                <input type="date" name="transaction_date" value="<?php echo e($d['transaction_date']); ?>" required>
                <?php echo err($errors, 'transaction_date'); ?>
            </div>

            <div id="from_account_field">
                <label>থেকে অ্যাকাউন্ট (From Account):</label>
                <select name="from_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php account_options($accounts, (int)$d['from_account_id']); ?>
                </select>
                <?php echo err($errors, 'from_account_id'); ?>
            </div>

            <div id="to_account_field">
                <label>তে অ্যাকাউন্ট (To Account):</label>
                <select name="to_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php account_options($accounts, (int)$d['to_account_id']); ?>
                </select>
                <?php echo err($errors, 'to_account_id'); ?>
            </div>

            <div id="loan_field">
                <label>ধার/ঋণ নির্বাচন করুন:</label>
                <select id="loan_id_select" name="loan_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                            $remaining = $loan['amount'] - $loan['paid_amount'];
                            $status = $remaining <= 0 ? ' (পরিশোধিত)' : ' (বাকি: BDT ' . number_format($remaining, 2) . ')';
                        ?>
                        <option value="<?php echo (int)$loan['id']; ?>" data-type="<?php echo e($loan['type']); ?>" <?php echo (int)$loan['id'] === (int)$d['loan_id'] ? 'selected' : ''; ?>>
                            <?php echo e($loan['party_name'] . ' (' . ucfirst($loan['type']) . ') - BDT ' . number_format((float)$loan['amount'], 2) . $status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php echo err($errors, 'loan_id'); ?>
            </div>

            <div>
                <label>বর্ণনা (ঐচ্ছিক):</label>
                <textarea name="description"><?php echo e($d['description']); ?></textarea>
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
            const LOAN_KIND = <?php echo json_encode(LOAN_KIND_FOR_TYPE); ?>;

            const typeSelect = document.getElementById('transaction_type');
            const loanSelect = document.getElementById('loan_id_select');

            function toggleFields() {
                const t = typeSelect.value;
                document.getElementById('category_field').classList.toggle('hidden-field', !CATEGORY_TYPES.includes(t));
                document.getElementById('from_account_field').classList.toggle('hidden-field', !FROM_TYPES.includes(t));
                document.getElementById('to_account_field').classList.toggle('hidden-field', !TO_TYPES.includes(t));
                document.getElementById('loan_field').classList.toggle('hidden-field', !LOAN_TYPES.includes(t));

                if (LOAN_TYPES.includes(t)) {
                    Array.from(loanSelect.options).forEach(o => {
                        o.hidden = o.value !== '' && o.dataset.type !== LOAN_KIND[t];
                    });
                }
            }

            typeSelect.addEventListener('change', toggleFields);
            toggleFields();
        });
    </script>
</body>
</html>
