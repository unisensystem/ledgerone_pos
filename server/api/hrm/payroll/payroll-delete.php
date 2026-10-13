<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    
    // Grab full row before deletion, for the Activity Log
    $stOld = $pdo->prepare("SELECT * FROM payroll_entries WHERE tenant_id = ? AND id = ?");
    $stOld->execute([$tenant_id, $payroll_id]);
    $payrollRow = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$payrollRow) {
        throw new Exception('Payroll entry not found');
    }
    if ($payrollRow['status'] === 'finalized' && empty($data['acknowledge_finalized'])) {
        throw new Exception("This payroll (\"{$payrollRow['payroll_code']}\") is finalized and locked. Resubmit with acknowledge_finalized=true to force delete.");
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
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Payroll', $payroll_id, $payrollRow['payroll_code'],
        "Deleted payroll entry \"{$payrollRow['payroll_code']}\", total " . number_format($payrollRow['total_amount'], 2),
        $payrollRow, null
    );

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Payroll deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}