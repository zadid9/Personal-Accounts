<?php
// The only place that knows how a transaction moves money. Every page that creates,
// edits or deletes a transaction goes through these functions.

const TRANSACTION_TYPES = [
    'income' => 'আয়',
    'expense' => 'ব্যয়',
    'transfer' => 'অ্যাকাউন্ট ট্রান্সফার',
    'reimbursement' => 'ফেরতযোগ্য লেনদেন',
    'loan_received' => 'ধার গ্রহণ',
    'loan_given' => 'ধার প্রদান',
    'loan_repayment_made' => 'ধার পরিশোধ (আপনি করেছেন)',
    'loan_repayment_received' => 'ধার পরিশোধ (আপনি পেয়েছেন)',
];
const FROM_TYPES = ['expense', 'transfer', 'reimbursement', 'loan_given', 'loan_repayment_made'];
const TO_TYPES = ['income', 'transfer', 'reimbursement', 'loan_received', 'loan_repayment_received'];
const LOAN_TYPES = ['loan_received', 'loan_given', 'loan_repayment_made', 'loan_repayment_received'];
const REPAYMENT_TYPES = ['loan_repayment_made', 'loan_repayment_received'];
const CATEGORY_TYPES = ['income', 'expense', 'reimbursement'];
// Which kind of loan each loan transaction may point at.
const LOAN_KIND_FOR_TYPE = [
    'loan_received' => 'borrowed',
    'loan_repayment_made' => 'borrowed',
    'loan_given' => 'lent',
    'loan_repayment_received' => 'lent',
];

/**
 * Validates raw form input. $accounts and $loans are the user's own rows (each with 'id';
 * loans also with 'type'), so anything outside them is rejected.
 * Returns [clean data, errors keyed by field].
 */
function ledger_validate(array $in, array $accounts, array $loans): array
{
    $type = trim((string)($in['type'] ?? ''));
    $d = [
        'type' => $type,
        'title' => mb_substr(trim((string)($in['title'] ?? '')), 0, 255),
        'category' => trim((string)($in['category'] ?? '')),
        'amount' => trim((string)($in['amount'] ?? '')),
        'transaction_date' => trim((string)($in['transaction_date'] ?? '')),
        'description' => trim((string)($in['description'] ?? '')),
        'from_account_id' => in_array($type, FROM_TYPES, true) ? (int)($in['from_account_id'] ?? 0) : 0,
        'to_account_id' => in_array($type, TO_TYPES, true) ? (int)($in['to_account_id'] ?? 0) : 0,
        'loan_id' => in_array($type, LOAN_TYPES, true) ? (int)($in['loan_id'] ?? 0) : 0,
    ];
    $e = [];

    if (!array_key_exists($type, TRANSACTION_TYPES)) {
        $e['type'] = 'অনুগ্রহ করে লেনদেনের প্রকার নির্বাচন করুন।';
    }
    if ($d['amount'] === '' || !is_numeric($d['amount']) || (float)$d['amount'] <= 0) {
        $e['amount'] = 'পরিমাণের জন্য একটি বৈধ ধনাত্মক সংখ্যা লিখুন।';
    }
    $date = DateTime::createFromFormat('!Y-m-d', $d['transaction_date']);
    if (!$date || $date->format('Y-m-d') !== $d['transaction_date']) {
        $e['transaction_date'] = 'অনুগ্রহ করে একটি সঠিক তারিখ দিন।';
    }

    if (in_array($type, CATEGORY_TYPES, true)) {
        if ($d['category'] === '') {
            $e['category'] = 'অনুগ্রহ করে একটি ক্যাটাগরি নির্বাচন করুন।';
        } elseif (mb_strlen($d['category']) > 100) {
            $e['category'] = 'ক্যাটাগরির নাম সর্বোচ্চ ১০০ অক্ষর।';
        }
    } else {
        $d['category'] = '';
    }

    $account_ids = array_map('intval', array_column($accounts, 'id'));
    if (in_array($type, FROM_TYPES, true)) {
        if (!$d['from_account_id']) {
            $e['from_account_id'] = 'কোন অ্যাকাউন্ট থেকে টাকা গেছে তা নির্বাচন করুন।';
        } elseif (!in_array($d['from_account_id'], $account_ids, true)) {
            $e['from_account_id'] = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
        }
    }
    if (in_array($type, TO_TYPES, true)) {
        if (!$d['to_account_id']) {
            $e['to_account_id'] = 'কোন অ্যাকাউন্টে টাকা এসেছে তা নির্বাচন করুন।';
        } elseif (!in_array($d['to_account_id'], $account_ids, true)) {
            $e['to_account_id'] = 'অবৈধ অ্যাকাউন্ট নির্বাচন করা হয়েছে।';
        }
    }
    if ($d['from_account_id'] && $d['from_account_id'] === $d['to_account_id']) {
        $e['to_account_id'] = 'উৎস এবং গন্তব্য অ্যাকাউন্ট একই হতে পারে না।';
    }

    if (in_array($type, LOAN_TYPES, true)) {
        $loan_kind = null;
        foreach ($loans as $loan) {
            if ((int)$loan['id'] === $d['loan_id']) {
                $loan_kind = $loan['type'];
            }
        }
        if (!$d['loan_id']) {
            $e['loan_id'] = 'অনুগ্রহ করে সংশ্লিষ্ট ধার/ঋণ নির্বাচন করুন।';
        } elseif ($loan_kind === null) {
            $e['loan_id'] = 'অবৈধ ধার/ঋণ নির্বাচন করা হয়েছে।';
        } elseif ($loan_kind !== LOAN_KIND_FOR_TYPE[$type]) {
            $e['loan_id'] = $loan_kind === 'borrowed'
                ? 'এটি আপনার নেওয়া ধার; এই লেনদেনের জন্য আপনার দেওয়া ধার নির্বাচন করুন।'
                : 'এটি আপনার দেওয়া ধার; এই লেনদেনের জন্য আপনার নেওয়া ধার নির্বাচন করুন।';
        }
    }

    return [$d, $e];
}

function ledger_find(mysqli $link, int $user_id, int $id, bool $lock = false): ?array
{
    $sql = "SELECT id, type, title, category, amount, transaction_date, description, from_account_id, to_account_id, loan_id
            FROM transactions WHERE id = ? AND user_id = ?" . ($lock ? " FOR UPDATE" : "");
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function ledger_create(mysqli $link, int $user_id, array $d): int
{
    return ledger_atomic($link, function () use ($link, $user_id, $d) {
        $amount = (float)$d['amount'];
        $category = $d['category'] === '' ? null : $d['category'];
        $from = $d['from_account_id'] ?: null;
        $to = $d['to_account_id'] ?: null;
        $loan = $d['loan_id'] ?: null;
        $stmt = mysqli_prepare($link, "INSERT INTO transactions
            (user_id, type, title, category, amount, transaction_date, description, from_account_id, to_account_id, loan_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssdssiii", $user_id, $d['type'], $d['title'], $category, $amount,
            $d['transaction_date'], $d['description'], $from, $to, $loan);
        mysqli_stmt_execute($stmt);
        $id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        ledger_apply($link, $user_id, $d, 1);
        return $id;
    });
}

function ledger_update(mysqli $link, int $user_id, int $id, array $d): void
{
    ledger_atomic($link, function () use ($link, $user_id, $id, $d) {
        $old = ledger_find($link, $user_id, $id, true);
        if (!$old) {
            throw new RuntimeException('লেনদেনটি পাওয়া যায়নি।');
        }
        ledger_apply($link, $user_id, $old, -1);

        $amount = (float)$d['amount'];
        $category = $d['category'] === '' ? null : $d['category'];
        $from = $d['from_account_id'] ?: null;
        $to = $d['to_account_id'] ?: null;
        $loan = $d['loan_id'] ?: null;
        $stmt = mysqli_prepare($link, "UPDATE transactions SET
                type = ?, title = ?, category = ?, amount = ?, transaction_date = ?, description = ?,
                from_account_id = ?, to_account_id = ?, loan_id = ?
            WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "sssdssiiiii", $d['type'], $d['title'], $category, $amount,
            $d['transaction_date'], $d['description'], $from, $to, $loan, $id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        ledger_apply($link, $user_id, $d, 1);
    });
}

function ledger_delete(mysqli $link, int $user_id, int $id): bool
{
    return ledger_atomic($link, function () use ($link, $user_id, $id) {
        $old = ledger_find($link, $user_id, $id, true);
        if (!$old) {
            return false;
        }
        ledger_apply($link, $user_id, $old, -1);
        $stmt = mysqli_prepare($link, "DELETE FROM transactions WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    });
}

// $sign = 1 applies a transaction's effect, -1 reverses it.
function ledger_apply(mysqli $link, int $user_id, array $tx, int $sign): void
{
    $amount = $sign * (float)$tx['amount'];

    if (!empty($tx['from_account_id']) && in_array($tx['type'], FROM_TYPES, true)) {
        ledger_adjust_account($link, $user_id, (int)$tx['from_account_id'], -$amount);
    }
    if (!empty($tx['to_account_id']) && in_array($tx['type'], TO_TYPES, true)) {
        ledger_adjust_account($link, $user_id, (int)$tx['to_account_id'], $amount);
    }
    if (!empty($tx['loan_id']) && in_array($tx['type'], REPAYMENT_TYPES, true)) {
        $loan_id = (int)$tx['loan_id'];
        $stmt = mysqli_prepare($link, "UPDATE loans SET paid_amount = paid_amount + ? WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "dii", $amount, $loan_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        ledger_refresh_loan_status($link, $user_id, $loan_id);
    }
}

function ledger_adjust_account(mysqli $link, int $user_id, int $account_id, float $delta): void
{
    $stmt = mysqli_prepare($link, "UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "dii", $delta, $account_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function ledger_refresh_loan_status(mysqli $link, int $user_id, int $loan_id): void
{
    $stmt = mysqli_prepare($link, "UPDATE loans SET status = CASE
            WHEN paid_amount >= amount THEN 'fully_paid'
            WHEN paid_amount > 0 THEN 'partially_paid'
            ELSE 'outstanding' END
        WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $loan_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function ledger_atomic(mysqli $link, callable $work)
{
    mysqli_begin_transaction($link);
    try {
        $result = $work();
        mysqli_commit($link);
        return $result;
    } catch (Throwable $e) {
        mysqli_rollback($link);
        throw $e;
    }
}

/**
 * Recomputes every account balance and loan repayment total from the transactions
 * and returns only the rows where the stored figure disagrees.
 */
function ledger_find_mismatches(mysqli $link, int $user_id): array
{
    $from = "'" . implode("','", FROM_TYPES) . "'";
    $to = "'" . implode("','", TO_TYPES) . "'";
    $rep = "'" . implode("','", REPAYMENT_TYPES) . "'";

    $stmt = mysqli_prepare($link, "SELECT a.id, a.name, a.initial_balance, a.current_balance,
            COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.user_id = a.user_id AND t.to_account_id = a.id AND t.type IN ($to)), 0)
          - COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.user_id = a.user_id AND t.from_account_id = a.id AND t.type IN ($from)), 0) AS net_movement
        FROM accounts a WHERE a.user_id = ? ORDER BY a.name");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $accounts = [];
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) {
        $row['computed_balance'] = round($row['initial_balance'] + $row['net_movement'], 2);
        if (abs($row['computed_balance'] - $row['current_balance']) >= 0.005) {
            $accounts[] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($link, "SELECT l.id, l.party_name, l.type, l.amount, l.paid_amount, l.status,
            COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.user_id = l.user_id AND t.loan_id = l.id AND t.type IN ($rep)), 0) AS computed_paid
        FROM loans l WHERE l.user_id = ? ORDER BY l.party_name");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $loans = [];
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) {
        if (abs($row['computed_paid'] - $row['paid_amount']) >= 0.005) {
            $loans[] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    return ['accounts' => $accounts, 'loans' => $loans];
}
