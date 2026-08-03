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
    $company_id = isset($_GET['company_id']) && $_GET['company_id'] !== '' ? (int)$_GET['company_id'] : null;

    $sql = "SELECT bill_no FROM sale_invoice WHERE tenant_id = ?";
    $params = [$tenant_id];

    if ($company_id) {
        $sql .= " AND company_id = ?";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY id DESC LIMIT 1";
    $billStmt = $pdo->prepare($sql);
    $billStmt->execute($params);
    $lastBill = $billStmt->fetchColumn();

    if ($lastBill) {
        $lastNumber = (int) substr($lastBill, 4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $nextBillNo = 'SAL-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    echo json_encode(['success' => true, 'nextNumber' => $nextBillNo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
