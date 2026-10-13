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
    $branch_id = $_GET['branch_id'] ?? null;
    
    if ($branch_id) {
        $stmt = $pdo->prepare("
            SELECT fs.id, fs.station_code, fs.station_name, fs.branch_id, fs.fuel_type_id,
                   b.branch_code, b.branch_name, b.branch_type,
                   p.code as product_code, p.name as product_name
            FROM fueling_stations fs
            LEFT JOIN branches b ON fs.branch_id = b.id
            LEFT JOIN products p ON fs.fuel_type_id = p.id
            WHERE fs.tenant_id = ? AND fs.branch_id = ? AND fs.status = 'active' 
            ORDER BY fs.station_name
        ");
        $stmt->execute([$tenant_id, $branch_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT fs.id, fs.station_code, fs.station_name, fs.branch_id, fs.fuel_type_id,
                   b.branch_code, b.branch_name, b.branch_type,
                   p.code as product_code, p.name as product_name
            FROM fueling_stations fs
            LEFT JOIN branches b ON fs.branch_id = b.id
            LEFT JOIN products p ON fs.fuel_type_id = p.id
            WHERE fs.tenant_id = ? AND fs.status = 'active' 
            ORDER BY fs.station_name
        ");
        $stmt->execute([$tenant_id]);
    }
    
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stations' => $stations]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}