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
    SELECT e.employee_id, e.full_name, d.department_name, d.id as department_id, e.shift_timing
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.tenant_id = ? AND e.is_active = 1
    ORDER BY e.full_name
");
$stmt->execute([$tenant_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $employees]);
