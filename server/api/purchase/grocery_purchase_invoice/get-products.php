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
            p.id, p.code, p.name, p.purchase_price, p.default_unit_id, p.barcode, p.qr_code, p.photo,
            p.carton_conversion, p.product_conversion_factor, p.default_discount, p.trade_offer_discount, p.default_foc, p.sales_tax,
            p.inventory_account_id, p.uom_type, p.uom_group_id,
            p.price_entry_unit_id, p.purchase_price_entry_value,
            u.uom_name as default_unit_name, u.unit_scope, u.is_base_unit, u.conversion_factor as unit_conversion_factor
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
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
    
    // Get UOM group units for products with uom_type = 'group'
    foreach ($products as &$product) {
        if ($product['uom_type'] === 'group' && $product['uom_group_id']) {
            $groupStmt = $pdo->prepare("
                SELECT u.id, u.uom_name, u.unit_scope, u.is_base_unit, u.conversion_factor
                FROM uom_group_units ugu
                JOIN uom u ON ugu.uom_id = u.id
                WHERE ugu.uom_group_id = ?
                ORDER BY ugu.id
            ");
            $groupStmt->execute([$product['uom_group_id']]);
            $groupUnits = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get per-product conversion factors
            foreach ($groupUnits as &$unit) {
                if ($unit['unit_scope'] === 'per_product') {
                    $convStmt = $pdo->prepare("
                        SELECT conversion_factor 
                        FROM product_uom_conversions 
                        WHERE product_id = ? AND uom_id = ?
                    ");
                    $convStmt->execute([$product['id'], $unit['id']]);
                    $convResult = $convStmt->fetch(PDO::FETCH_ASSOC);
                    if ($convResult) {
                        $unit['conversion_factor'] = $convResult['conversion_factor'];
                    }
                }
            }
            $product['group_units'] = $groupUnits;
        } else {
            $product['group_units'] = [];
        }
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}