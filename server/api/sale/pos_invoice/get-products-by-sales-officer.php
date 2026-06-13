<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$sales_officer_id = $_GET['sales_officer_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$sales_officer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sales Officer ID is required']);
    exit;
}

try {
    // First, get all suppliers where sales_officer_id is in the comma-separated salesman_id field
    $supplierStmt = $pdo->prepare("
        SELECT id FROM suppliers
        WHERE tenant_id = ? AND FIND_IN_SET(?, salesman_id)
    ");
    $supplierStmt->execute([$tenant_id, $sales_officer_id]);
    $suppliers = $supplierStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($suppliers)) {
        echo json_encode(['success' => true, 'products' => []]);
        exit;
    }
    
    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($suppliers), '?'));
    
    // Now get all products from these suppliers
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.code, p.name, p.mrp, p.trade_price, p.default_unit_id, 
            p.barcode, p.qr_code, p.photo, p.carton_conversion, p.stock_affects, 
            p.invoice_affects, p.parent_product_id, p.default_discount, 
            p.trade_offer_discount, p.sales_tax, p.sales_tax_type,
            p.uom_type, p.uom_group_id, p.product_conversion_factor,
            u.uom_name as default_unit_name, u.unit_scope as default_unit_scope, 
            u.is_base_unit as default_is_base_unit, u.conversion_factor as default_conversion_factor
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_product_id IS NULL 
        AND p.vendor_id IN ($placeholders)
        ORDER BY p.name
    ");
    
    $params = array_merge([$tenant_id], $suppliers);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
