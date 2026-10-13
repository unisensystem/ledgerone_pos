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
    $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

    $sql = "SELECT id, supplier_code, supplier_name, primary_phone, current_balance FROM suppliers WHERE tenant_id = ? AND status = 'ACTIVE'";
    $params = [$tenant_id];

    if ($company_id) {
        $sql .= " AND company_id = ?";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY supplier_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}