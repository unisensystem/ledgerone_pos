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
    $priceSource = $_GET['priceSource'] ?? 'trade_price';
    $priceColumn = $priceSource === 'mrp' ? 'mrp' : 'trade_price';
    
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.code, 
            p.name, 
            {$priceColumn} as sale_price, 
            p.default_unit_id, 
            p.barcode, 
            p.qr_code, 
            p.photo, 
            p.carton_conversion, 
            p.default_discount, 
            p.trade_offer_discount, 
            p.sales_tax,
            p.uom_type,
            p.uom_group_id,
            p.product_conversion_factor,
            u.uom_name as default_unit_name,
            u.unit_scope as default_unit_scope,
            u.is_base_unit as default_is_base_unit,
            CASE
                WHEN u.unit_scope = 'per_product' AND puc.conversion_factor IS NOT NULL
                THEN puc.conversion_factor
                ELSE u.conversion_factor
            END as default_conversion_factor
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        LEFT JOIN product_uom_conversions puc ON puc.product_id = p.id AND puc.uom_id = p.default_unit_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_product_id IS NULL
        ORDER BY p.name
    ");
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get UOM group units for products with uom_type = 'group'
    foreach ($products as &$product) {
        if ($product['uom_type'] === 'group' && $product['uom_group_id']) {
            $uomStmt = $pdo->prepare("
                SELECT
                    u.id,
                    ugu.uom_id,
                    u.uom_name,
                    u.unit_scope,
                    u.is_base_unit,
                    CASE
                        WHEN u.unit_scope = 'per_product' AND puc.conversion_factor IS NOT NULL
                        THEN puc.conversion_factor
                        ELSE u.conversion_factor
                    END as conversion_factor
                FROM uom_group_units ugu
                JOIN uom u ON ugu.uom_id = u.id
                LEFT JOIN product_uom_conversions puc ON puc.uom_id = u.id AND puc.product_id = ?
                WHERE ugu.uom_group_id = ?
                ORDER BY ugu.id
            ");
            $uomStmt->execute([$product['id'], $product['uom_group_id']]);
            $product['group_units'] = $uomStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $product['group_units'] = [];
        }
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}