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
        SELECT closing_reading 
        FROM station_daily_usage 
        WHERE tenant_id = ? AND station_id = ? AND closing_reading IS NOT NULL
        ORDER BY usage_date DESC, id DESC 
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $station_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $lastReading = $result ? $result['closing_reading'] : 0;
    
    echo json_encode(['success' => true, 'last_reading' => $lastReading]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>