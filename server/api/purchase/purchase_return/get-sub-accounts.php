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
    echo json_encode(['success' => true, 'sub_accounts' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, sub_account_name FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? ORDER BY sub_account_name");
    $stmt->execute([$tenant_id, $supplier_id]);
    $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'sub_accounts' => $sub_accounts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
