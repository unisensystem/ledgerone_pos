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
    
    $payroll_id = $data['payroll_id'] ?? null;
    $payroll_date = $data['payroll_date'] ?? null;
    $payroll_code = $data['payroll_code'] ?? null;
    $items = $data['items'] ?? [];
    
    if (!$payroll_id || !$payroll_date || !$payroll_code || empty($items)) {
        throw new Exception('Missing required fields');
    }
    
    $pdo->beginTransaction();
    
    // Calculate total amount
    $total_amount = array_sum(array_column($items, 'amount'));
    
    // Update payroll entry
    $stmt = $pdo->prepare("
        UPDATE payroll_entries 
        SET payroll_date = ?, total_amount = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$payroll_date, $total_amount, $payroll_id, $tenant_id]);
    
    // Delete old accounting entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE tenant_id = ? AND transaction_type = 'payroll' AND reference_table = 'payroll_entries' AND reference_id = ?");
    $stmt->execute([$tenant_id, $payroll_id]);
    
    // Delete old payroll items
    $stmt = $pdo->prepare("DELETE FROM payroll_entries_items WHERE tenant_id = ? AND payroll_id = ?");
    $stmt->execute([$tenant_id, $payroll_id]);
    
    // Insert new payroll items
    $stmt = $pdo->prepare("
        INSERT INTO payroll_entries_items 
        (tenant_id, payroll_id, employee_id, type, payment_method_id, bank_account_id, cheque_date, description, amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $stmt->execute([
            $tenant_id,
            $payroll_id,
            $item['employee_code'],
            $item['type'],
            $item['payment_method_id'],
            $item['bank_account_id'] ?? null,
            $item['cheque_date'] ?? null,
            $item['description'] ?? null,
            $item['amount']
        ]);
    }
    
    // Create new accounting entries
    $stmt = $pdo->prepare("
        INSERT INTO accounting_ledger 
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit)
        VALUES (?, 'payroll', 'payroll_entries', ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items as $item) {
        $description = $item['type'] . ' - ' . $item['employee_code'];
        
        // Get payment account_id
        if ($item['payment_method_id'] == 7) {
            $payment_account_id = 1; // Cash
        } else {
            $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
            $bankStmt->execute([$item['bank_account_id'], $tenant_id]);
            $payment_account_id = $bankStmt->fetchColumn() ?: 1;
        }
        
        // Determine accounts based on type
        if (in_array($item['type'], ['Advance', 'Advance Return'])) {
            if ($item['type'] === 'Advance') {
                $stmt->execute([$tenant_id, $payroll_id, 31, $payroll_date, $description, $item['amount'], 0]);
                $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, 0, $item['amount']]);
            } else {
                $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, $item['amount'], 0]);
                $stmt->execute([$tenant_id, $payroll_id, 31, $payroll_date, $description, 0, $item['amount']]);
            }
        } else {
            $stmt->execute([$tenant_id, $payroll_id, 28, $payroll_date, $description, $item['amount'], 0]);
            $stmt->execute([$tenant_id, $payroll_id, $payment_account_id, $payroll_date, $description, 0, $item['amount']]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payroll entry updated successfully',
        'payroll_id' => $payroll_id,
        'payroll_code' => $payroll_code
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}