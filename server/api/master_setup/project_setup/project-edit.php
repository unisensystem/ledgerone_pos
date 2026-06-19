<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id']) || empty($input['project_name']) || empty($input['start_date']) || empty($input['end_date'])) {
    echo json_encode(['success' => false, 'message' => 'ID, project name, start date and end date are required']); exit;
}

try {
    $stmt = $pdo->prepare("UPDATE projects SET
        project_name = ?, city_id = ?, start_date = ?, end_date = ?,
        project_manager_id = ?, team_leader_id = ?, status = ?, description = ?
        WHERE id = ? AND tenant_id = ?");
    $stmt->execute([
        $input['project_name'],
        $input['city_id'] ?: null,
        $input['start_date'],
        $input['end_date'],
        $input['project_manager_id'] ?: null,
        $input['team_leader_id'] ?: null,
        $input['status'] ?? 'active',
        $input['description'] ?? null,
        $input['id'],
        $tenant_id
    ]);

    // Sync team members: delete old, insert new
    $del = $pdo->prepare("DELETE FROM project_team_members WHERE project_id = ? AND tenant_id = ?");
    $del->execute([$input['id'], $tenant_id]);

    if (!empty($input['team_members']) && is_array($input['team_members'])) {
        $ins = $pdo->prepare("INSERT IGNORE INTO project_team_members (project_id, employee_id, tenant_id) VALUES (?, ?, ?)");
        foreach ($input['team_members'] as $emp_id) {
            $ins->execute([$input['id'], $emp_id, $tenant_id]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
