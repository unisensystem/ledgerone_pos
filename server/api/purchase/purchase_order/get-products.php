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
            p.id, p.code, p.name, p.purchase_price, p.default_unit_id, p.barcode, p.qr_code, p.photo,
            p.default_discount, p.trade_offer_discount, p.default_foc, p.sales_tax, p.inventory_account_id,
            p.uom_type, p.uom_group_id, p.product_conversion_factor,
            u.uom_name as default_unit_name, u.unit_scope, u.is_base_unit, u.conversion_factor as unit_conversion_factor
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        WHERE p.tenant_id = ? AND p.is_active = 1 
        ORDER BY p.name
    ");
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For products with uom_group, fetch group units with conversion factors
    foreach ($products as &$product) {
        if ($product['uom_type'] === 'group' && $product['uom_group_id']) {
            $unitsStmt = $pdo->prepare("
                SELECT 
                    u.id, u.uom_name, u.unit_scope, u.is_base_unit, u.conversion_factor,
                    u.base_unit_id,
                    puc.conversion_factor as product_conversion_factor
                FROM uom_group_units ugu
                JOIN uom u ON ugu.uom_id = u.id
                LEFT JOIN product_uom_conversions puc ON puc.product_id = ? AND puc.uom_id = u.id
                WHERE ugu.uom_group_id = ?
                ORDER BY ugu.id
            ");
            $unitsStmt->execute([$product['id'], $product['uom_group_id']]);
            $product['group_units'] = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $product['group_units'] = [];
        }
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}