<?php
require_once '../../../../includes/connection.php';
require_once __DIR__ . '/cheque-tracking-helper.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$tenantId || !$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function respondChequeBooksError(Throwable $error, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}

/**
 * Splits "D-92851806" into prefix "D-" and digits "92851806".
 */
function parseChequeText(string $text): array
{
    if (!preg_match('/^(.*?)(\d+)$/', trim($text), $m)) {
        throw new InvalidArgumentException('Cheque number must end with digits, e.g. D-92851806.');
    }
    return ['prefix' => $m[1], 'digits' => $m[2]];
}

function formatChequeNumber(string $prefix, int $number, int $width): string
{
    return $prefix . str_pad((string)$number, $width, '0', STR_PAD_LEFT);
}

function chequeBookInput(array $input): array
{
    $accountId = filter_var($input['bank_account_id'] ?? null, FILTER_VALIDATE_INT);
    $fromText = trim((string)($input['cheque_from'] ?? ''));
    $toText = trim((string)($input['cheque_to'] ?? ''));
    if (!$accountId || $fromText === '' || $toText === '') {
        throw new InvalidArgumentException('Bank account and cheque range are required.');
    }

    $fromParts = parseChequeText($fromText);
    $toParts = parseChequeText($toText);
    if (strcasecmp($fromParts['prefix'], $toParts['prefix']) !== 0) {
        throw new InvalidArgumentException('Cheque From and To must have the same prefix.');
    }
    if (strlen($fromParts['prefix']) > 20) {
        throw new InvalidArgumentException('Cheque prefix is too long.');
    }

    $from = (int)$fromParts['digits'];
    $to = (int)$toParts['digits'];
    if ($to < $from) {
        throw new InvalidArgumentException('Cheque Number To must be greater than or equal to Cheque Number From.');
    }
    if (($to - $from) > 1000000) {
        throw new InvalidArgumentException('A cheque book cannot contain more than 1,000,001 cheques.');
    }

    $issueDate = trim((string)($input['issue_date'] ?? ''));
    $date = DateTime::createFromFormat('Y-m-d', $issueDate);
    if (!$date || $date->format('Y-m-d') !== $issueDate) {
        throw new InvalidArgumentException('A valid issue date is required.');
    }

    return [
        'bank_account_id' => $accountId,
        'cheque_prefix' => $fromParts['prefix'],
        'cheque_from' => $from,
        'cheque_to' => $to,
        'number_width' => max(strlen($fromParts['digits']), strlen($toParts['digits'])),
        'issue_date' => $issueDate,
        'remarks' => trim((string)($input['remarks'] ?? ''))
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && $action === 'banks') {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT bank_name FROM bank_accounts
             WHERE tenant_id = ? AND is_active = 1 AND bank_name <> ""
             ORDER BY bank_name'
        );
        $stmt->execute([$tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        exit;
    }

    if ($method === 'GET' && $action === 'accounts') {
        $stmt = $pdo->prepare(
            'SELECT id, bank_name, account_number, account_title
             FROM bank_accounts WHERE tenant_id = ? AND is_active = 1
             ORDER BY bank_name, account_number'
        );
        $stmt->execute([$tenantId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($method === 'GET' && $action === 'available') {
        $accountId = filter_var($_GET['bank_account_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$accountId) {
            throw new InvalidArgumentException('Select a bank account first.');
        }
        $account = $pdo->prepare('SELECT id FROM bank_accounts WHERE tenant_id = ? AND id = ? AND is_active = 1');
        $account->execute([$tenantId, $accountId]);
        if (!$account->fetchColumn()) {
            throw new InvalidArgumentException('The selected bank account is not available.');
        }

        $exclude = null;
        $referenceTable = $_GET['reference_table'] ?? '';
        $referenceId = filter_var($_GET['reference_id'] ?? null, FILTER_VALIDATE_INT);
        $itemId = filter_var($_GET['reference_item_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($referenceId && in_array($referenceTable, ['payment_voucher', 'transfer_voucher', 'expense_voucher', 'payroll_entries'], true)) {
            $exclude = ['table' => $referenceTable, 'id' => $referenceId, 'item_id' => $itemId ?: 0];
        }

        $used = getUsedChequeNumbers($pdo, $tenantId, $accountId, $exclude);
        $wasteStmt = $pdo->prepare('SELECT cheque_no FROM wasted_cheques WHERE tenant_id = ? AND bank_account_id = ?');
        $wasteStmt->execute([$tenantId, $accountId]);
        $wasted = [];
        foreach ($wasteStmt->fetchAll(PDO::FETCH_COLUMN) as $wastedNo) {
            if (preg_match('/(\d+)\s*$/', (string)$wastedNo, $wm)) {
                $wasted[(int)$wm[1]] = true;
            }
        }

        $bookStmt = $pdo->prepare(
            'SELECT id, cheque_prefix, cheque_from, cheque_to, number_width FROM cheque_books
             WHERE tenant_id = ? AND bank_account_id = ? ORDER BY cheque_from'
        );
        $bookStmt->execute([$tenantId, $accountId]);
        $numbers = [];
        foreach ($bookStmt->fetchAll(PDO::FETCH_ASSOC) as $book) {
            for ($number = (int)$book['cheque_from']; $number <= (int)$book['cheque_to']; $number++) {
                if (!isset($used[$number]) && !isset($wasted[$number])) {
                    $numbers[] = [
                        'cheque_no' => formatChequeNumber((string)$book['cheque_prefix'], $number, (int)$book['number_width']),
                        'cheque_book_id' => (int)$book['id']
                    ];
                }
            }
        }
        echo json_encode(['success' => true, 'data' => $numbers]);
        exit;
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT cb.id, cb.bank_account_id, cb.cheque_prefix, cb.cheque_from, cb.cheque_to, cb.number_width,
                    cb.issue_date, cb.remarks, ba.bank_name, ba.account_number, ba.account_title
             FROM cheque_books cb
             JOIN bank_accounts ba ON ba.id = cb.bank_account_id AND ba.tenant_id = cb.tenant_id
             WHERE cb.tenant_id = ?
             ORDER BY cb.issue_date DESC, cb.id DESC'
        );
        $stmt->execute([$tenantId]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $usedByAccount = [];
        foreach ($books as &$book) {
            $from = (int)$book['cheque_from'];
            $to = (int)$book['cheque_to'];
            $width = (int)$book['number_width'];
            $book['cheque_from_display'] = formatChequeNumber((string)$book['cheque_prefix'], $from, $width);
            $book['cheque_to_display'] = formatChequeNumber((string)$book['cheque_prefix'], $to, $width);
            $accountId = (int)$book['bank_account_id'];
            if (!isset($usedByAccount[$accountId])) {
                $usedByAccount[$accountId] = getUsedChequeNumbers($pdo, $tenantId, $accountId);
            }
            $book['total_cheques'] = $to - $from + 1;
            $book['used_cheques'] = 0;
            foreach ($usedByAccount[$accountId] as $number => $_) {
                if ($number >= $from && $number <= $to) {
                    $book['used_cheques']++;
                }
            }
            $wasteStmt = $pdo->prepare('SELECT cheque_no FROM wasted_cheques WHERE tenant_id = ? AND cheque_book_id = ?');
            $wasteStmt->execute([$tenantId, $book['id']]);
            $wastedCount = 0;
            foreach ($wasteStmt->fetchAll(PDO::FETCH_COLUMN) as $wastedNumber) {
                $wastedDigits = preg_match('/(\d+)\s*$/', (string)$wastedNumber, $wm) ? (int)$wm[1] : 0;
                if (!isset($usedByAccount[$accountId][$wastedDigits])) {
                    $wastedCount++;
                }
            }
            $book['wasted_cheques'] = $wastedCount;
            $book['available_cheques'] = $book['total_cheques'] - $book['used_cheques'] - $book['wasted_cheques'];
        }
        unset($book);
        echo json_encode(['success' => true, 'data' => $books]);
        exit;
    }

    if ($method === 'POST' || $method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            throw new InvalidArgumentException('Invalid request data.');
        }
        $fields = chequeBookInput($input);
        $bookId = $method === 'PUT' ? filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) : null;
        if ($method === 'PUT' && !$bookId) {
            throw new InvalidArgumentException('Cheque book ID is required.');
        }

        $pdo->beginTransaction();
        $accountStmt = $pdo->prepare(
            'SELECT id FROM bank_accounts WHERE id = ? AND tenant_id = ? AND is_active = 1 FOR UPDATE'
        );
        $accountStmt->execute([$fields['bank_account_id'], $tenantId]);
        if (!$accountStmt->fetchColumn()) {
            throw new InvalidArgumentException('The selected bank account is not available.');
        }

        if ($bookId) {
            $currentStmt = $pdo->prepare(
                'SELECT bank_account_id, cheque_prefix, cheque_from, cheque_to, number_width
                 FROM cheque_books WHERE id = ? AND tenant_id = ? FOR UPDATE'
            );
            $currentStmt->execute([$bookId, $tenantId]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                throw new InvalidArgumentException('Cheque book not found.');
            }
            if (
                (int)$current['bank_account_id'] !== $fields['bank_account_id']
                || (string)$current['cheque_prefix'] !== $fields['cheque_prefix']
                || (int)$current['cheque_from'] !== $fields['cheque_from']
                || (int)$current['cheque_to'] !== $fields['cheque_to']
                || (int)$current['number_width'] !== $fields['number_width']
            ) {
                $countStmt = $pdo->prepare(
                    'SELECT
                        (SELECT COUNT(*) FROM cheque_usage WHERE tenant_id = ? AND cheque_book_id = ?) +
                        (SELECT COUNT(*) FROM wasted_cheques WHERE tenant_id = ? AND cheque_book_id = ?)'
                );
                $countStmt->execute([$tenantId, $bookId, $tenantId, $bookId]);
                if ((int)$countStmt->fetchColumn() > 0 || chequeBookHasUsedCheques(
                    $pdo,
                    $tenantId,
                    (int)$current['bank_account_id'],
                    (int)$current['cheque_from'],
                    (int)$current['cheque_to']
                )) {
                    throw new InvalidArgumentException('A cheque book range or account cannot be changed after cheques have been used or wasted.');
                }
            }
        }

        $overlapSql = 'SELECT id FROM cheque_books
                       WHERE tenant_id = ? AND bank_account_id = ?
                         AND cheque_from <= ? AND cheque_to >= ?';
        $overlapParams = [$tenantId, $fields['bank_account_id'], $fields['cheque_to'], $fields['cheque_from']];
        if ($bookId) {
            $overlapSql .= ' AND id <> ?';
            $overlapParams[] = $bookId;
        }
        $overlapSql .= ' LIMIT 1 FOR UPDATE';
        $overlap = $pdo->prepare($overlapSql);
        $overlap->execute($overlapParams);
        if ($overlap->fetchColumn()) {
            throw new InvalidArgumentException('This cheque range overlaps another cheque book for the selected bank account.');
        }

        if ($bookId) {
            $stmt = $pdo->prepare(
                'UPDATE cheque_books
                 SET bank_account_id = ?, cheque_prefix = ?, cheque_from = ?, cheque_to = ?, number_width = ?, issue_date = ?, remarks = ?
                 WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([
                $fields['bank_account_id'], $fields['cheque_prefix'], $fields['cheque_from'], $fields['cheque_to'],
                $fields['number_width'], $fields['issue_date'], $fields['remarks'], $bookId, $tenantId
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO cheque_books
                 (tenant_id, bank_account_id, cheque_prefix, cheque_from, cheque_to, number_width, issue_date, remarks, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $tenantId, $fields['bank_account_id'], $fields['cheque_prefix'], $fields['cheque_from'], $fields['cheque_to'],
                $fields['number_width'], $fields['issue_date'], $fields['remarks'], $userId
            ]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $bookId ? 'Cheque book updated.' : 'Cheque book added.']);
        exit;
    }

    if ($method === 'DELETE') {
        $bookId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$bookId) {
            throw new InvalidArgumentException('Cheque book ID is required.');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM cheque_books WHERE id = ? AND tenant_id = ? FOR UPDATE');
        $stmt->execute([$bookId, $tenantId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('Cheque book not found.');
        }
        $count = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM cheque_usage WHERE tenant_id = ? AND cheque_book_id = ?) +
                (SELECT COUNT(*) FROM wasted_cheques WHERE tenant_id = ? AND cheque_book_id = ?)'
        );
        $count->execute([$tenantId, $bookId, $tenantId, $bookId]);
        $bookAccount = $pdo->prepare('SELECT bank_account_id, cheque_from, cheque_to FROM cheque_books WHERE id = ? AND tenant_id = ?');
        $bookAccount->execute([$bookId, $tenantId]);
        $bookDetails = $bookAccount->fetch(PDO::FETCH_ASSOC);
        if (
            (int)$count->fetchColumn() > 0
            || chequeBookHasUsedCheques($pdo, $tenantId, (int)$bookDetails['bank_account_id'], (int)$bookDetails['cheque_from'], (int)$bookDetails['cheque_to'])
        ) {
            throw new InvalidArgumentException('A cheque book with used or wasted cheques cannot be deleted.');
        }
        $pdo->prepare('DELETE FROM cheque_books WHERE id = ? AND tenant_id = ?')->execute([$bookId, $tenantId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cheque book deleted.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method or action not allowed.']);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error instanceof PDOException) {
        error_log('Cheque book API database error: ' . $error->getMessage());
        respondChequeBooksError(new RuntimeException('Unable to complete the cheque book request.'), 500);
    } else {
        respondChequeBooksError($error, 400);
    }
}