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

    $sql = "
        SELECT 
            p.id, p.code, p.name, p.mrp as sale_price, p.trade_price, 
            p.default_unit_id, p.barcode, p.qr_code, p.photo, p.carton_conversion,
            p.uom_type, p.uom_group_id,
            u.uom_name as default_unit_name,
            CASE WHEN p.uom_type = 'group' AND p.uom_group_id IS NOT NULL THEN 1 ELSE 0 END as has_group
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        WHERE p.tenant_id = ? AND p.is_active = 1
    ";
    $params = [$tenant_id];
    if ($company_id) { $sql .= " AND p.company_id = ?"; $params[] = $company_id; }
    $sql .= " ORDER BY p.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load UOM group units for products with groups
    foreach ($products as &$product) {
        if ($product['uom_type'] === 'group' && $product['uom_group_id']) {
            // Get group units
            $groupStmt = $pdo->prepare("
                SELECT 
                    ugu.id, ugu.uom_id, u.is_base_unit, u.conversion_factor,
                    u.uom_name
                FROM uom_group_units ugu
                JOIN uom u ON ugu.uom_id = u.id
                WHERE ugu.uom_group_id = ?
                ORDER BY u.is_base_unit DESC, ugu.id
            ");
            $groupStmt->execute([$product['uom_group_id']]);
            $groupUnits = $groupStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check for per-product conversions
            foreach ($groupUnits as &$unit) {
                $convStmt = $pdo->prepare("
                    SELECT conversion_factor 
                    FROM product_uom_conversions 
                    WHERE product_id = ? AND uom_id = ?
                ");
                $convStmt->execute([$product['id'], $unit['uom_id']]);
                $perProductConv = $convStmt->fetchColumn();
                
                if ($perProductConv !== false) {
                    $unit['conversion_factor'] = $perProductConv;
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