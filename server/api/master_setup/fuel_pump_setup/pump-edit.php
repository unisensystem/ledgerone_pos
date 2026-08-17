<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get station details
    $station_id = $_GET['id'] ?? null;
    if (!$station_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Station ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM fueling_stations WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$station_id, $tenant_id]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$station) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Station not found']);
            exit;
        }
        
        echo json_encode(['success' => true, 'station' => $station]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update station
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id']) || empty($input['station_name'])) {
            throw new Exception('Station ID and name are required');
        }
        
        $stmt = $pdo->prepare("
            UPDATE fueling_stations SET 
                station_name = ?, branch_id = ?, fuel_type_id = ?, manufacturer = ?, model = ?, 
                serial_number = ?, flow_rate = ?, location_description = ?, 
                status = ?, installation_date = ?, last_maintenance_date = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([
            $input['station_name'],
            $input['branch_id'],
            $input['fuel_type_id'] ?? null,
            $input['manufacturer'] ?? null,
            $input['model'] ?? null,
            $input['serial_number'] ?? null,
            $input['flow_rate'] ?? null,
            $input['location_description'] ?? null,
            $input['status'] ?? 'active',
            $input['installation_date'] ?? null,
            $input['last_maintenance_date'] ?? null,
            $user_id,
            $input['id'],
            $tenant_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Station updated successfully']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}