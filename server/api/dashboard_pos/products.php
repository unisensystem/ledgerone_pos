<?php
// server/api/dashboard_pos/products.php
// Tenant ke active, sellable products (price = MRP, MRP na ho to Trade Price) + simple stock.
// Har product ke saath `units`: [{id,name,factor,base}] - factor = kitne BASE unit ka ek ye unit.
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
               u.base_unit_id AS u_base_id, b.uom_name AS b_name,
               (SELECT puc.conversion_factor FROM product_uom_conversions puc
                 WHERE puc.product_id = p.id AND puc.uom_id = p.default_unit_id LIMIT 1) AS puc_factor
        FROM products p
        LEFT JOIN uom u ON u.id = p.default_unit_id
        LEFT JOIN uom b ON b.id = u.base_unit_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.invoice_affects = 1
        ORDER BY p.name
    ");
    $s->execute([$tenant_id]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);

    // Fallback base unit (Piece / Pcs) - sirf tab kaam aata hai jab Carton ka base_unit_id set na ho
    $fallbackPiece = null;
    try {
        $fp = $pdo->query("SELECT id, uom_name FROM uom
                           WHERE is_base_unit = 1 AND (uom_name LIKE '%piece%' OR uom_name LIKE 'pc%')
                           ORDER BY id LIMIT 1");
        $fallbackPiece = $fp ? $fp->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Exception $e) { $fallbackPiece = null; }

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

    // POS quantities base unit mein dikhti hain, isliye har ledger row ko base mein convert karke sum karte hain.
    $stock = [];
    $sql = "
        SELECT sl.product_id,
               SUM((COALESCE(sl.qty_in, 0) - COALESCE(sl.qty_out, 0)) *
                   CASE
                       WHEN u.is_base_unit = 1 THEN 1
                       WHEN u.unit_scope = 'per_product' THEN COALESCE(NULLIF(puc.conversion_factor, 0), NULLIF(p.product_conversion_factor, 0), 1)
                       WHEN u.unit_scope = 'universal' THEN COALESCE(NULLIF(u.conversion_factor, 0), 1)
                       ELSE 1
                   END) AS q
        FROM stock_ledger sl
        INNER JOIN products p ON p.id = sl.product_id AND p.tenant_id = sl.tenant_id
        LEFT JOIN uom u ON u.id = sl.unit_id
        LEFT JOIN product_uom_conversions puc ON puc.product_id = sl.product_id AND puc.uom_id = sl.unit_id
        WHERE sl.tenant_id = ?
    ";
    $args = [$tenant_id];
    if ($branch_id) { $sql .= " AND sl.branch_id = ?"; $args[] = $branch_id; }
    $sql .= " GROUP BY sl.product_id";
    $s = $pdo->prepare($sql);
    $s->execute($args);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $stock[$r['product_id']] = (float) $r['q'];

    $out = [];
    foreach ($rows as $r) {
        $price = (float) $r['mrp'] > 0 ? (float) $r['mrp'] : (float) $r['trade_price'];

        // Default-unit product: agar default unit Carton jaisi non-base unit hai to
        // [Base (factor 1), Carton (factor N)] banao. Price base unit ka hi rehta hai.
        $units = [];
        $barcodeUnitId = $r['default_unit_id'] ? (int) $r['default_unit_id'] : null;
        $unitId = $r['default_unit_id'] ? (int) $r['default_unit_id'] : null;
        $unitName = $r['uom_name'];

        if ($unitId) {
            if ((int) $r['u_is_base'] !== 1) {
                // Factor: per_product => product ka apna factor, warna universal factor
                if ($r['u_scope'] === 'per_product') {
                    $f = (float) $r['product_conversion_factor'];
                    if ($f <= 0) $f = (float) $r['puc_factor'];
                } else {
                    $f = (float) $r['u_conv'];
                }

                // Base unit: pehle unit ka apna base_unit_id, warna fallback Piece
                $baseId = $r['u_base_id'] ? (int) $r['u_base_id'] : ($fallbackPiece ? (int) $fallbackPiece['id'] : 0);
                $baseName = $r['u_base_id'] ? $r['b_name'] : ($fallbackPiece ? $fallbackPiece['uom_name'] : '');

                if ($f > 0 && $baseId) {
                    $units[] = ['id' => $baseId, 'name' => $baseName, 'factor' => 1, 'base' => true];
                    $units[] = ['id' => $unitId, 'name' => $r['uom_name'], 'factor' => $f, 'base' => false];
                    $unitId = $baseId;
                    $unitName = $baseName;
                }
            }
            if (!$units) $units[] = ['id' => $unitId, 'name' => $unitName, 'factor' => 1, 'base' => true];
        }

        $out[] = [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'barcode' => $r['barcode'],
            'category_id' => $r['category_id'] ? (int) $r['category_id'] : null,
            'photo' => $r['photo'], 'price' => $price,
            'unit_id' => $unitId, 'barcode_unit_id' => $barcodeUnitId,
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