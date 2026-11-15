<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$id = $name = $type = '';
$parent_id = null; // Initialize parent_id as null
$name_err = $type_err = '';

if ((int)($_GET['id'] ?? 0) > 0) {
    $id = (int)$_GET['id'];

    // Fetch category details
    $sql = "SELECT name, type, parent_id FROM categories WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $name, $type, $parent_id);
        if (!mysqli_stmt_fetch($stmt)) {
            echo "ক্যাটাগরি পাওয়া যায়নি।";
            exit();
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "কিছু সমস্যা হয়েছে। অনুগ্রহ করে পরে আবার চেষ্টা করুন।";
        exit();
    }
} else {
    header("location: view_categories.php");
    exit();
}

// Fetch existing categories to be used as parent categories (excluding the current category and its children)
$parent_categories = [];
$sql_parent_categories = "SELECT id, name, type FROM categories WHERE user_id = ? AND id != ? ORDER BY type, name";
if ($stmt_parent_cat = mysqli_prepare($link, $sql_parent_categories)) {
    mysqli_stmt_bind_param($stmt_parent_cat, "ii", $user_id, $id);
    mysqli_stmt_execute($stmt_parent_cat);
    $result_parent_cat = mysqli_stmt_get_result($stmt_parent_cat);
    while ($row = mysqli_fetch_assoc($result_parent_cat)) {
        // Basic check to prevent a category from being its own child (direct circular reference)
        // More robust check for deep circular references would require a recursive function.
        if ($row['id'] != $id) {
            $parent_categories[] = $row;
        }
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

    // Get parent_id, convert empty string to null
    $parent_id = (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') ? (int)trim($_POST['parent_id']) : null;

    if (!in_array($type, ['income', 'expense'], true)) {
        $type_err = 'অনুগ্রহ করে ক্যাটাগরির প্রকার নির্বাচন করুন।';
    }
    if ($parent_id === $id) {
        $name_err = 'একটি ক্যাটাগরি তার নিজের প্যারেন্ট হতে পারে না।';
    } elseif ($parent_id !== null && !in_array($parent_id, array_map('intval', array_column($parent_categories, 'id')), true)) {
        $name_err = 'অবৈধ প্যারেন্ট ক্যাটাগরি।';
    }

    if (empty($name_err) && empty($type_err)) {
        try {
            $stmt = mysqli_prepare($link, "UPDATE categories SET name = ?, type = ?, parent_id = ? WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ssiii", $name, $type, $parent_id, $id, $user_id);
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
    <title>ক্যাটাগরি সম্পাদনা করুন</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>ক্যাটাগরি সম্পাদনা করুন</h2>
        <form action="edit_category.php?id=<?php echo $id; ?>" method="post">
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
                <input type="submit" value="আপডেট করুন">
            </div>
            <p><a href="view_categories.php">ক্যাটাগরি তালিকায় ফিরে যান</a></p>
        </form>
    </div>
</body>
</html>
