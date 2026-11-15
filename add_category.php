<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$name = $type = '';
$parent_id = null; // Initialize parent_id as null
$name_err = $type_err = '';

// Fetch existing categories to be used as parent categories
$parent_categories = [];
$sql_parent_categories = "SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type, name";
if ($stmt_parent_cat = mysqli_prepare($link, $sql_parent_categories)) {
    mysqli_stmt_bind_param($stmt_parent_cat, "i", $user_id);
    mysqli_stmt_execute($stmt_parent_cat);
    $result_parent_cat = mysqli_stmt_get_result($stmt_parent_cat);
    while ($row = mysqli_fetch_assoc($result_parent_cat)) {
        $parent_categories[] = $row;
    }
    mysqli_stmt_close($stmt_parent_cat);
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate name
    if (empty(trim($_POST['name']))) {
        $name_err = 'অনুগ্রহ করে ক্যাটাগরির নাম লিখুন।';
    } else {
        $name = trim($_POST['name']);
    }

    // Validate type
    if (empty(trim($_POST['type']))) {
        $type_err = 'অনুগ্রহ করে ক্যাটাগরির প্রকার নির্বাচন করুন।';
    } else {
        $type = trim($_POST['type']);
    }

    $parent_id = (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') ? (int)trim($_POST['parent_id']) : null;

    if (!in_array($type, ['income', 'expense'], true)) {
        $type_err = 'অনুগ্রহ করে ক্যাটাগরির প্রকার নির্বাচন করুন।';
    }
    if ($parent_id !== null && !in_array($parent_id, array_map('intval', array_column($parent_categories, 'id')), true)) {
        $name_err = 'অবৈধ প্যারেন্ট ক্যাটাগরি।';
    }

    if (empty($name_err) && empty($type_err)) {
        try {
            $stmt = mysqli_prepare($link, "INSERT INTO categories (user_id, name, type, parent_id) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issi", $user_id, $name, $type, $parent_id);
            mysqli_stmt_execute($stmt);
            header('location: view_categories.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1062) {
                throw $e;
            }
            $name_err = 'এই প্রকারের জন্য এই ক্যাটাগরির নাম ইতিমধ্যে বিদ্যমান।';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>ক্যাটাগরি যোগ করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>নতুন ক্যাটাগরি যোগ করুন</h2>
        <form action="add_category.php" method="post">
            <?php echo csrf_field(); ?>
            <div>
                <label>ক্যাটাগরির নাম:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <span><?php echo $name_err; ?></span>
            </div>
            <div>
                <label>প্রকার:</label>
                <select name="type">
                    <option value="">প্রকার নির্বাচন করুন</option>
                    <option value="income" <?php echo ($type == 'income') ? 'selected' : ''; ?>>আয়</option>
                    <option value="expense" <?php echo ($type == 'expense') ? 'selected' : ''; ?>>ব্যয়</option>
                </select>
                <span><?php echo $type_err; ?></span>
            </div>
            <div>
                <label>প্যারেন্ট ক্যাটাগরি (ঐচ্ছিক):</label>
                <select name="parent_id">
                    <option value="">কোনো প্যারেন্ট নেই</option>
                    <?php foreach ($parent_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($parent_id == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name'] . ' (' . ucfirst($cat['type']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <input type="submit" value="ক্যাটাগরি যোগ করুন">
            </div>
            <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a> | <a href="view_categories.php">সকল ক্যাটাগরি দেখুন</a></p>
        </form>
    </div>
</body>
</html>
