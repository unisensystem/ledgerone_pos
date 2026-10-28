<?php
// server/api/dashboard_pos/products.php
// Tenant ke active, sellable products (price = MRP, MRP na ho to Trade Price) + simple stock.
// Har product ke saath `units` bhi: [{id,name,factor,base}] - factor = kitne BASE unit ka ek ye unit.
// Price hamesha BASE unit (Piece / Kg) ka hota hai. UOM-Group wale products ki units client group API se leta hai.
require_once '../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

try {
    $branch_id = (int) ($_GET['branch_id'] ?? 0);

    $s = $pdo->prepare("
        SELECT p.id, p.code, p.name, p.barcode, p.category_id, p.photo, p.mrp, p.trade_price,
               p.default_unit_id, p.uom_type, p.uom_group_id, p.stock_affects, p.parent_product_id,
               p.product_type, p.product_conversion_factor,
               u.uom_name, u.unit_scope AS u_scope, u.is_base_unit AS u_is_base, u.conversion_factor AS u_conv,
               u.base_unit_id AS u_base_id, b.uom_name AS b_name
        FROM products p
        LEFT JOIN uom u ON u.id = p.default_unit_id
        LEFT JOIN uom b ON b.id = u.base_unit_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.invoice_affects = 1
        ORDER BY p.name
    ");
    $s->execute([$tenant_id]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);

    $subUnitsByProduct = [];
    $subUnitStmt = $pdo->prepare("
        SELECT product_id, id, label, qty_in_base, price, barcode
        FROM product_sub_units
        WHERE tenant_id = ?
        ORDER BY sort_order, id
    ");
    $subUnitStmt->execute([$tenant_id]);
    foreach ($subUnitStmt->fetchAll(PDO::FETCH_ASSOC) as $subUnit) {
        $subUnitsByProduct[$subUnit['product_id']][] = [
            'id' => (int) $subUnit['id'],
            'label' => $subUnit['label'],
            'qty_in_base' => (float) $subUnit['qty_in_base'],
            'price' => (float) $subUnit['price'],
            'barcode' => $subUnit['barcode']
        ];
    }

    // Simple stock = SUM(qty_in - qty_out) for the branch (raw ledger units; exact multi-UOM
    // stock still comes from get-product-stock.php, which is used at the counter invoice).
    $stock = [];
    $sql = "SELECT product_id, SUM(COALESCE(qty_in,0) - COALESCE(qty_out,0)) AS q FROM stock_ledger WHERE tenant_id = ?";
    $args = [$tenant_id];
    if ($branch_id) { $sql .= " AND branch_id = ?"; $args[] = $branch_id; }
    $sql .= " GROUP BY product_id";
    $s = $pdo->prepare($sql);
    $s->execute($args);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $stock[$r['product_id']] = (float) $r['q'];

    $out = [];
    foreach ($rows as $r) {
        $price = (float) $r['mrp'] > 0 ? (float) $r['mrp'] : (float) $r['trade_price'];

        // Default-unit products: agar default unit Carton jaisi non-base unit hai to
        // [Base (factor 1), Carton (factor N)] banao. Price base unit ka hi rehta hai.
        $units = [];
        $unitId = $r['default_unit_id'] ? (int) $r['default_unit_id'] : null;
        $unitName = $r['uom_name'];
        if ($unitId) {
            if ((int) $r['u_is_base'] !== 1 && $r['u_base_id']) {
                $f = $r['u_scope'] === 'per_product' ? (float) $r['product_conversion_factor'] : (float) $r['u_conv'];
                if ($f > 0) {
                    $units[] = ['id' => (int) $r['u_base_id'], 'name' => $r['b_name'], 'factor' => 1, 'base' => true];
                    $units[] = ['id' => $unitId, 'name' => $r['uom_name'], 'factor' => $f, 'base' => false];
                    $unitId = (int) $r['u_base_id'];
                    $unitName = $r['b_name'];
                }
            }
            if (!$units) $units[] = ['id' => $unitId, 'name' => $unitName, 'factor' => 1, 'base' => true];
        }

        $out[] = [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'barcode' => $r['barcode'],
            'category_id' => $r['category_id'] ? (int) $r['category_id'] : null,
            'photo' => $r['photo'], 'price' => $price,
            'unit_id' => $unitId,
            'unit' => $unitName, 'uom_group_id' => $r['uom_group_id'] ? (int) $r['uom_group_id'] : null,
            'units' => $units,
            'loose_units' => $subUnitsByProduct[$r['id']] ?? [],
            'stock_affects' => (int) $r['stock_affects'],
            'stock' => ((int) $r['stock_affects'] === 1 && $r['product_type'] !== 'service') ? ($stock[$r['id']] ?? 0) : null
        ];
    }
    echo json_encode(['success' => true, 'products' => $out]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}