<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

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

$data = json_decode(file_get_contents('php://input'), true);
$pdc_id = $data['id'] ?? null;
$new_status = $data['status'] ?? null;
$override_bank_account_id = $data['bank_account_id'] ?? null;

if (!$pdc_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$allowed_statuses = ['Pending', 'Approved', 'Rejected', 'Cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $debug_info = [];
    
    // Get PDC details with bank account_id
    $stmt = $pdo->prepare("SELECT pdc.transaction_type, pdc.amount, pdc.cheque_date, pdc.account_id, pdc.cheque_no, pdc.reference_table, pdc.reference_id, pdc.status as old_status, ba.account_id as bank_account_id
                           FROM post_dated_cheques pdc
                           LEFT JOIN bank_accounts ba ON pdc.bank_account_id = ba.id
                           WHERE pdc.id = ? AND pdc.tenant_id = ?");
    $stmt->execute([$pdc_id, $tenant_id]);
    $pdc = $stmt->fetch();
    
    if (!$pdc) {
        throw new Exception('PDC not found');
    }
    
    $debug_info['pdc_data'] = $pdc;
    
    // Update status (and bank_account_id if overridden)
    if ($override_bank_account_id) {
        $stmt = $pdo->prepare("UPDATE post_dated_cheques SET status = ?, bank_account_id = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$new_status, $override_bank_account_id, $pdc_id, $tenant_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE post_dated_cheques SET status = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$new_status, $pdc_id, $tenant_id]);
    }
    
    // Resolve final bank account_id for ledger entries
    if ($override_bank_account_id) {
        $stmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$override_bank_account_id, $tenant_id]);
        $overrideBankRow = $stmt->fetch();
        $bank_account_id = $overrideBankRow['account_id'] ?? $pdc['bank_account_id'];
    }
    
    // Insert accounting ledger entries
    $transaction_type = $pdc['transaction_type'];
    $amount = $pdc['amount'];
    $date = $pdc['cheque_date'];
    $bank_account_id = $pdc['bank_account_id'];
    $account_id = $pdc['account_id'];
    $description = "PDC {$pdc['cheque_no']} - Status: {$new_status}";
    $reference_table = $pdc['reference_table'];
    $reference_id = $pdc['reference_id'];
    
    // Debug log
    error_log("PDC Update - Type: $transaction_type, Status: $new_status, Bank Account ID: $bank_account_id, Amount: $amount");
    
    $debug_info['variables'] = [
        'transaction_type' => $transaction_type,
        'new_status' => $new_status,
        'bank_account_id' => $bank_account_id,
        'amount' => $amount,
        'in_array_check' => in_array($new_status, ['Approved', 'Rejected', 'Cancelled']),
        'type_match_received' => ($transaction_type === 'Received')
    ];
    
    // Only insert ledger entries if status is Approved, Rejected, or Cancelled
    if (in_array($new_status, ['Approved', 'Rejected', 'Cancelled'])) {
        $debug_info['entered_status_check'] = true;
        
        if ($transaction_type === 'Received') {
        $debug_info['entered_received_block'] = true;
        if ($new_status === 'Approved') {
            // Debit: Bank Account, Credit: PDC Receivable (78)
            try {
                $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'PDC', ?, ?, ?, ?, ?, ?, 0)");
                $result1 = $stmt->execute([$tenant_id, $reference_table, $reference_id, $bank_account_id, $date, $description, $amount]);
                $debug_info['insert1'] = ['success' => $result1, 'rows' => $stmt->rowCount(), 'error' => $stmt->errorInfo()];
                
                $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'PDC', ?, ?, 78, ?, ?, 0, ?)");
                $result2 = $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
                $debug_info['insert2'] = ['success' => $result2, 'rows' => $stmt->rowCount(), 'error' => $stmt->errorInfo()];
            } catch (Exception $e) {
                $debug_info['insert_error'] = $e->getMessage();
            }
        } elseif ($new_status === 'Rejected' || $new_status === 'Cancelled') {
            // Debit: Trade Debtors (2), Credit: PDC Receivable (78)
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, 2, ?, ?, ?, 0)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, 78, ?, ?, 0, ?)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
        }
        } elseif ($transaction_type === 'Payment' || $transaction_type === 'Paid') {
            $debug_info['entered_paid_block'] = true;
            if ($new_status === 'Approved') {
                $debug_info['entered_paid_approved'] = true;
                // Debit: PDC Payable (79), Credit: Bank Account
                $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'PDC', ?, ?, 79, ?, ?, ?, 0)");
                $result1 = $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
                $debug_info['paid_insert1'] = ['success' => $result1, 'rows' => $stmt->rowCount()];
                
                $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                    (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                    VALUES (?, 'PDC', ?, ?, ?, ?, ?, 0, ?)");
                $result2 = $stmt->execute([$tenant_id, $reference_table, $reference_id, $bank_account_id, $date, $description, $amount]);
                $debug_info['paid_insert2'] = ['success' => $result2, 'rows' => $stmt->rowCount()];
        } elseif ($new_status === 'Rejected' || $new_status === 'Cancelled') {
            // Debit: PDC Payable (79), Credit: Trade Creditors (14)
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, 79, ?, ?, ?, 0)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, 14, ?, ?, 0, ?)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
        }
        } elseif ($transaction_type === 'Expense') {
        if ($new_status === 'Approved') {
            // Debit: PDC Payable (79), Credit: Bank Account
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, 79, ?, ?, ?, 0)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, ?, ?, ?, 0, ?)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $bank_account_id, $date, $description, $amount]);
        } elseif ($new_status === 'Rejected' || $new_status === 'Cancelled') {
            // Debit: PDC Payable (79), Credit: Expense Account (account_id)
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, 79, ?, ?, ?, 0)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $date, $description, $amount]);
            
            $stmt = $pdo->prepare("INSERT INTO accounting_ledger 
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) 
                VALUES (?, 'PDC', ?, ?, ?, ?, ?, 0, ?)");
            $stmt->execute([$tenant_id, $reference_table, $reference_id, $account_id, $date, $description, $amount]);
        }
        }
    }
    
    $oldStatus = $pdc['old_status'];
    $editDescription = $oldStatus !== $new_status
        ? "Updated PDC \"{$pdc['cheque_no']}\" status: $oldStatus \u{2192} $new_status"
        : "Updated PDC \"{$pdc['cheque_no']}\" - no tracked field changes";
    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Post Dated Cheque', $pdc_id, $pdc['cheque_no'],
        $editDescription,
        ['status' => $oldStatus],
        ['status' => $new_status]
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'debug' => $debug_info]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
