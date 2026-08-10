<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['error' => 'No tenant_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cost_adjustments WHERE tenant_id = ? ORDER BY adjustment_date DESC LIMIT 10");
    $stmt->execute([$tenant_id]);
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'adjustments' => $adjustments,
        'count' => count($adjustments)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
