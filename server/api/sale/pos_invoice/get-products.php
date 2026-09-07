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

    $sql = "
        SELECT
            p.id, p.code, p.name, p.mrp, p.trade_price, p.wholesale_price, p.default_unit_id,
            p.barcode, p.qr_code, p.photo, p.carton_conversion, p.stock_affects,
            p.invoice_affects, p.parent_product_id, p.default_discount,
            p.trade_offer_discount, p.sales_tax, p.sales_tax_type,
            p.uom_type, p.uom_group_id, p.product_conversion_factor,
            p.price_entry_unit_id, p.trade_price_entry_value, p.mrp_entry_value,
            COALESCE(puc.conversion_factor, peu.conversion_factor) as price_entry_unit_factor,
            u.uom_name as default_unit_name, u.unit_scope as default_unit_scope,
            u.is_base_unit as default_is_base_unit, u.conversion_factor as default_conversion_factor
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        LEFT JOIN uom peu ON p.price_entry_unit_id = peu.id
        LEFT JOIN product_uom_conversions puc ON puc.product_id = p.id AND puc.uom_id = p.price_entry_unit_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.parent_product_id IS NULL
    ";
    $params = [$tenant_id];

    if ($company_id) {
        $sql .= " AND p.company_id = ?";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY p.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}