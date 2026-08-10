<?php
header('Content-Type: application/json');

session_start();
require_once '../../../../includes/connection.php';

try {
    $tenant_id = $_SESSION['tenant_id'] ?? null;
    
    if (!$tenant_id) {
        throw new Exception('Tenant ID not found in session');
    }

    $query = "SELECT id, project_code, project_name FROM projects WHERE tenant_id = ? ORDER BY project_code";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $projects = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
