<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

try {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    
    if (!$tenant_id) {
        echo json_encode([
            'success' => false,
            'containers' => [],
            'message' => 'No tenant found'
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, container_name, container_code
        FROM containers
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY container_name ASC
    ");
    $stmt->execute([$tenant_id]);
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'containers' => $containers
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'containers' => [],
        'message' => $e->getMessage()
    ]);
}
?>
