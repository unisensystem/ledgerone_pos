<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
$tenant_id = $auth['tenant_id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.project_code, p.project_name, c.city_name
        FROM projects p
        LEFT JOIN cities c ON c.id = p.city_id
        WHERE p.tenant_id = ? AND p.status = 'active'
        ORDER BY p.project_name ASC
    ");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true, 'projects' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
