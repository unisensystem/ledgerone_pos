<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['user_id'] ?? null;
if (!$tenant_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['project_name']) || empty($input['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Project name and company are required']); exit;
}

try {
    // Auto-generate project code
    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM projects WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $next_num = $stmt->fetchColumn();
    $project_code = 'PRJ-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("INSERT INTO projects 
        (tenant_id, company_id, project_code, project_name, city_id, start_date, end_date, project_manager_id, team_leader_id, status, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $tenant_id,
        $input['company_id'],
        $project_code,
        $input['project_name'],
        $input['city_id'] ?: null,
        $input['start_date'] ?: null,
        $input['end_date'] ?: null,
        $input['project_manager_id'] ?: null,
        $input['team_leader_id'] ?: null,
        $input['status'] ?? 'active',
        $input['description'] ?? null
    ]);

    $project_id = $pdo->lastInsertId();

    // Insert team members
    if (!empty($input['team_members']) && is_array($input['team_members'])) {
        $ins = $pdo->prepare("INSERT IGNORE INTO project_team_members (project_id, employee_id, tenant_id) VALUES (?, ?, ?)");
        foreach ($input['team_members'] as $emp_id) {
            $ins->execute([$project_id, $emp_id, $tenant_id]);
        }
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Project', $project_id, $project_code,
        "Created project \"{$input['project_name']}\" ($project_code)",
        null,
        ['project_code' => $project_code, 'project_name' => $input['project_name'], 'company_id' => $input['company_id'], 'status' => $input['status'] ?? 'active']
    );

    echo json_encode(['success' => true, 'message' => 'Project created successfully', 'project_id' => $project_id, 'project_code' => $project_code]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}