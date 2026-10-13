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

$supplier_id = $_GET['supplier_id'] ?? null;

if (!$supplier_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Supplier ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, sub_account_name FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? ORDER BY sub_account_name");
    $stmt->execute([$tenant_id, $supplier_id]);
    $subAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'sub_accounts' => $subAccounts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
