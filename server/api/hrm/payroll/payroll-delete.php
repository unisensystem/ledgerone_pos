<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $payroll_id = $data['id'] ?? null;
    
    if (!$payroll_id) {
        throw new Exception('Payroll ID is required');
    }
    
    $pdo->beginTransaction();
    
    // Delete accounting ledger entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'payroll' AND reference_table = 'payroll_entries' AND reference_id = ?");
    $stmt->execute([$tenant_id, $payroll_id]);
    
    // Delete payroll items
    $stmt = $pdo->prepare("DELETE FROM payroll_entries_items WHERE tenant_id = ? AND payroll_id = ?");
    $stmt->execute([$tenant_id, $payroll_id]);
    
    // Delete payroll entry
    $stmt = $pdo->prepare("DELETE FROM payroll_entries WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$tenant_id, $payroll_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payroll deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}