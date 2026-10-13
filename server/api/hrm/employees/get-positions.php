<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $department_id = $_GET['department_id'] ?? null;
    
    if (!$department_id) {
        throw new Exception('Department ID is required');
    }
    
    $stmt = $pdo->prepare("
        SELECT id, position_title 
        FROM positions 
        WHERE (tenant_id = 0 OR tenant_id = ?) 
        AND (department_id = ? OR department_id IS NULL)
        AND is_active = 1 
        ORDER BY position_title
    ");
    $stmt->execute([$tenant_id, $department_id]);
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'positions' => $positions
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
