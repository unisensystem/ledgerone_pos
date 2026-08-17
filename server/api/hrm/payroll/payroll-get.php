<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$payroll_id = $_GET['id'] ?? null;

if (!$payroll_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payroll ID is required']);
    exit;
}

try {
    // Get payroll entry
    $stmt = $pdo->prepare("SELECT * FROM payroll_entries WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$payroll_id, $tenant_id]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payroll) {
        throw new Exception('Payroll not found');
    }
    
    // Get payroll items
    $stmt = $pdo->prepare("
        SELECT pei.*, e.full_name as employee_name
        FROM payroll_entries_items pei
        LEFT JOIN employees e ON pei.employee_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci AND e.tenant_id = ?
        WHERE pei.payroll_id = ? AND pei.tenant_id = ?
    ");
    $stmt->execute([$tenant_id, $payroll_id, $tenant_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $payroll['items'] = $items;
    
    echo json_encode(['success' => true, 'payroll' => $payroll]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
