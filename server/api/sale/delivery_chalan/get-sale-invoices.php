<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT si.id, si.bill_no, si.sale_date, c.customer_name
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id
        WHERE si.tenant_id = ? AND si.status = 'Posted'
        ORDER BY si.id DESC
    ");
    $stmt->execute([$tenant_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'invoices'=>$invoices]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
