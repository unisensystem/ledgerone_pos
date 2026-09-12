<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/posting_lock.php';
require_once '../../../../includes/activity-logger.php';
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

// Draft ko lock apply nahi hota, sirf Posted pe
if ($status === 'posted') {
    $lockCheck = checkPostingDateAllowed($pdo, $tenant_id, 'journal_voucher', $data['date']);
    if (!$lockCheck['allowed']) {
        http_response_code(400);
        echo json_encode(['error' => $lockCheck['message']]);
        exit();
    }
}

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

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Journal Voucher', $voucher_id, $data['voucherNumber'],
        "Created journal voucher \"{$data['voucherNumber']}\" (" . count($data['entries']) . " line(s), " . ($status === 'draft' ? 'Draft' : 'Posted') . ")",
        null,
        [
            'voucher_number' => $data['voucherNumber'], 'voucher_date' => $data['date'], 'company_id' => $data['company_id'] ?? null,
            'description' => $data['description'], 'total_debit' => $data['totalDebit'], 'total_credit' => $data['totalCredit'],
            'status' => $status, 'entries' => $data['entries']
        ]
    );

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