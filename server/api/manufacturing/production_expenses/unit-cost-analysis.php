<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $search = $_GET['search'] ?? '';
    
    $sql = "
        SELECT 
            p.id as product_id,
            p.name as product_name,
            p.code as sku_code,
            u.uom_name,
            SUM(cp.completed_qty) as total_qty_produced,
            SUM(cp.total_cost) as total_cost,
            SUM(cp.total_cost) / NULLIF(SUM(cp.completed_qty), 0) as avg_unit_cost,
            SUM(cp.material_cost * cp.completed_qty) / NULLIF(SUM(cp.completed_qty), 0) as avg_material_cost,
            SUM(cp.overhead_cost * cp.completed_qty) / NULLIF(SUM(cp.completed_qty), 0) as avg_overhead_cost,
            COUNT(DISTINCT po.id) as batch_count,
            MAX(pc.complete_date) as last_production_date
        FROM completed_products cp
        INNER JOIN production_completions pc ON cp.production_completion_id = pc.id
        INNER JOIN production_orders po ON pc.production_order_id = po.id
        INNER JOIN products p ON cp.product_id = p.id
        LEFT JOIN uom u ON cp.uom_id = u.id
        WHERE pc.tenant_id = ?
    ";
    
    $params = [$tenant_id];
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " GROUP BY p.id, p.name, p.code, u.uom_name
              ORDER BY p.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$row) {
        $row['avg_unit_cost'] = $row['avg_unit_cost'] ?? 0;
        $row['avg_material_cost'] = $row['avg_material_cost'] ?? 0;
        $row['avg_overhead_cost'] = $row['avg_overhead_cost'] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
