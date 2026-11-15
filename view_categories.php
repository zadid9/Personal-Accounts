<?php
require_once 'auth.php';
require_once 'config.php';

$user_id = $_SESSION['id'];
$categories = [];

// Fetch all categories for the user
$sql = "SELECT id, name, type, parent_id FROM categories WHERE user_id = ? ORDER BY type, name";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    mysqli_stmt_close($stmt);
}
mysqli_close($link);

// Function to build hierarchical category list
function buildCategoryTree($categories, $parentId = null, $level = 0) {
    $branch = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $category['level'] = $level;
            $branch[] = $category;
            $children = buildCategoryTree($categories, $category['id'], $level + 1);
            foreach ($children as $child) {
                $branch[] = $child;
            }
        }
    }
    return $branch;
}

$hierarchical_categories = buildCategoryTree($categories);

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>আপনার ক্যাটাগরি</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>আপনার ক্যাটাগরি</h2>
        <p><a href="dashboard.php">ড্যাশবোর্ডে ফিরে যান</a> | <a href="add_category.php">নতুন ক্যাটাগরি যোগ করুন</a></p>

        <?php if (empty($categories)): ?>
            <p>আপনার কোনো ক্যাটাগরি সেট করা হয়নি।</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ক্যাটাগরির নাম</th>
                        <th>প্রকার</th>
                        <th>অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hierarchical_categories as $category): ?>
                    <tr class="category-level-<?php echo $category['level']; ?>">
                        <td>
                            <?php 
                                echo str_repeat('&nbsp;&nbsp;&nbsp;', $category['level']); // Indent based on level
                                echo htmlspecialchars($category['name']); 
                            ?>
                        </td>
                        <td><?php echo ucfirst(htmlspecialchars($category['type'])); ?></td>
                        <td class="action-links">
                            <a href="edit_category.php?id=<?php echo $category['id']; ?>">সম্পাদনা করুন</a>
                            <form action="delete_category.php" method="post" class="inline-form" onsubmit="return confirm('আপনি কি নিশ্চিত যে এই ক্যাটাগরি মুছে ফেলতে চান? আগের লেনদেনগুলোতে ক্যাটাগরির নাম থেকে যাবে।');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">
                                <button type="submit" class="link-button delete">মুছে ফেলুন</button>
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
