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

try {
    $sql = "SELECT pe.id, pe.payroll_code, pe.payroll_date, pe.total_amount,
                   pei.employee_id, e.full_name as employee_name,
                   pei.type, pei.amount,
                   a.name as payment_method
            FROM payroll_entries pe
            JOIN payroll_entries_items pei ON pe.id = pei.payroll_id
            LEFT JOIN employees e ON pei.employee_id COLLATE utf8mb4_unicode_ci = e.employee_id COLLATE utf8mb4_unicode_ci AND e.tenant_id = ?
            LEFT JOIN accounts a ON pei.payment_method_id = a.id
            WHERE pe.tenant_id = ?
            ORDER BY pe.payroll_date DESC, pe.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $tenant_id]);
    $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'payrolls' => $payrolls]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
