<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location: view_accounts.php');
    exit;
}

$user_id = $_SESSION['id'];
$id = (int)($_POST['id'] ?? 0);

$stmt = mysqli_prepare($link, "SELECT COUNT(*) FROM transactions WHERE user_id = ? AND (from_account_id = ? OR to_account_id = ?)");
mysqli_stmt_bind_param($stmt, "iii", $user_id, $id, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $tx_count);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($tx_count > 0) {
    header('location: view_accounts.php?error=has_transactions');
    exit;
}

$stmt = mysqli_prepare($link, "DELETE FROM accounts WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('location: view_accounts.php');
exit;
