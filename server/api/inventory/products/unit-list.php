<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $type = $_GET['type'] ?? '';
    $base_only = $_GET['base_only'] ?? 0;
    
    $sql = "SELECT id, uom_name, uom_type, unit_scope, is_base_unit FROM uom WHERE tenant_id = ? OR tenant_id = 0";
    $params = [$tenant_id];
    
    if ($type) {
        $sql .= " AND uom_type = ?";
        $params[] = $type;
    }
    
    if ($base_only) {
        $sql .= " AND is_base_unit = 1";
    }
    
    $sql .= " ORDER BY uom_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'units' => $units]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}