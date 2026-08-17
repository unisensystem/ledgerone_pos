<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID required']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Check if voucher exists and is draft
    $stmt = $pdo->prepare(
        "SELECT id, status, voucher_date, description FROM journal_voucher WHERE id = ? AND tenant_id = ?"
    );
    $stmt->execute([$id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    if ($voucher['status'] !== 'draft') {
        http_response_code(400);
        echo json_encode(['error' => 'Only draft vouchers can be posted']);
        exit();
    }

    // Update voucher status to posted
    $stmt = $pdo->prepare("UPDATE journal_voucher SET status = 'posted' WHERE id = ?");
    $stmt->execute([$id]);

    // Get voucher lines
    $stmt = $pdo->prepare(
        "SELECT account_id, debit, credit FROM journal_voucher_line WHERE voucher_id = ?"
    );
    $stmt->execute([$id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Insert to accounting ledger
    $stmt_ledger = $pdo->prepare(
        "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
         VALUES (?, 'Journal Voucher', 'journal_voucher', ?, ?, ?, ?, ?, ?)"
    );

    foreach ($lines as $line) {
        $stmt_ledger->execute([
            $tenant_id,
            $id,
            $line['account_id'],
            $voucher['voucher_date'],
            $voucher['description'],
            $line['debit'],
            $line['credit']
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Voucher posted successfully']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to post voucher: ' . $e->getMessage()]);
}
