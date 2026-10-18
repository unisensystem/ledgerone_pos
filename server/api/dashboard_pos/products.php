<?php
// server/api/dashboard_pos/products.php
// Tenant ke active, sellable products (price = MRP, MRP na ho to Trade Price) + simple stock.
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
               p.product_type, u.uom_name
        FROM products p
        LEFT JOIN uom u ON u.id = p.default_unit_id
        WHERE p.tenant_id = ? AND p.is_active = 1 AND p.invoice_affects = 1
        ORDER BY p.name
    ");
    $s->execute([$tenant_id]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);

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
        $out[] = [
            'id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'barcode' => $r['barcode'],
            'category_id' => $r['category_id'] ? (int) $r['category_id'] : null,
            'photo' => $r['photo'], 'price' => $price,
            'unit_id' => $r['default_unit_id'] ? (int) $r['default_unit_id'] : null,
            'unit' => $r['uom_name'], 'uom_group_id' => $r['uom_group_id'] ? (int) $r['uom_group_id'] : null,
            'stock_affects' => (int) $r['stock_affects'],
            'stock' => ((int) $r['stock_affects'] === 1 && $r['product_type'] !== 'service') ? ($stock[$r['id']] ?? 0) : null
        ];
    }
    echo json_encode(['success' => true, 'products' => $out]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}