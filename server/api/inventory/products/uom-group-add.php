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
    $group_name = $_POST['groupName'] ?? '';
    $unit_ids = $_POST['unitIds'] ?? [];
    
    if (empty($group_name)) {
        echo json_encode(['success' => false, 'message' => 'Group name is required']);
        exit;
    }
    
    if (empty($unit_ids) || !is_array($unit_ids)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one unit']);
        exit;
    }
    
    // Insert UOM group
    $stmt = $pdo->prepare("INSERT INTO uom_groups (tenant_id, group_name) VALUES (?, ?)");
    $stmt->execute([$tenant_id, $group_name]);
    $group_id = $pdo->lastInsertId();
    
    // Insert group units
    $stmt = $pdo->prepare("INSERT INTO uom_group_units (uom_group_id, uom_id) VALUES (?, ?)");
    foreach ($unit_ids as $unit_id) {
        $stmt->execute([$group_id, $unit_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'UOM Group added successfully',
        'group' => [
            'id' => $group_id,
            'name' => $group_name
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
