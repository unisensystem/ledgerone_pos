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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit();
}

$status = $data['status'] ?? 'posted';

try {
    $pdo->beginTransaction();

    // Insert journal voucher
    $stmt = $pdo->prepare(
        "INSERT INTO journal_voucher (tenant_id, company_id, voucher_number, voucher_date, description, total_debit, total_credit, created_by, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $tenant_id,
        $data['company_id'] ?? null,
        $data['voucherNumber'],
        $data['date'],
        $data['description'],
        $data['totalDebit'],
        $data['totalCredit'],
        $user_id,
        $status
    ]);
    $voucher_id = $pdo->lastInsertId();

    // Prepare statement for lines
    $stmt_line = $pdo->prepare(
        "INSERT INTO journal_voucher_line (tenant_id, voucher_id, account_id, debit, credit) 
         VALUES (?, ?, ?, ?, ?)"
    );

    // Only insert to ledger if status is posted
    if ($status === 'posted') {
        $stmt_ledger = $pdo->prepare(
            "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
             VALUES (?, 'Journal Voucher', 'journal_voucher', ?, ?, ?, ?, ?, ?)"
        );
    }

    foreach ($data['entries'] as $entry) {
        $stmt_line->execute([
            $tenant_id,
            $voucher_id,
            $entry['accountId'],
            $entry['debit'],
            $entry['credit']
        ]);

        // Only insert to ledger if posted
        if ($status === 'posted') {
            $stmt_ledger->execute([
                $tenant_id,
                $voucher_id,
                $entry['accountId'],
                $data['date'],
                $entry['lineDescription'],
                $entry['debit'],
                $entry['credit']
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $status === 'draft' ? 'Journal voucher saved as draft' : 'Journal voucher posted successfully',
        'voucher_id' => $voucher_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to post journal voucher: ' . $e->getMessage()]);
}