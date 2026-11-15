<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$party_name = $amount = $type = $due_date = $notes = '';
$party_name_err = $amount_err = $type_err = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate party name
    if (empty(trim($_POST['party_name']))) {
        $party_name_err = 'অনুগ্রহ করে পার্টির নাম লিখুন।';
    } else {
        $party_name = trim($_POST['party_name']);
    }

    // Validate amount
    if (empty(trim($_POST['amount'])) || !is_numeric(trim($_POST['amount'])) || trim($_POST['amount']) <= 0) {
        $amount_err = 'পরিমাণের জন্য একটি বৈধ ধনাত্মক সংখ্যা লিখুন।';
    } else {
        $amount = trim($_POST['amount']);
    }

    // Validate type
    if (empty(trim($_POST['type']))) {
        $type_err = 'অনুগ্রহ করে ধারের প্রকার নির্বাচন করুন।';
    } else {
        $type = trim($_POST['type']);
    }

    if (!in_array($type, ['borrowed', 'lent'], true)) {
        $type_err = 'অনুগ্রহ করে ধারের প্রকার নির্বাচন করুন।';
    }

    $due_date = trim($_POST['due_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $due_date_db = $due_date === '' ? null : $due_date;

    // If no errors, insert into database
    if (empty($party_name_err) && empty($amount_err) && empty($type_err)) {
        $sql = "INSERT INTO loans (user_id, party_name, amount, type, due_date, notes) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "isdsss", $user_id, $party_name, $amount, $type, $due_date_db, $notes);

            if (mysqli_stmt_execute($stmt)) {
                header('location: view_loans.php'); // Redirect to view loans page
            } else {
                echo 'কিছু সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।';
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>ধার/ঋণ যোগ করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>নতুন ধার/ঋণ যোগ করুন</h2>
        <form action="add_loan.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label>পার্টির নাম:</label>
                <input type="text" name="party_name" value="<?php echo htmlspecialchars($party_name); ?>">
                <span><?php echo $party_name_err; ?></span>
            </div>
            <div>
                <label>পরিমাণ (BDT):</label>
                <input type="number" name="amount" step="0.01" value="<?php echo htmlspecialchars($amount); ?>">
                <span><?php echo $amount_err; ?></span>
            </div>
            <div>
                <label>প্রকার:</label>
                <select name="type">
                    <option value="">প্রকার নির্বাচন করুন</option>
                    <option value="borrowed" <?php echo ($type == 'borrowed') ? 'selected' : ''; ?>>ধার নেওয়া (আপনি দেনা)</option>
                    <option value="lent" <?php echo ($type == 'lent') ? 'selected' : ''; ?>>ধার দেওয়া (অন্যরা দেনা)</option>
                </select>
                <span><?php echo $type_err; ?></span>
            </div>
            <div>
                <label>পরিশোধের শেষ তারিখ (ঐচ্ছিক):</label>
                <input type="date" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>">
            </div>
            <div>
                <label>নোটস (ঐচ্ছিক):</label>
                <textarea name="notes"><?php echo htmlspecialchars($notes); ?></textarea>
            </div>
            <div>
                <input type="submit" value="ধার/ঋণ যোগ করুন">
            </div>
            <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a></p>
        </form>
    </div>
</body>
</html>
