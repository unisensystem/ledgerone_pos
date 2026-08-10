<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';

try {
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    
    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    $query = "
        SELECT 
            ppr.id,
            ppr.tenant_id,
            ppr.project_id,
            ppr.product_id,
            ppr.rate,
            ppr.created_at,
            ppr.updated_at,
            p.project_code,
            p.project_name,
            pr.code as product_code,
            pr.name as product_name
        FROM project_product_rates ppr
        LEFT JOIN projects p ON ppr.project_id = p.id
        LEFT JOIN products pr ON ppr.product_id = pr.id
        WHERE ppr.tenant_id = ?
        ORDER BY p.project_code, pr.code
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $rates = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'rates' => $rates,
        'total' => count($rates)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
