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
    
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'Balance Sheet Adjustment'");
    $stmt->execute([$tenant_id]);
    $deleted = $stmt->rowCount();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Balance Sheet Adjustment deleted',
        'deleted_entries' => $deleted
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
