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
$loans = fetch_user_rows($link, "SELECT id, party_name, amount, type, paid_amount FROM loans WHERE user_id = ? AND status != 'fully_paid' ORDER BY party_name", $user_id);
$all_categories = fetch_user_rows($link, "SELECT id, name, type, parent_id FROM categories WHERE user_id = ? ORDER BY type, name", $user_id);

function category_options(array $categories, $parent_id = null, string $prefix = ''): array
{
    $options = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parent_id) {
            $options[] = ['id' => (int)$cat['id'], 'label' => $prefix . $cat['name'], 'name' => $cat['name']];
            $options = array_merge($options, category_options($categories, $cat['id'], $prefix . '— '));
        }
    }
    return $options;
}

$income_cats = category_options(array_values(array_filter($all_categories, fn($c) => $c['type'] === 'income')));
$expense_cats = category_options(array_values(array_filter($all_categories, fn($c) => $c['type'] !== 'income')));
// Category selects per transaction type; reimbursements use expense categories.
$category_selects = [
    'income' => ['field' => 'income_category', 'placeholder' => 'আয় ক্যাটাগরি নির্বাচন করুন', 'options' => $income_cats],
    'expense' => ['field' => 'expense_category', 'placeholder' => 'ব্যয় ক্যাটাগরি নির্বাচন করুন', 'options' => $expense_cats],
    'reimbursement' => ['field' => 'reimbursement_category', 'placeholder' => 'ফেরতযোগ্য ক্যাটাগরি নির্বাচন করুন', 'options' => $expense_cats],
];

$d = ['type' => '', 'title' => '', 'amount' => '', 'transaction_date' => date('Y-m-d'), 'description' => '',
      'from_account_id' => 0, 'to_account_id' => 0, 'loan_id' => 0];
$selected_category_id = 0;
$errors = [];
$form_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    $input['category'] = '';
    $type = trim($_POST['type'] ?? '');
    if (isset($category_selects[$type])) {
        $selected_category_id = (int)($_POST[$category_selects[$type]['field']] ?? 0);
        foreach ($category_selects[$type]['options'] as $opt) {
            if ($opt['id'] === $selected_category_id) {
                $input['category'] = $opt['name'];
            }
        }
    }

    [$d, $errors] = ledger_validate($input, $accounts, $loans);

    if (!$errors) {
        try {
            ledger_create($link, $user_id, $d);
            header('location: view_transactions.php?month_filter=' . urlencode(substr($d['transaction_date'], 0, 7)));
            exit;
        } catch (Throwable $e) {
            error_log('add_transaction: ' . $e->getMessage());
            $form_err = 'লেনদেন যোগ করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
        }
    } else {
        $form_err = 'লেনদেন সেভ হয়নি। নিচের ত্রুটিগুলো ঠিক করুন।';
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
    <title>লেনদেন যোগ করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>নতুন লেনদেন যোগ করুন</h2>

        <?php if ($form_err): ?>
            <div style="color:#c0392b; background:#fdecea; padding:10px; border-radius:5px; margin-bottom:15px;"><?php echo e($form_err); ?></div>
        <?php endif; ?>

        <form action="add_transaction.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label>প্রকার:</label>
                <select id="transaction_type" name="type">
                    <option value="">প্রকার নির্বাচন করুন</option>
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

            <div>
                <label>পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo e($d['amount']); ?>">
                <?php echo err($errors, 'amount'); ?>
            </div>
            <div>
                <label>তারিখ:</label>
                <input type="date" name="transaction_date" value="<?php echo e($d['transaction_date']); ?>">
                <?php echo err($errors, 'transaction_date'); ?>
            </div>

            <div id="category_field" class="hidden-field">
                <label>ক্যাটাগরি:</label>
                <?php foreach ($category_selects as $for_type => $sel): ?>
                    <select name="<?php echo $sel['field']; ?>" data-for-type="<?php echo $for_type; ?>" class="category-select" style="display:none;">
                        <option value=""><?php echo $sel['placeholder']; ?></option>
                        <?php foreach ($sel['options'] as $opt): ?>
                            <option value="<?php echo $opt['id']; ?>" <?php echo ($d['type'] === $for_type && $opt['id'] === $selected_category_id) ? 'selected' : ''; ?>><?php echo e($opt['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endforeach; ?>
                <?php echo err($errors, 'category'); ?>
                <?php if (!$all_categories): ?>
                    <small>আপনার কোনো ক্যাটাগরি নেই। আগে <a href="add_category.php">ক্যাটাগরি যোগ করুন</a>।</small>
                <?php endif; ?>
            </div>

            <div id="from_account_field" class="hidden-field">
                <label>থেকে অ্যাকাউন্ট:</label>
                <select name="from_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php account_options($accounts, (int)$d['from_account_id']); ?>
                </select>
                <?php echo err($errors, 'from_account_id'); ?>
            </div>

            <div id="to_account_field" class="hidden-field">
                <label>তে অ্যাকাউন্ট:</label>
                <select name="to_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php account_options($accounts, (int)$d['to_account_id']); ?>
                </select>
                <?php echo err($errors, 'to_account_id'); ?>
            </div>

            <div id="loan_field" class="hidden-field">
                <label>ধার/ঋণ নির্বাচন করুন:</label>
                <select id="loan_id" name="loan_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($loans as $loan): ?>
                        <?php $remaining = $loan['amount'] - $loan['paid_amount']; ?>
                        <option value="<?php echo (int)$loan['id']; ?>" data-type="<?php echo e($loan['type']); ?>" <?php echo (int)$loan['id'] === (int)$d['loan_id'] ? 'selected' : ''; ?>>
                            <?php echo e($loan['party_name'] . ' (' . ucfirst($loan['type']) . ') - BDT ' . number_format((float)$loan['amount'], 2) . ' (বাকি: BDT ' . number_format($remaining, 2) . ')'); ?>
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
                <input type="submit" value="লেনদেন যোগ করুন">
            </div>
            <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a></p>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const FROM_TYPES = <?php echo json_encode(FROM_TYPES); ?>;
            const TO_TYPES = <?php echo json_encode(TO_TYPES); ?>;
            const LOAN_TYPES = <?php echo json_encode(LOAN_TYPES); ?>;
            const LOAN_KIND = <?php echo json_encode(LOAN_KIND_FOR_TYPE); ?>;

            const typeSelect = document.getElementById('transaction_type');
            const categoryField = document.getElementById('category_field');
            const fromField = document.getElementById('from_account_field');
            const toField = document.getElementById('to_account_field');
            const loanField = document.getElementById('loan_field');
            const loanSelect = document.getElementById('loan_id');
            const categorySelects = document.querySelectorAll('.category-select');

            function toggleFields(resetValues) {
                const t = typeSelect.value;
                let hasCategory = false;
                categorySelects.forEach(sel => {
                    const active = sel.dataset.forType === t;
                    sel.style.display = active ? 'block' : 'none';
                    sel.disabled = !active;
                    hasCategory = hasCategory || active;
                    if (resetValues) sel.value = '';
                });
                categoryField.classList.toggle('hidden-field', !hasCategory);
                fromField.classList.toggle('hidden-field', !FROM_TYPES.includes(t));
                toField.classList.toggle('hidden-field', !TO_TYPES.includes(t));
                loanField.classList.toggle('hidden-field', !LOAN_TYPES.includes(t));

                if (LOAN_TYPES.includes(t)) {
                    Array.from(loanSelect.options).forEach(o => {
                        o.hidden = o.value !== '' && o.dataset.type !== LOAN_KIND[t];
                    });
                }
                if (resetValues) {
                    fromField.querySelector('select').value = '';
                    toField.querySelector('select').value = '';
                    loanSelect.value = '';
                }
            }

            typeSelect.addEventListener('change', () => toggleFields(true));
            toggleFields(false);
        });
    </script>
</body>
</html>
