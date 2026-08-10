<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['allocation_method']) || !isset($input['entry_date']) || !isset($input['lines'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $allocation_method = $input['allocation_method'];
    $entry_date = $input['entry_date'];
    $narration = $input['narration'] ?? '';
    $lines = $input['lines'];
    
    if (empty($lines)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'At least one expense line is required']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    $total_amount = array_sum(array_column($lines, 'amount'));
    
    if ($allocation_method === 'direct') {
        if (!isset($input['production_order_id'])) {
            throw new Exception('Production order required for direct allocation');
        }
        
        $production_order_id = $input['production_order_id'];
        
        $stmt_expense = $pdo->prepare("
            INSERT INTO production_expenses
            (tenant_id, production_order_id, entry_date, narration, total_amount, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'Posted', ?, NOW())
        ");
        
        $stmt_expense->execute([$tenant_id, $production_order_id, $entry_date, $narration, $total_amount, $user_id]);
        $expense_id = $pdo->lastInsertId();
        
        processExpenseLines($pdo, $expense_id, $lines, $tenant_id, $entry_date, $narration);
        
    } else {
        if (!isset($input['batches']) || empty($input['batches'])) {
            throw new Exception('Batches required for multi-batch allocation');
        }
        
        $batches = $input['batches'];
        $is_period = ($allocation_method === 'period');
        
        foreach ($batches as $batch) {
            $po_id = $batch['po_id'];
            $allocated_amount = $batch['allocation_amount'];
            $per_unit_cost = $batch['per_unit_cost'];
            
            $stmt_expense = $pdo->prepare("
                INSERT INTO production_expenses
                (tenant_id, production_order_id, entry_date, narration, total_amount, allocation_method, per_unit_cost, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Posted', ?, NOW())
            ");
            
            $method_value = $is_period ? 'period' : 'multi';
            $stmt_expense->execute([$tenant_id, $po_id, $entry_date, $narration, $allocated_amount, $method_value, $per_unit_cost, $user_id]);
            $expense_id = $pdo->lastInsertId();
            
            $proportion = $allocated_amount / $total_amount;
            $allocated_lines = array_map(function($line) use ($proportion) {
                $line['amount'] = $line['amount'] * $proportion;
                return $line;
            }, $lines);
            
            processExpenseLines($pdo, $expense_id, $allocated_lines, $tenant_id, $entry_date, $narration);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Production expense saved successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function processExpenseLines($pdo, $expense_id, $lines, $tenant_id, $entry_date, $narration) {
    // Prepare statements for items and ledger
    $stmt_item = $pdo->prepare("
        INSERT INTO production_expense_items
        (expense_id, gl_account_id, expense_type, description, hours, rate, amount, vendor_id, payment_status, payment_mode, bank_account_id, cheque_no, cheque_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt_ledger = $pdo->prepare("
        INSERT INTO accounting_ledger
        (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt_cheque = $pdo->prepare("
        INSERT INTO post_dated_cheques
        (tenant_id, cheque_no, bank_account_id, transaction_type, supplier_id, amount, cheque_date, status, reference_id, reference_table, created_at)
        VALUES (?, ?, ?, 'Expense', ?, ?, ?, 'Pending', ?, 'production_expenses', NOW())
    ");
    
    foreach ($lines as $line) {
        // Insert expense item
        $stmt_item->execute([
            $expense_id,
            $line['gl_account_id'],
            $line['expense_type'] ?? null,
            $line['description'] ?? null,
            $line['hours'] ?? null,
            $line['rate'] ?? null,
            $line['amount'],
            $line['vendor_id'] ?? null,
            $line['payment_status'],
            $line['payment_mode'] ?? null,
            $line['bank_account_id'] ?? null,
            $line['cheque_no'] ?? null,
            $line['cheque_date'] ?? null
        ]);
        
        // Debit entry - Expense account
        $stmt_ledger->execute([
            $tenant_id,
            'production_expense',
            'production_expenses',
            $expense_id,
            $line['gl_account_id'],
            $entry_date,
            $narration ?: 'Production expense',
            $line['amount'],
            0
        ]);
        
        // Credit entry - Payment account
        $credit_account_id = null;
        if ($line['payment_status'] === 'accrued') {
            $credit_account_id = 187; // Accrued Expenses (Liability)
        } elseif ($line['payment_mode'] === 'cash') {
            $credit_account_id = 1; // Cash Account
        } elseif ($line['payment_mode'] === 'bank') {
            // Get account_id from bank_accounts table
            $stmt_bank = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
            $stmt_bank->execute([$line['bank_account_id']]);
            $bank_result = $stmt_bank->fetch();
            $credit_account_id = $bank_result['account_id'] ?? null;
        } elseif ($line['payment_mode'] === 'cheque') {
            $credit_account_id = 79; // Post-Dated Cheques Payable
            
            // Insert into post_dated_cheques table
            $stmt_cheque->execute([
                $tenant_id,
                $line['cheque_no'],
                $line['bank_account_id'],
                $line['vendor_id'],
                $line['amount'],
                $line['cheque_date'],
                $expense_id
            ]);
        }
        
        if ($credit_account_id) {
            $stmt_ledger->execute([
                $tenant_id,
                'production_expense',
                'production_expenses',
                $expense_id,
                $credit_account_id,
                $entry_date,
                $narration ?: 'Production expense payment',
                0,
                $line['amount']
            ]);
        }
    }
}
