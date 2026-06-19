<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT p.*,
            c.city_name,
            pm.full_name AS project_manager_name,
            tl.full_name AS team_leader_name,
            co.company_name,
            (SELECT COUNT(*) FROM project_team_members ptm WHERE ptm.project_id = p.id) AS team_count
        FROM projects p
        LEFT JOIN cities c ON c.id = p.city_id
        LEFT JOIN employees pm ON pm.id = p.project_manager_id
        LEFT JOIN employees tl ON tl.id = p.team_leader_id
        LEFT JOIN companies co ON co.id = p.company_id
        WHERE p.tenant_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$tenant_id]);
    $projects = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $projects]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
