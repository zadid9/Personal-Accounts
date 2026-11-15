<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
// [পরিবর্তন ১] $title ভেরিয়েবল যোগ করা হয়েছে
$type = $title = $category = $amount = $transaction_date = $description = '';
$from_account_id = $to_account_id = '';
$loan_id = ''; 

$type_err = $category_err = $amount_err = $date_err = $from_account_err = $to_account_err = $loan_id_err = '';

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

$loans = [];
$sql_loans = "SELECT id, party_name, amount, type, paid_amount FROM loans WHERE user_id = ? AND status != 'fully_paid' ORDER BY party_name";
if ($stmt_loans = mysqli_prepare($link, $sql_loans)) {
    mysqli_stmt_bind_param($stmt_loans, "i", $user_id);
    mysqli_stmt_execute($stmt_loans);
    $result_loans = mysqli_stmt_get_result($stmt_loans);
    while ($row = mysqli_fetch_assoc($result_loans)) {
        $loans[] = $row;
    }
    mysqli_stmt_close($stmt_loans);
}

$all_categories = [];
$sql_categories = "SELECT id, name, type, parent_id FROM categories WHERE user_id = ? ORDER BY type, name";
if ($stmt_cat = mysqli_prepare($link, $sql_categories)) {
    mysqli_stmt_bind_param($stmt_cat, "i", $user_id);
    mysqli_stmt_execute($stmt_cat);
    $result_cat = mysqli_stmt_get_result($stmt_cat);
    while ($row = mysqli_fetch_assoc($result_cat)) {
        $all_categories[] = $row;
    }
    mysqli_stmt_close($stmt_cat);
}

function buildCategoryDropdownOptions($categories, $parentId = null, $prefix = '') {
    $options = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $options[] = ['id' => $category['id'], 'name' => $prefix . htmlspecialchars($category['name']), 'type' => $category['type']];
            $options = array_merge($options, buildCategoryDropdownOptions($categories, $category['id'], $prefix . '-- '));
        }
    }
    return $options;
}

$income_categories_flat = [];
$expense_categories_flat = [];
$reimbursement_categories_flat = [];

$temp_income_categories = [];
$temp_expense_categories = [];

foreach ($all_categories as $cat) {
    if ($cat['type'] == 'income') {
        $temp_income_categories[] = $cat;
    } else {
        $temp_expense_categories[] = $cat;
    }
}

$income_categories_flat = buildCategoryDropdownOptions($temp_income_categories);
$expense_categories_flat = buildCategoryDropdownOptions($temp_expense_categories);
$reimbursement_categories_flat = buildCategoryDropdownOptions($temp_expense_categories);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = trim($_POST['type'] ?? '');
    // [পরিবর্তন ২] ফর্ম থেকে টাইটেল নেওয়া হচ্ছে
    $title = trim($_POST['title'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    $category = null;
    if (isset($_POST['income_category_active']) && !empty($_POST['income_category_active'])) {
        foreach ($all_categories as $cat) {
            if ($cat['id'] == $_POST['income_category_active']) {
                $category = $cat['name'];
                break;
            }
        }
    } elseif (isset($_POST['expense_category_active']) && !empty($_POST['expense_category_active'])) {
        foreach ($all_categories as $cat) {
            if ($cat['id'] == $_POST['expense_category_active']) {
                $category = $cat['name'];
                break;
            }
        }
    } elseif (isset($_POST['reimbursement_category_active']) && !empty($_POST['reimbursement_category_active'])) {
        foreach ($all_categories as $cat) {
            if ($cat['id'] == $_POST['reimbursement_category_active']) {
                $category = $cat['name'];
                break;
            }
        }
    }

    $from_account_id = (isset($_POST['from_account_id']) && $_POST['from_account_id'] !== '') ? (int)trim($_POST['from_account_id']) : null;
    $to_account_id = (isset($_POST['to_account_id']) && $_POST['to_account_id'] !== '') ? (int)trim($_POST['to_account_id']) : null;
    $loan_id = (isset($_POST['loan_id']) && $_POST['loan_id'] !== '') ? (int)trim($_POST['loan_id']) : null;

    $owned_account_ids = array_map('intval', array_column($accounts, 'id'));
    $owned_loan_ids = array_map('intval', array_column($loans, 'id'));
    if ($from_account_id !== null && !in_array($from_account_id, $owned_account_ids, true)) {
        $from_account_err = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
    }
    if ($to_account_id !== null && !in_array($to_account_id, $owned_account_ids, true)) {
        $to_account_err = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
    }
    if ($loan_id !== null && !in_array($loan_id, $owned_loan_ids, true)) {
        $loan_id_err = 'অবৈধ ধার/ঋণ নির্বাচন করা হয়েছে।';
    }

    $valid_types = ['income', 'expense', 'transfer', 'reimbursement', 'loan_received', 'loan_given', 'loan_repayment_made', 'loan_repayment_received'];
    if (!in_array($type, $valid_types, true)) { $type_err = 'অনুগ্রহ করে প্রকার নির্বাচন করুন।'; }
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) { $amount_err = 'পরিমাণের জন্য একটি বৈধ ধনাত্মক সংখ্যা লিখুন।'; }
    if (empty($transaction_date)) { $date_err = 'অনুগ্রহ করে একটি তারিখ লিখুন।'; }

    if ($type == 'income') {
        if (empty($category)) { $category_err = 'অনুগ্রহ করে একটি ক্যাটাগরি নির্বাচন করুন।'; }
        if (empty($to_account_id)) { $to_account_err = 'অনুগ্রহ করে কোন অ্যাকাউন্টে টাকা এসেছে তা নির্বাচন করুন।'; }
    } elseif ($type == 'expense') {
        if (empty($category)) { $category_err = 'অনুগ্রহ করে একটি ক্যাটাগরি নির্বাচন করুন।'; }
        if (empty($from_account_id)) { $from_account_err = 'অনুগ্রহ করে কোন অ্যাকাউন্ট থেকে টাকা গেছে তা নির্বাচন করুন।'; }
    } elseif ($type == 'transfer' || $type == 'reimbursement') {
        if (empty($from_account_id)) { $from_account_err = 'অনুগ্রহ করে উৎস অ্যাকাউন্ট নির্বাচন করুন।'; }
        if (empty($to_account_id)) { $to_account_err = 'অনুগ্রহ করে গন্তব্য অ্যাকাউন্ট নির্বাচন করুন।'; }
        if ($from_account_id == $to_account_id && !empty($from_account_id)) {
            $from_account_err = 'উৎস এবং গন্তব্য অ্যাকাউন্ট একই হতে পারে না।';
            $to_account_err = 'উৎস এবং গন্তব্য অ্যাকাউন্ট একই হতে পারে না।';
        }
        if ($type == 'reimbursement' && empty($category)) {
            $category_err = 'অনুগ্রহ করে একটি ক্যাটাগরি নির্বাচন করুন।';
        }
    } elseif (strpos($type, 'loan_') !== false) {
        if (empty($loan_id)) { $loan_id_err = 'অনুগ্রহ করে একটি ধার/ঋণ নির্বাচন করুন।'; }
        if ($type == 'loan_received' || $type == 'loan_repayment_received') {
            if (empty($to_account_id)) { $to_account_err = 'অনুগ্রহ করে কোন অ্যাকাউন্টে টাকা এসেছে তা নির্বাচন করুন।'; }
        } elseif ($type == 'loan_given' || $type == 'loan_repayment_made') {
            if (empty($from_account_id)) { $from_account_err = 'অনুগ্রহ করে কোন অ্যাকাউন্ট থেকে টাকা গেছে তা নির্বাচন করুন।'; }
        }
    }

    if (empty($type_err) && empty($category_err) && empty($amount_err) && empty($date_err) &&
        empty($from_account_err) && empty($to_account_err) && empty($loan_id_err)) {

        mysqli_begin_transaction($link);
        $success = true;

        try {
            // [পরিবর্তন ৩] SQL কুয়েরিতে title যোগ করা হয়েছে
            $sql_insert_transaction = "INSERT INTO transactions (user_id, title, transaction_date, amount, description, type, category, from_account_id, to_account_id, loan_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_insert = mysqli_prepare($link, $sql_insert_transaction)) {
                // প্যারামিটার বাইন্ডিং এ টাইটেল যোগ করা হয়েছে (issdsssiii)
                mysqli_stmt_bind_param($stmt_insert, "issdsssiii", $user_id, $title, $transaction_date, $amount, $description, $type, $category, $from_account_id, $to_account_id, $loan_id);
                if (!mysqli_stmt_execute($stmt_insert)) {
                    $success = false;
                }
                mysqli_stmt_close($stmt_insert);
            } else {
                $success = false;
            }

            if ($success) {
                if ($type == 'income' || $type == 'loan_received' || $type == 'loan_repayment_received') {
                    if ($to_account_id) {
                        $sql_update_account = "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?";
                        if ($stmt_update = mysqli_prepare($link, $sql_update_account)) {
                            mysqli_stmt_bind_param($stmt_update, "dii", $amount, $to_account_id, $user_id);
                            if (!mysqli_stmt_execute($stmt_update)) { $success = false; }
                            mysqli_stmt_close($stmt_update);
                        } else { $success = false; }
                    }
                } elseif ($type == 'expense' || $type == 'loan_given' || $type == 'loan_repayment_made') {
                    if ($from_account_id) {
                        $sql_update_account = "UPDATE accounts SET current_balance = current_balance - ? WHERE id = ? AND user_id = ?";
                        if ($stmt_update = mysqli_prepare($link, $sql_update_account)) {
                            mysqli_stmt_bind_param($stmt_update, "dii", $amount, $from_account_id, $user_id);
                            if (!mysqli_stmt_execute($stmt_update)) { $success = false; }
                            mysqli_stmt_close($stmt_update);
                        } else { $success = false; }
                    }
                } elseif ($type == 'transfer' || $type == 'reimbursement') {
                    if ($from_account_id) {
                        $sql_update_from = "UPDATE accounts SET current_balance = current_balance - ? WHERE id = ? AND user_id = ?";
                        if ($stmt_from = mysqli_prepare($link, $sql_update_from)) {
                            mysqli_stmt_bind_param($stmt_from, "dii", $amount, $from_account_id, $user_id);
                            if (!mysqli_stmt_execute($stmt_from)) { $success = false; }
                            mysqli_stmt_close($stmt_from);
                        } else { $success = false; }
                    }
                    if ($success && $to_account_id) {
                        $sql_update_to = "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?";
                        if ($stmt_to = mysqli_prepare($link, $sql_update_to)) {
                            mysqli_stmt_bind_param($stmt_to, "dii", $amount, $to_account_id, $user_id);
                            if (!mysqli_stmt_execute($stmt_to)) { $success = false; }
                            mysqli_stmt_close($stmt_to);
                        } else { $success = false; }
                    }
                }

                if ($success && $loan_id && ($type == 'loan_repayment_made' || $type == 'loan_repayment_received')) {
                    $sql_update_loan = "UPDATE loans SET paid_amount = paid_amount + ? WHERE id = ? AND user_id = ?";
                    if ($stmt_loan_update = mysqli_prepare($link, $sql_update_loan)) {
                        mysqli_stmt_bind_param($stmt_loan_update, "dii", $amount, $loan_id, $user_id);
                        if (!mysqli_stmt_execute($stmt_loan_update)) { $success = false; }
                        mysqli_stmt_close($stmt_loan_update);
                    } else { $success = false; }

                    if ($success) {
                        $sql_check_loan = "SELECT amount, paid_amount FROM loans WHERE id = ? AND user_id = ?";
                        if ($stmt_check_loan = mysqli_prepare($link, $sql_check_loan)) {
                            mysqli_stmt_bind_param($stmt_check_loan, "ii", $loan_id, $user_id);
                            mysqli_stmt_execute($stmt_check_loan);
                            mysqli_stmt_bind_result($stmt_check_loan, $loan_total_amount, $loan_paid_amount);
                            mysqli_stmt_fetch($stmt_check_loan);
                            mysqli_stmt_close($stmt_check_loan);

                            if ($loan_paid_amount >= $loan_total_amount) {
                                $sql_set_fully_paid = "UPDATE loans SET status = 'fully_paid' WHERE id = ? AND user_id = ?";
                                if ($stmt_set_paid = mysqli_prepare($link, $sql_set_fully_paid)) {
                                    mysqli_stmt_bind_param($stmt_set_paid, "ii", $loan_id, $user_id);
                                    if (!mysqli_stmt_execute($stmt_set_paid)) { $success = false; }
                                    mysqli_stmt_close($stmt_set_paid);
                                } else { $success = false; }
                            } else {
                                $sql_set_partially_paid = "UPDATE loans SET status = 'partially_paid' WHERE id = ? AND user_id = ?";
                                if ($stmt_set_partial = mysqli_prepare($link, $sql_set_partially_paid)) {
                                    mysqli_stmt_bind_param($stmt_set_partial, "ii", $loan_id, $user_id);
                                    if (!mysqli_stmt_execute($stmt_set_partial)) { $success = false; }
                                    mysqli_stmt_close($stmt_set_partial);
                                } else { $success = false; }
                            }
                        } else { $success = false; }
                    }
                }
            }

            if ($success) {
                mysqli_commit($link);
                header('location: view_transactions.php');
            } else {
                mysqli_rollback($link);
                echo 'লেনদেন যোগ করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।';
            }

        } catch (Exception $e) {
            mysqli_rollback($link);
            echo 'লেনদেন যোগ করতে একটি অপ্রত্যাশিত ত্রুটি হয়েছে: ' . $e->getMessage();
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
    <title>লেনদেন যোগ করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>নতুন লেনদেন যোগ করুন</h2>
        <form action="add_transaction.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label>প্রকার:</label>
                <select id="transaction_type" name="type">
                    <option value="">প্রকার নির্বাচন করুন</option>
                    <option value="income" <?php echo ($type == 'income') ? 'selected' : ''; ?>>আয়</option>
                    <option value="expense" <?php echo ($type == 'expense') ? 'selected' : ''; ?>>ব্যয়</option>
                    <option value="transfer" <?php echo ($type == 'transfer') ? 'selected' : ''; ?>>অ্যাকাউন্ট ট্রান্সফার</option>
                    <option value="reimbursement" <?php echo ($type == 'reimbursement') ? 'selected' : ''; ?>>ফেরতযোগ্য লেনদেন</option>
                    <option value="loan_received" <?php echo ($type == 'loan_received') ? 'selected' : ''; ?>>ধার গ্রহণ</option>
                    <option value="loan_given" <?php echo ($type == 'loan_given') ? 'selected' : ''; ?>>ধার প্রদান</option>
                    <option value="loan_repayment_made" <?php echo ($type == 'loan_repayment_made') ? 'selected' : ''; ?>>ধার পরিশোধ (আপনি করেছেন)</option>
                    <option value="loan_repayment_received" <?php echo ($type == 'loan_repayment_received') ? 'selected' : ''; ?>>ধার পরিশোধ (আপনি পেয়েছেন)</option>
                </select>
                <span><?php echo $type_err; ?></span>
            </div>

            <!-- [পরিবর্তন ৪] টাইটেল ইনপুট ফিল্ড যোগ করা হয়েছে -->
            <div>
                <label>লেনদেনের নাম (টাইটেল):</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" placeholder="উদা: বাজার খরচ, বেতন, ইত্যাদি">
            </div>

            <div>
                <label>পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" value="<?php echo htmlspecialchars($amount); ?>">
                <span><?php echo $amount_err; ?></span>
            </div>
            <div>
                <label>তারিখ:</label>
                <input type="date" name="transaction_date" value="<?php echo htmlspecialchars($transaction_date); ?>">
                <span><?php echo $date_err; ?></span>
            </div>

            <div id="category_field" class="hidden-field">
                <label>ক্যাটাগরি:</label>
                <select id="income_category" name="" style="display:none;">
                    <option value="">আয় ক্যাটাগরি নির্বাচন করুন</option>
                    <?php foreach ($income_categories_flat as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['name']) ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="expense_category" name="" style="display:none;">
                    <option value="">ব্যয় ক্যাটাগরি নির্বাচন করুন</option>
                    <?php foreach ($expense_categories_flat as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['name']) ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                 <select id="reimbursement_category" name="" style="display:none;">
                    <option value="">ফেরতযোগ্য ক্যাটাগরি নির্বাচন করুন</option>
                    <?php foreach ($reimbursement_categories_flat as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['name']) ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $category_err; ?></span>
            </div>

            <div id="from_account_field" class="hidden-field">
                <label>থেকে অ্যাকাউন্ট:</label>
                <select id="from_account_id" name="from_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>" <?php echo ($from_account_id == $account['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format($account['current_balance'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $from_account_err; ?></span>
            </div>

            <div id="to_account_field" class="hidden-field">
                <label>তে অ্যাকাউন্ট:</label>
                <select id="to_account_id" name="to_account_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>" <?php echo ($to_account_id == $account['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($account['name'] . ' (' . ucfirst($account['type']) . ') - BDT ' . number_format($account['current_balance'], 2)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $to_account_err; ?></span>
            </div>

            <div id="loan_field" class="hidden-field">
                <label>ধার/ঋণ নির্বাচন করুন:</label>
                <select id="loan_id" name="loan_id">
                    <option value="">নির্বাচন করুন</option>
                    <?php foreach ($loans as $loan): ?>
                        <option value="<?php echo $loan['id']; ?>" data-type="<?php echo htmlspecialchars($loan['type']); ?>" <?php echo ($loan_id == $loan['id']) ? 'selected' : ''; ?>>
                            <?php
                                $loan_status = ($loan['amount'] - $loan['paid_amount'] <= 0) ? ' (পরিশোধিত)' : ' (বাকি: BDT ' . number_format($loan['amount'] - $loan['paid_amount'], 2) . ')';
                                echo htmlspecialchars($loan['party_name'] . ' (' . ucfirst($loan['type']) . ') - BDT ' . number_format($loan['amount'], 2) . $loan_status);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span><?php echo $loan_id_err; ?></span>
            </div>

            <div>
                <label>বর্ণনা (ঐচ্ছিক):</label>
                <textarea name="description"><?php echo htmlspecialchars($description); ?></textarea>
            </div>

            <div>
                <input type="submit" value="লেনদেন যোগ করুন">
            </div>
            <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a></p>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const transactionType = document.getElementById('transaction_type');
            const categoryField = document.getElementById('category_field');
            const fromAccountField = document.getElementById('from_account_field');
            const toAccountField = document.getElementById('to_account_field');
            const loanField = document.getElementById('loan_field');
            const loanTypeSelect = document.getElementById('loan_id');
            const incomeCategorySelect = document.getElementById('income_category');
            const expenseCategorySelect = document.getElementById('expense_category');
            const reimbursementCategorySelect = document.getElementById('reimbursement_category');

            function toggleFields() {
                const selectedType = transactionType.value;

                // Hide all optional fields initially
                categoryField.classList.add('hidden-field');
                fromAccountField.classList.add('hidden-field');
                toAccountField.classList.add('hidden-field');
                loanField.classList.add('hidden-field');

                // Reset selected values
                incomeCategorySelect.value = '';
                expenseCategorySelect.value = '';
                reimbursementCategorySelect.value = '';
                document.getElementById('from_account_id').value = '';
                document.getElementById('to_account_id').value = '';
                document.getElementById('loan_id').value = '';

                // Disable all category selects by default
                incomeCategorySelect.name = '';
                expenseCategorySelect.name = '';
                reimbursementCategorySelect.name = '';
                incomeCategorySelect.style.display = 'none';
                expenseCategorySelect.style.display = 'none';
                reimbursementCategorySelect.style.display = 'none';


                if (selectedType === 'income') {
                    categoryField.classList.remove('hidden-field');
                    incomeCategorySelect.style.display = 'block';
                    incomeCategorySelect.name = 'income_category_active'; // Set unique name for active select
                    toAccountField.classList.remove('hidden-field');
                } else if (selectedType === 'expense') {
                    categoryField.classList.remove('hidden-field');
                    expenseCategorySelect.style.display = 'block';
                    expenseCategorySelect.name = 'expense_category_active'; // Set unique name for active select
                    fromAccountField.classList.remove('hidden-field');
                } else if (selectedType === 'transfer') {
                    fromAccountField.classList.remove('hidden-field');
                    toAccountField.classList.remove('hidden-field');
                } else if (selectedType === 'reimbursement') { // New reimbursement type
                    categoryField.classList.remove('hidden-field');
                    reimbursementCategorySelect.style.display = 'block';
                    reimbursementCategorySelect.name = 'reimbursement_category_active'; // Set unique name for active select
                    fromAccountField.classList.remove('hidden-field');
                    toAccountField.classList.remove('hidden-field');
                }
                else if (selectedType === 'loan_received') {
                    loanField.classList.remove('hidden-field');
                    toAccountField.classList.remove('hidden-field');
                    // Filter loan_id to show only 'borrowed' loans
                    Array.from(loanTypeSelect.options).forEach(option => {
                        if (option.dataset.type === 'borrowed' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                } else if (selectedType === 'loan_given') {
                    loanField.classList.remove('hidden-field');
                    fromAccountField.classList.remove('hidden-field');
                     // Filter loan_id to show only 'lent' loans
                    Array.from(loanTypeSelect.options).forEach(option => {
                        if (option.dataset.type === 'lent' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                } else if (selectedType === 'loan_repayment_made') {
                    loanField.classList.remove('hidden-field');
                    fromAccountField.classList.remove('hidden-field');
                    // Filter loan_id to show only 'borrowed' loans
                    Array.from(loanTypeSelect.options).forEach(option => {
                        if (option.dataset.type === 'borrowed' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                } else if (selectedType === 'loan_repayment_received') {
                    loanField.classList.remove('hidden-field');
                    toAccountField.classList.remove('hidden-field');
                    // Filter loan_id to show only 'lent' loans
                    Array.from(loanTypeSelect.options).forEach(option => {
                        if (option.dataset.type === 'lent' || option.value === '') {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                }
            }

            transactionType.addEventListener('change', toggleFields);
            toggleFields(); // Call on page load to set initial state
        });
    </script>
</body>
</html>