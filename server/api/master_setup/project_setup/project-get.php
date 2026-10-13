<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'message' => 'Project ID required']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.city_name, co.company_name,
            pm.full_name AS project_manager_name,
            tl.full_name AS team_leader_name
        FROM projects p
        LEFT JOIN cities c ON c.id = p.city_id
        LEFT JOIN employees pm ON pm.id = p.project_manager_id
        LEFT JOIN employees tl ON tl.id = p.team_leader_id
        LEFT JOIN companies co ON co.id = p.company_id
        WHERE p.id = ? AND p.tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);
    $project = $stmt->fetch();

    if (!$project) { echo json_encode(['success' => false, 'message' => 'Project not found']); exit; }

    // Get team members
    $stmt2 = $pdo->prepare("
        SELECT ptm.employee_id, e.full_name, e.employee_id AS emp_code
        FROM project_team_members ptm
        JOIN employees e ON e.id = ptm.employee_id
        WHERE ptm.project_id = ? AND ptm.tenant_id = ?
    ");
    $stmt2->execute([$id, $tenant_id]);
    $project['team_members'] = $stmt2->fetchAll();

    echo json_encode(['success' => true, 'data' => $project]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
