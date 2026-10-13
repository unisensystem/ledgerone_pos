<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';

try {
    $id = $_GET['id'] ?? null;
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    
    if (!$id) {
        throw new Exception('Missing rate ID');
    }

    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    $query = "SELECT id, tenant_id, project_id, product_id, rate FROM project_product_rates WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id, $tenant_id]);
    $rate = $stmt->fetch();

    if (!$rate) {
        throw new Exception('Rate not found');
    }

    echo json_encode([
        'success' => true,
        'rate' => $rate
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
