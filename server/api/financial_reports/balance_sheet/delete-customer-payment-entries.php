<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Delete ledger entries for payment vouchers to customers
    $stmt = $pdo->prepare("
        DELETE al FROM accounting_ledger al
        JOIN payment_voucher pv ON al.reference_id = pv.id AND al.reference_table = 'payment_voucher'
        WHERE al.tenant_id = ? AND pv.customer_id IS NOT NULL
    ");
    $stmt->execute([$tenant_id]);
    $deleted = $stmt->rowCount();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Deleted incorrect payment voucher entries to customers',
        'deleted_entries' => $deleted
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
