<?php

/**
 * Returns the trailing digits of a cheque number as an integer
 * ("D-92851806" => 92851806, "000123" => 123), or null when there are none.
 * Used for scanning existing DB rows, so it never throws.
 */
function extractChequeDigits($value): ?int
{
    if (!preg_match('/(\d+)\s*$/', trim((string)$value), $m)) {
        return null;
    }
    $canonical = ltrim($m[1], '0');
    $canonical = $canonical === '' ? '0' : $canonical;
    if (strlen($canonical) > 18) {
        return null;
    }
    return (int)$canonical;
}

/**
 * Returns the non-numeric prefix of a cheque number ("D-92851806" => "D-").
 */
function extractChequePrefix($value): string
{
    if (!preg_match('/^(.*?)\d+\s*$/', trim((string)$value), $m)) {
        return '';
    }
    return $m[1];
}

function normalizeChequeNumber($value): int
{
    $text = trim((string)$value);
    if ($text === '' || !preg_match('/(\d+)\s*$/', $text, $m)) {
        throw new InvalidArgumentException('Cheque number must end with digits, e.g. D-92851806.');
    }

    $canonical = ltrim($m[1], '0');
    $canonical = $canonical === '' ? '0' : $canonical;
    if (strlen($canonical) > 18) {
        throw new InvalidArgumentException('Cheque number is too large.');
    }

    return (int)$canonical;
}

function releaseIssuedChequeUsage(PDO $pdo, int $tenantId, string $referenceTable, int $referenceId, ?int $referenceItemId = null): void
{
    $sql = 'DELETE FROM cheque_usage WHERE tenant_id = ? AND reference_table = ? AND reference_id = ?';
    $params = [$tenantId, $referenceTable, $referenceId];
    if ($referenceItemId !== null) {
        $sql .= ' AND reference_item_id = ?';
        $params[] = $referenceItemId;
    }
    $pdo->prepare($sql)->execute($params);
}

function syncIssuedChequeUsage(
    PDO $pdo,
    int $tenantId,
    int $userId,
    string $referenceTable,
    int $referenceId,
    int $referenceItemId,
    bool $isCheque,
    ?int $bankAccountId,
    $chequeNumber,
    ?string $chequeDate
): void {
    releaseIssuedChequeUsage($pdo, $tenantId, $referenceTable, $referenceId, $referenceItemId);
    if (!$isCheque) {
        return;
    }

    if (!$bankAccountId || !$chequeNumber) {
        throw new InvalidArgumentException('Select a bank account and an available cheque number.');
    }
    $number = normalizeChequeNumber($chequeNumber);

    $accountStmt = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = ? AND tenant_id = ? AND is_active = 1 FOR UPDATE');
    $accountStmt->execute([$bankAccountId, $tenantId]);
    if (!$accountStmt->fetchColumn()) {
        throw new InvalidArgumentException('The selected bank account is not available.');
    }

    $bookStmt = $pdo->prepare(
        'SELECT id FROM cheque_books
         WHERE tenant_id = ? AND bank_account_id = ? AND cheque_from <= ? AND cheque_to >= ?
         LIMIT 1 FOR UPDATE'
    );
    $bookStmt->execute([$tenantId, $bankAccountId, $number, $number]);
    $bookId = $bookStmt->fetchColumn();
    if (!$bookId) {
        throw new InvalidArgumentException('The cheque number is not in an issued cheque book for this bank account.');
    }

    $wasteStmt = $pdo->prepare('SELECT id FROM wasted_cheques WHERE tenant_id = ? AND bank_account_id = ? AND cheque_no = ?');
    $wasteStmt->execute([$tenantId, $bankAccountId, $number]);
    if ($wasteStmt->fetchColumn()) {
        throw new InvalidArgumentException('This cheque has been marked as wasted and cannot be used.');
    }

    $usageStmt = $pdo->prepare(
        'SELECT id FROM cheque_usage
         WHERE tenant_id = ? AND bank_account_id = ? AND cheque_no = ?
           AND NOT (reference_table = ? AND reference_id = ? AND reference_item_id = ?)
         LIMIT 1'
    );
    $usageStmt->execute([$tenantId, $bankAccountId, $number, $referenceTable, $referenceId, $referenceItemId]);
    if ($usageStmt->fetchColumn() || chequeIsUsedByAnotherTransaction($pdo, $tenantId, $bankAccountId, $number, $referenceTable, $referenceId, $referenceItemId)) {
        throw new InvalidArgumentException('This cheque number has already been used.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO cheque_usage
         (tenant_id, cheque_book_id, bank_account_id, cheque_no, reference_table, reference_id, reference_item_id, cheque_date, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $tenantId,
        $bookId,
        $bankAccountId,
        $number,
        $referenceTable,
        $referenceId,
        $referenceItemId,
        $chequeDate,
        $userId
    ]);
}

/**
 * True when the cheque number is used by any transaction other than the one being saved.
 * Works with prefixed numbers because digits are extracted in PHP.
 */
function chequeIsUsedByAnotherTransaction(
    PDO $pdo,
    int $tenantId,
    int $bankAccountId,
    int $chequeNumber,
    string $referenceTable,
    int $referenceId,
    int $referenceItemId
): bool {
    $used = getUsedChequeNumbers($pdo, $tenantId, $bankAccountId, [
        'table' => $referenceTable,
        'id' => $referenceId,
        'item_id' => $referenceItemId
    ]);
    return isset($used[$chequeNumber]);
}

function getUsedChequeNumbers(PDO $pdo, int $tenantId, int $bankAccountId, ?array $excludeReference = null): array
{
    $used = [];
    $queries = [
        "SELECT cheque_no, 'payment_voucher' AS reference_table, id AS reference_id, 0 AS reference_item_id
         FROM payment_voucher
         WHERE tenant_id = ? AND payment_method_id = 6 AND bank_account_id = ?
           AND cheque_no IS NOT NULL AND TRIM(cheque_no) <> ''",
        "SELECT tv.cheque_no AS cheque_no, 'transfer_voucher' AS reference_table,
                tv.id AS reference_id, 0 AS reference_item_id
         FROM transfer_voucher tv
         WHERE tv.tenant_id = ? AND tv.payment_method_id = 6 AND tv.bank_account_id = ?
           AND tv.cheque_no IS NOT NULL AND TRIM(tv.cheque_no) <> ''",
        "SELECT evl.cheque_no AS cheque_no, 'expense_voucher' AS reference_table,
                evl.voucher_id AS reference_id, evl.id AS reference_item_id
         FROM expense_voucher_line evl
         WHERE evl.tenant_id = ? AND evl.payment_method_id = 6 AND evl.bank_account_id = ?
           AND evl.cheque_no IS NOT NULL AND TRIM(evl.cheque_no) <> ''",
        "SELECT pei.cheque_no AS cheque_no, 'payroll_entries' AS reference_table,
                pei.payroll_id AS reference_id, pei.id AS reference_item_id
         FROM payroll_entries_items pei
         WHERE pei.tenant_id = ? AND pei.payment_method_id = 6 AND pei.bank_account_id = ?
           AND pei.cheque_no IS NOT NULL AND TRIM(pei.cheque_no) <> ''",
        "SELECT pei.cheque_no AS cheque_no, 'production_expenses' AS reference_table,
                pei.expense_id AS reference_id, pei.id AS reference_item_id
         FROM production_expense_items pei
         JOIN production_expenses pe ON pe.id = pei.expense_id
         WHERE pe.tenant_id = ? AND pei.payment_status = 'paid' AND pei.payment_mode = 'cheque'
           AND pei.bank_account_id = ?
           AND pei.cheque_no IS NOT NULL AND TRIM(pei.cheque_no) <> ''",
        "SELECT cheque_no, reference_table, reference_id, reference_item_id
         FROM cheque_usage WHERE tenant_id = ? AND bank_account_id = ?"
    ];

    foreach ($queries as $query) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tenantId, $bankAccountId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (
                $excludeReference !== null
                && $row['reference_table'] === $excludeReference['table']
                && (int)$row['reference_id'] === (int)$excludeReference['id']
                && (int)$row['reference_item_id'] === (int)($excludeReference['item_id'] ?? 0)
            ) {
                continue;
            }
            $digits = extractChequeDigits($row['cheque_no']);
            if ($digits === null) {
                continue;
            }
            $used[$digits] = true;
        }
    }

    return $used;
}

function chequeBookHasUsedCheques(PDO $pdo, int $tenantId, int $bankAccountId, int $chequeFrom, int $chequeTo): bool
{
    foreach (getUsedChequeNumbers($pdo, $tenantId, $bankAccountId) as $number => $_) {
        if ($number >= $chequeFrom && $number <= $chequeTo) {
            return true;
        }
    }
    return false;
}