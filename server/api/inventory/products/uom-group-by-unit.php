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
    $unit_id = $_GET['unit_id'] ?? '';
    
    if (empty($unit_id)) {
        // Return all groups with their units
        $stmt = $pdo->prepare("
            SELECT ug.id, ug.group_name, GROUP_CONCAT(ugu.uom_id) as unit_ids
            FROM uom_groups ug
            LEFT JOIN uom_group_units ugu ON ug.id = ugu.uom_group_id
            WHERE ug.tenant_id = ? OR ug.tenant_id = 0
            GROUP BY ug.id
            ORDER BY ug.group_name
        ");
        $stmt->execute([$tenant_id]);
    } else {
        // Return only groups that contain the specified unit
        $stmt = $pdo->prepare("
            SELECT ug.id, ug.group_name, GROUP_CONCAT(ugu.uom_id) as unit_ids
            FROM uom_groups ug
            INNER JOIN uom_group_units ugu ON ug.id = ugu.uom_group_id
            WHERE (ug.tenant_id = ? OR ug.tenant_id = 0) AND ugu.uom_id = ?
            GROUP BY ug.id
            ORDER BY ug.group_name
        ");
        $stmt->execute([$tenant_id, $unit_id]);
    }
    
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert unit_ids to array
    foreach ($groups as &$group) {
        $group['unit_ids'] = $group['unit_ids'] ? explode(',', $group['unit_ids']) : [];
    }
    
    echo json_encode(['success' => true, 'groups' => $groups]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
