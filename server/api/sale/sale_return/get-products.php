<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$tenant_id = $auth['tenant_id'];

try {
    $sql = "
        SELECT
            p.id, p.code, p.name, p.mrp as sale_price, p.trade_price,
            p.default_unit_id, p.barcode, p.qr_code, p.photo, p.carton_conversion,
            p.uom_type, p.uom_group_id, p.product_conversion_factor,
            u.uom_name as default_unit_name,
            u.unit_scope AS u_scope, u.is_base_unit AS u_is_base, u.conversion_factor AS u_conv,
            u.base_unit_id AS u_base_id, bu.uom_name AS b_name,
            (SELECT puc.conversion_factor FROM product_uom_conversions puc
              WHERE puc.product_id = p.id AND puc.uom_id = p.default_unit_id LIMIT 1) AS puc_factor,
            CASE WHEN p.uom_type = 'group' AND p.uom_group_id IS NOT NULL THEN 1 ELSE 0 END as has_group
        FROM products p
        LEFT JOIN uom u ON p.default_unit_id = u.id
        LEFT JOIN uom bu ON bu.id = u.base_unit_id
        WHERE p.tenant_id = ? AND p.is_active = 1
    ";
    $params = [$tenant_id];
    $sql .= " ORDER BY p.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback base unit (Piece) - used only when the default unit (e.g. Carton) has no base_unit_id
    $fallbackPiece = null;
    try {
        $fp = $pdo->query("SELECT id, uom_name FROM uom
                           WHERE is_base_unit = 1 AND (uom_name LIKE '%piece%' OR uom_name LIKE 'pc%')
                           ORDER BY id LIMIT 1");
        $fallbackPiece = $fp ? $fp->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Exception $e) { $fallbackPiece = null; }

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

        // Non-group product whose default unit is bigger than the base unit (e.g. Carton):
        // send [Carton (factor N), Piece (factor 1)] so both can be entered / loaded from a POS bill.
        // Same factor logic as server/api/dashboard_pos/products.php.
        $product['units'] = [];
        if (empty($product['group_units']) && $product['default_unit_id'] && (int) $product['u_is_base'] !== 1) {
            if ($product['u_scope'] === 'per_product') {
                $f = (float) $product['product_conversion_factor'];
                if ($f <= 0) $f = (float) $product['puc_factor'];
            } else {
                $f = (float) $product['u_conv'];
            }
            $baseId = $product['u_base_id'] ? (int) $product['u_base_id'] : ($fallbackPiece ? (int) $fallbackPiece['id'] : 0);
            $baseName = $product['u_base_id'] ? $product['b_name'] : ($fallbackPiece ? $fallbackPiece['uom_name'] : '');
            if ($f > 0 && $baseId) {
                $product['units'] = [
                    ['id' => (int) $product['default_unit_id'], 'name' => $product['default_unit_name'], 'factor' => $f, 'base' => false],
                    ['id' => $baseId, 'name' => $baseName, 'factor' => 1, 'base' => true]
                ];
            }
        }
        unset($product['u_scope'], $product['u_is_base'], $product['u_conv'], $product['u_base_id'], $product['b_name'], $product['puc_factor']);
    }
    unset($product);
    
    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}