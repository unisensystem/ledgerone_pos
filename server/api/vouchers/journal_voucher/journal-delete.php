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

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

    // Step 1: Verify voucher exists and belongs to current tenant
    $stmt = $pdo->prepare(
        "SELECT id, status, voucher_number FROM journal_voucher 
         WHERE id = ? AND tenant_id = ?"
    );
    $stmt->execute([$id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }

    // Step 2: Delete ledger entries if posted
    if ($voucher['status'] === 'posted') {
        $stmt = $pdo->prepare(
            "DELETE FROM accounting_ledger 
             WHERE reference_table = ? AND reference_id = ? AND tenant_id = ?"
        );
        $stmt->execute(['journal_voucher', $id, $tenant_id]);
        $ledgerDeleted = $stmt->rowCount();
    } else {
        $ledgerDeleted = 0;
    }

    // Step 3: Delete all journal voucher lines
    $stmt = $pdo->prepare(
        "DELETE FROM journal_voucher_line 
         WHERE voucher_id = ? AND tenant_id = ?"
    );
    $stmt->execute([$id, $tenant_id]);
    $linesDeleted = $stmt->rowCount();

    // Step 4: Delete the journal voucher
    $stmt = $pdo->prepare(
        "DELETE FROM journal_voucher 
         WHERE id = ? AND tenant_id = ?"
    );
    $stmt->execute([$id, $tenant_id]);
    $voucherDeleted = $stmt->rowCount();

    // Step 5: Verify deletion was successful
    if ($voucherDeleted === 0) {
        throw new Exception('Failed to delete voucher record');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Voucher deleted successfully',
        'voucherNumber' => $voucher['voucher_number'],
        'status' => $voucher['status'],
        'linesDeleted' => $linesDeleted,
        'ledgerDeleted' => $ledgerDeleted,
        'voucherDeleted' => $voucherDeleted
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete voucher: ' . $e->getMessage()]);
}
