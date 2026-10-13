<?php
require_once '../../../../includes/connection.php';
session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Check all projects regardless of status
try {
    $stmt = $pdo->prepare("SELECT id, project_code, project_name, status, tenant_id FROM projects WHERE tenant_id = ? LIMIT 20");
    $stmt->execute([$tenant_id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT id, project_code, project_name, status FROM projects LIMIT 20");
    $stmt2->execute();
    $allNoTenant = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'session_tenant_id' => $tenant_id,
        'session_user_id' => $user_id,
        'projects_for_tenant' => $all,
        'all_projects_in_db' => $allNoTenant
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
