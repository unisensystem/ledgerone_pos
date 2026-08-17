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
    $company_id  = isset($_GET['company_id'])  && $_GET['company_id']  !== '' ? (int)$_GET['company_id']  : null;
    $supplier_id = isset($_GET['supplier_id']) && $_GET['supplier_id'] !== '' ? (int)$_GET['supplier_id'] : null;

    $sql = "
        SELECT 
            pi.id, 
            pi.bill_no, 
            pi.purchase_date,
            pi.net_amount,
            s.supplier_name,
            c.symbol as currency_symbol
        FROM purchase_invoice pi
        LEFT JOIN suppliers s ON pi.supplier_id = s.id
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        WHERE pi.tenant_id = ?
    ";
    $params = [$tenant_id];
    if ($company_id)  { $sql .= " AND pi.company_id = ?";  $params[] = $company_id; }
    if ($supplier_id) { $sql .= " AND pi.supplier_id = ?"; $params[] = $supplier_id; }
    $sql .= " ORDER BY pi.purchase_date DESC, pi.bill_no DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'invoices' => $invoices]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
