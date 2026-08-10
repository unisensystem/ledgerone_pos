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

$product_id = $_GET['product_id'] ?? null;
$branch_id = $_GET['branch_id'] ?? null;
$unit_id = $_GET['unit_id'] ?? null;
$station_id = $_GET['station_id'] ?? null;

if (!$product_id || !$branch_id || !$unit_id || !$station_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT usage_date, opening_reading, closing_reading, rate
        FROM station_daily_usage 
        WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND unit_id = ? AND station_id = ?
        ORDER BY usage_date DESC
        LIMIT 30
    ");
    $stmt->execute([$tenant_id, $product_id, $branch_id, $unit_id, $station_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
