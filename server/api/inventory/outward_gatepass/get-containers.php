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
            'data' => [],
            'message' => 'No tenant found'
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, container_code, container_name
        FROM containers
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY container_name ASC
    ");
    $stmt->execute([$tenant_id]);
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $containers
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => $e->getMessage()
    ]);
}
?>
