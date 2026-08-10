<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['station_name']) || empty($input['branch_id'])) {
        throw new Exception('Station name and branch are required');
    }
    
    // Generate sequential station code
    $last_code_stmt = $pdo->prepare("SELECT station_code FROM fueling_stations WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $last_code_stmt->execute([$tenant_id]);
    $last_code = $last_code_stmt->fetchColumn();
    
    if ($last_code) {
        $last_number = (int)substr($last_code, 2);
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }
    
    $station_code = 'ST' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
    
    // Insert fuel station
    $stmt = $pdo->prepare("
        INSERT INTO fueling_stations (
            tenant_id, station_code, station_name, branch_id, fuel_type_id,
            manufacturer, model, serial_number, flow_rate, location_description,
            status, installation_date, last_maintenance_date, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $tenant_id,
        $station_code,
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
        $user_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Fuel station added successfully',
        'station_id' => $pdo->lastInsertId(),
        'station_code' => $station_code
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}