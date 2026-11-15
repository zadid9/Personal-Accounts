<?php
require_once 'auth.php';
require_once 'ledger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ledger_delete($link, $_SESSION['id'], (int)($_POST['id'] ?? 0));
}

$back = 'view_transactions.php';
if (is_string($_POST['month_filter'] ?? null) && is_valid_month($_POST['month_filter'])) {
    $back .= '?month_filter=' . $_POST['month_filter'];
}
header('location: ' . $back);
exit;
