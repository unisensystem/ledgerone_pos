<?php
session_start();
require_once __DIR__ . '/../../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, department_name 
    FROM departments 
    WHERE tenant_id = ? OR tenant_id = 0 
    ORDER BY department_name
");
$stmt->execute([$tenant_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $departments]);
