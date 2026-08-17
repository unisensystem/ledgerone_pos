<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';

try {
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    
    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    $query = "SELECT id, code, name FROM products WHERE is_active = 1 AND tenant_id = ? ORDER BY code";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
