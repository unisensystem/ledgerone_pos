<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $employee_id = $data['id'] ?? null;
    
    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }
    
    // Verify employee exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT id, employee_id FROM employees WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$employee_id, $tenant_id]);
    $employee = $stmt->fetch();
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    
    // Delete related records first
    $stmt = $pdo->prepare("DELETE FROM leave_records WHERE employee_id = ?");
    $stmt->execute([$employee['employee_id']]);
    
    $stmt = $pdo->prepare("DELETE FROM suspension_records WHERE employee_id = ?");
    $stmt->execute([$employee['employee_id']]);
    
    $stmt = $pdo->prepare("DELETE FROM termination_records WHERE employee_id = ?");
    $stmt->execute([$employee['employee_id']]);
    
    // Delete opening balance entries from accounting_ledger
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'opening_balance' AND reference_table = 'employees' AND reference_id = ?");
    $stmt->execute([$tenant_id, $employee_id]);
    
    // Hard delete - permanently remove employee
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$employee_id, $tenant_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Employee deleted successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}