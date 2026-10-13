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
$station_id = $_GET['station_id'] ?? null;

if (!$user_id || !$tenant_id || !$station_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.branch_code, b.branch_name, b.branch_type 
        FROM branches b 
        INNER JOIN fueling_stations fs ON b.id = fs.branch_id 
        WHERE fs.id = ? AND fs.tenant_id = ? AND b.tenant_id = ? AND b.is_active = 1
    ");
    $stmt->execute([$station_id, $tenant_id, $tenant_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $branch]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>