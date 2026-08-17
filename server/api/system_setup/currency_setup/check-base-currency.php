<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COUNT(CASE WHEN is_base_currency = 1 THEN 1 END) as base_count FROM tenant_currencies WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'is_first_currency' => $result['count'] == 0,
        'has_base_currency' => $result['base_count'] > 0
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}