<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT payroll_code FROM payroll_entries WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        // Extract number from last code (e.g., PR0001 -> 1)
        $lastNumber = intval(str_replace('PR', '', $lastCode));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    $newCode = 'PR' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    echo json_encode(['success' => true, 'payroll_code' => $newCode]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
