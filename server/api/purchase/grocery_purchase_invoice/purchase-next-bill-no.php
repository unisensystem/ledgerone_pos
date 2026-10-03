<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $billStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM purchase_invoice WHERE tenant_id = ? AND bill_no LIKE 'PUR-%'");
    $billStmt->execute([$tenant_id]);
    $lastNumber = (int)$billStmt->fetchColumn();
    $billNo = 'PUR-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

    echo json_encode(['success' => true, 'bill_no' => $billNo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
