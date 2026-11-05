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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $bookId = filter_var($_GET['book_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$bookId) {
            throw new InvalidArgumentException('Cheque book ID is required.');
        }
        $stmt = $pdo->prepare(
            'SELECT wc.id, wc.cheque_book_id, wc.bank_account_id, wc.cheque_no, wc.waste_date, wc.reason,
                    cb.number_width, cb.cheque_prefix
             FROM wasted_cheques wc
             JOIN cheque_books cb ON cb.id = wc.cheque_book_id AND cb.tenant_id = wc.tenant_id
             WHERE wc.tenant_id = ? AND wc.cheque_book_id = ?
             ORDER BY wc.cheque_no'
        );
        $stmt->execute([$tenantId, $bookId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['cheque_no'] = (string)$row['cheque_prefix']
                . str_pad((string)$row['cheque_no'], (int)$row['number_width'], '0', STR_PAD_LEFT);
        }
        unset($row);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            throw new InvalidArgumentException('Invalid request data.');
        }
        $bookId = filter_var($input['cheque_book_id'] ?? null, FILTER_VALIDATE_INT);
        $chequeText = trim((string)($input['cheque_no'] ?? ''));
        $number = normalizeChequeNumber($chequeText);
        $submittedPrefix = extractChequePrefix($chequeText);
        $wasteDate = trim((string)($input['waste_date'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        $date = DateTime::createFromFormat('Y-m-d', $wasteDate);
        if (!$bookId || !$date || $date->format('Y-m-d') !== $wasteDate || $reason === '') {
            throw new InvalidArgumentException('Cheque book, valid waste date, and reason are required.');
        }

        $pdo->beginTransaction();
        $bookStmt = $pdo->prepare('SELECT id, bank_account_id, cheque_prefix FROM cheque_books WHERE id = ? AND tenant_id = ?');
        $bookStmt->execute([$bookId, $tenantId]);
        $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
        if (!$book) {
            throw new InvalidArgumentException('Cheque book not found.');
        }
        if ($submittedPrefix !== '' && strcasecmp($submittedPrefix, (string)$book['cheque_prefix']) !== 0) {
            throw new InvalidArgumentException('The cheque prefix does not match this cheque book.');
        }
        $accountLock = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = ? AND tenant_id = ? FOR UPDATE');
        $accountLock->execute([$book['bank_account_id'], $tenantId]);
        $bookLock = $pdo->prepare('SELECT id FROM cheque_books WHERE id = ? AND tenant_id = ? FOR UPDATE');
        $bookLock->execute([$bookId, $tenantId]);
        if (!$bookLock->fetchColumn()) {
            throw new InvalidArgumentException('Cheque book not found.');
        }
        $rangeStmt = $pdo->prepare(
            'SELECT id FROM cheque_books
             WHERE id = ? AND tenant_id = ? AND cheque_from <= ? AND cheque_to >= ?'
        );
        $rangeStmt->execute([$bookId, $tenantId, $number, $number]);
        if (!$rangeStmt->fetchColumn()) {
            throw new InvalidArgumentException('This cheque number is outside the selected cheque book.');
        }

        $used = getUsedChequeNumbers($pdo, $tenantId, (int)$book['bank_account_id']);
        if (isset($used[$number])) {
            throw new InvalidArgumentException('A used cheque cannot be marked as wasted.');
        }
        $alreadyWasted = $pdo->prepare('SELECT id FROM wasted_cheques WHERE tenant_id = ? AND bank_account_id = ? AND cheque_no = ?');
        $alreadyWasted->execute([$tenantId, $book['bank_account_id'], $number]);
        if ($alreadyWasted->fetchColumn()) {
            throw new InvalidArgumentException('This cheque is already marked as wasted.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO wasted_cheques
             (tenant_id, cheque_book_id, bank_account_id, cheque_no, waste_date, reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $bookId, $book['bank_account_id'], $number, $wasteDate, $reason, $userId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cheque marked as wasted.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $wasteId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$wasteId) {
            throw new InvalidArgumentException('Wasted cheque ID is required.');
        }
        $pdo->beginTransaction();
        $wasteStmt = $pdo->prepare(
            'SELECT bank_account_id, cheque_no FROM wasted_cheques WHERE id = ? AND tenant_id = ? FOR UPDATE'
        );
        $wasteStmt->execute([$wasteId, $tenantId]);
        $waste = $wasteStmt->fetch(PDO::FETCH_ASSOC);
        if (!$waste) {
            throw new InvalidArgumentException('Wasted cheque not found.');
        }
        $accountLock = $pdo->prepare('SELECT id FROM bank_accounts WHERE id = ? AND tenant_id = ? FOR UPDATE');
        $accountLock->execute([$waste['bank_account_id'], $tenantId]);
        $used = getUsedChequeNumbers($pdo, $tenantId, (int)$waste['bank_account_id']);
        if (isset($used[(int)$waste['cheque_no']])) {
            throw new InvalidArgumentException('A cheque that has since been used cannot be unmarked as wasted.');
        }
        $pdo->prepare('DELETE FROM wasted_cheques WHERE id = ? AND tenant_id = ?')->execute([$wasteId, $tenantId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Wasted cheque mark removed.']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error instanceof PDOException) {
        error_log('Wasted cheque API database error: ' . $error->getMessage());
    }
    http_response_code($error instanceof PDOException ? 500 : 400);
    echo json_encode(['success' => false, 'message' => $error instanceof PDOException ? 'Unable to save wasted cheque.' : $error->getMessage()]);
}