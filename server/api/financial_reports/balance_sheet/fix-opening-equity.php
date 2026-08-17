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
    
    // Delete Balance Sheet Adjustment
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Balance Sheet Adjustment'");
    $stmt->execute([$tenant_id]);
    $deleted_adjustment = $stmt->rowCount();
    
    // Delete CASH_OPENING from Opening Balance Equity
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'CASH_OPENING' AND account_id = 90");
    $stmt->execute([$tenant_id]);
    $deleted_cash_opening = $stmt->rowCount();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Incorrect entries deleted from Opening Balance Equity',
        'deleted' => [
            'balance_sheet_adjustment' => $deleted_adjustment,
            'cash_opening' => $deleted_cash_opening,
            'total' => $deleted_adjustment + $deleted_cash_opening
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
