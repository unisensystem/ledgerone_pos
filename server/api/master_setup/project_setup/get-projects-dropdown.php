<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

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
