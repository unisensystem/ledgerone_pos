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

$voucher_id = $data['id'] ?? null;
$status = $data['status'] ?? 'posted';
$new_company_id = $data['company_id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID is required']);
    exit();
}

try {
    // Verify voucher exists and belongs to current tenant
    $checkStmt = $pdo->prepare(
        "SELECT id, company_id FROM journal_voucher WHERE id = ? AND tenant_id = ?"
    );
    $checkStmt->execute([$voucher_id, $tenant_id]);
    $existingVoucher = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingVoucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    $pdo->beginTransaction();

    // Update journal voucher header
    $stmt = $pdo->prepare(
        "UPDATE journal_voucher 
         SET company_id = ?, voucher_date = ?, description = ?, total_debit = ?, total_credit = ?, status = ? 
         WHERE id = ? AND tenant_id = ?"
    );
    $stmt->execute([
        $new_company_id,
        $data['date'],
        $data['description'],
        $data['totalDebit'],
        $data['totalCredit'],
        $status,
        $voucher_id,
        $tenant_id
    ]);

    // Remove old ledger entries linked to this voucher (re-inserted below if still posted)
    $stmt_del_ledger = $pdo->prepare(
        "DELETE FROM accounting_ledger WHERE tenant_id = ? AND reference_table = 'journal_voucher' AND reference_id = ?"
    );
    $stmt_del_ledger->execute([$tenant_id, $voucher_id]);

    // Remove old lines, then re-insert the edited set
    $stmt_del_lines = $pdo->prepare(
        "DELETE FROM journal_voucher_line WHERE tenant_id = ? AND voucher_id = ?"
    );
    $stmt_del_lines->execute([$tenant_id, $voucher_id]);

    $stmt_line = $pdo->prepare(
        "INSERT INTO journal_voucher_line (tenant_id, voucher_id, account_id, debit, credit) 
         VALUES (?, ?, ?, ?, ?)"
    );

    // Only write to ledger if the (possibly changed) status is posted
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
        'message' => $status === 'draft' ? 'Journal voucher updated and saved as draft' : 'Journal voucher updated and posted successfully',
        'voucher_id' => $voucher_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update journal voucher: ' . $e->getMessage()]);
}