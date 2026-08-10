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
    $stmt = $pdo->prepare("
        SELECT 
            si.id,
            si.bill_no,
            si.sale_date,
            c.customer_name,
            si.net_amount,
            cur.symbol as currency_symbol
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN fuelingsys_public.currencies cur ON si.currency_id = cur.id
        WHERE si.tenant_id = ? AND si.status = 'Draft'
        ORDER BY si.created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $drafts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'drafts' => $drafts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
