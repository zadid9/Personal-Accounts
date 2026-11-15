<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$category = $budgeted_amount = $start_date = $end_date = '';
$category_err = $budgeted_amount_err = $start_date_err = $end_date_err = '';

// Fetch existing categories for suggestion (optional, but good UX)
$existing_categories = [];
$sql_categories = "SELECT DISTINCT category FROM transactions WHERE user_id = ? AND category IS NOT NULL AND category != '' ORDER BY category";
if ($stmt_cat = mysqli_prepare($link, $sql_categories)) {
    mysqli_stmt_bind_param($stmt_cat, "i", $user_id);
    mysqli_stmt_execute($stmt_cat);
    $result_cat = mysqli_stmt_get_result($stmt_cat);
    while ($row = mysqli_fetch_assoc($result_cat)) {
        $existing_categories[] = htmlspecialchars($row['category']);
    }
    mysqli_stmt_close($stmt_cat);
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate category
    if (empty(trim($_POST['category']))) {
        $category_err = 'অনুগ্রহ করে একটি ক্যাটাগরি লিখুন।';
    } else {
        $category = trim($_POST['category']);
    }

    // Validate budgeted amount
    if (empty(trim($_POST['budgeted_amount'])) || !is_numeric(trim($_POST['budgeted_amount'])) || trim($_POST['budgeted_amount']) <= 0) {
        $budgeted_amount_err = 'বাজেটের পরিমাণের জন্য একটি বৈধ ধনাত্মক সংখ্যা লিখুন।';
    } else {
        $budgeted_amount = trim($_POST['budgeted_amount']);
    }

    // Validate start date
    if (empty(trim($_POST['start_date']))) {
        $start_date_err = 'অনুগ্রহ করে শুরুর তারিখ লিখুন।';
    } else {
        $start_date = trim($_POST['start_date']);
    }

    // Validate end date
    if (empty(trim($_POST['end_date']))) {
        $end_date_err = 'অনুগ্রহ করে শেষের তারিখ লিখুন।';
    } else {
        $end_date = trim($_POST['end_date']);
    }

    // Date comparison validation
    if (!empty($start_date) && !empty($end_date) && strtotime($start_date) > strtotime($end_date)) {
        $end_date_err = 'শেষের তারিখ শুরুর তারিখের পরে হতে হবে।';
    }

    // If no errors, insert into database
    if (empty($category_err) && empty($budgeted_amount_err) && empty($start_date_err) && empty($end_date_err)) {
        try {
            $stmt = mysqli_prepare($link, "INSERT INTO budgets (user_id, category, budgeted_amount, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isdss", $user_id, $category, $budgeted_amount, $start_date, $end_date);
            mysqli_stmt_execute($stmt);
            header('location: view_budgets.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1062) {
                throw $e;
            }
            $category_err = 'এই সময়ের মধ্যে এই ক্যাটাগরির জন্য একটি বাজেট ইতিমধ্যে বিদ্যমান।';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>বাজেট সেট করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>নতুন বাজেট সেট করুন</h2>
        <form action="add_budget.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label for="category">ক্যাটাগরি:</label>
                <input type="text" id="category" name="category" list="existingCategories" value="<?php echo htmlspecialchars($category); ?>" placeholder="যেমন: খাবার, পরিবহন">
                <datalist id="existingCategories">
                    <?php foreach ($existing_categories as $cat): ?>
                        <option value="<?php echo $cat; ?>">
                    <?php endforeach; ?>
                </datalist>
                <span><?php echo $category_err; ?></span>
            </div>
            <div>
                <label>বাজেটের পরিমাণ (BDT):</label>
                <input type="number" name="budgeted_amount" step="0.01" value="<?php echo htmlspecialchars($budgeted_amount); ?>">
                <span><?php echo $budgeted_amount_err; ?></span>
            </div>
            <div>
                <label>শুরুর তারিখ:</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                <span><?php echo $start_date_err; ?></span>
            </div>
            <div>
                <label>শেষের তারিখ:</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                <span><?php echo $end_date_err; ?></span>
            </div>
            <div>
                <input type="submit" value="বাজেট সেট করুন">
            </div>
            <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a></p>
        </form>
    </div>
</body>
</html>
