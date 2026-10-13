<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $billStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM sale_invoice WHERE tenant_id = ? AND bill_no LIKE 'SAL-%'");
    $billStmt->execute([$tenant_id]);
    $lastNumber = (int) $billStmt->fetchColumn();
    $newNumber = $lastNumber + 1;
    $nextBillNo = 'SAL-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    echo json_encode(['success' => true, 'nextNumber' => $nextBillNo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
