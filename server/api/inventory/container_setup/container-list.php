<?php
header('Content-Type: application/json');
require_once '../../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $query = "SELECT * FROM containers WHERE tenant_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $containers = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'containers' => $containers,
        'tenant_id' => $tenant_id,
        'total' => count($containers)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching containers: ' . $e->getMessage()
    ]);
}
?>
