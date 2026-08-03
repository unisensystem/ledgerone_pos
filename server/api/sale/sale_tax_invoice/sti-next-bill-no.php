<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

try {
    $company_id = isset($_GET['company_id']) && $_GET['company_id'] !== '' ? (int)$_GET['company_id'] : null;

    $sql = "SELECT bill_no FROM sale_invoice WHERE tenant_id = ?";
    $params = [$tenant_id];
    if ($company_id) {
        $sql .= " AND company_id = ?";
        $params[] = $company_id;
    }
    $sql .= " ORDER BY id DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $last = $stmt->fetchColumn();
    $newNum = $last ? ((int)substr($last, 4)) + 1 : 1;
    $billNo = 'SAL-' . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    echo json_encode(['success'=>true, 'bill_no'=>$billNo]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
