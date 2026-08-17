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
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT fs.id, fs.station_name, fs.status, fs.installation_date, 
               fs.last_maintenance_date, b.branch_name, p.name as fuel_type
        FROM fueling_stations fs
        LEFT JOIN branches b ON fs.branch_id = b.id
        LEFT JOIN products p ON fs.fuel_type_id = p.id
        WHERE fs.tenant_id = ?
        ORDER BY fs.created_at DESC
    ");
    
    $stmt->execute([$tenant_id]);
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stations' => $stations]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}