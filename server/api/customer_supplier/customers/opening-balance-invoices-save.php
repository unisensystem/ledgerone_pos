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
    
    if (empty($input['invoices']) || !is_array($input['invoices'])) {
        throw new Exception('No invoices provided');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    $totalDebitAdded = 0;
    $totalCreditAdded = 0;
    $customerDebitMap = []; // To track debit by customer for opening balance update
    $customerCreditMap = []; // To track credit by customer for opening balance update
    $savedCount = 0;
    $errors = [];

    foreach ($input['invoices'] as $index => $invoice) {
        try {
            // Validate required fields
            if (empty($invoice['customer_id'])) {
                $errors[] = "Row " . ($index + 1) . ": Customer is required";
                continue;
            }

            if (empty($invoice['invoice_date'])) {
                $errors[] = "Row " . ($index + 1) . ": Invoice date is required";
                continue;
            }

            $debit = floatval($invoice['debit'] ?? 0);
            $credit = floatval($invoice['credit'] ?? 0);

            if ($debit <= 0 && $credit <= 0) {
                $errors[] = "Row " . ($index + 1) . ": Either Debit or Credit amount must be greater than 0";
                continue;
            }

            $customer_id = (int)$invoice['customer_id'];
            $distribution_id = !empty($invoice['distribution_id']) ? (int)$invoice['distribution_id'] : 1;
            $employee_id = !empty($invoice['employee_id']) ? (int)$invoice['employee_id'] : null;
            $invoice_number = trim($invoice['invoice_number']);
            $invoice_date = trim($invoice['invoice_date']);
            
            // Check if invoice already exists (only if invoice_number provided)
            if (!empty($invoice_number)) {
                $checkStmt = $pdo->prepare("
                    SELECT id FROM opening_balance_invoices 
                    WHERE tenant_id = ? AND customer_id = ? AND invoice_number = ?
                ");
                $checkStmt->execute([$tenant_id, $customer_id, $invoice_number]);
                
                if ($checkStmt->rowCount() > 0) {
                    $errors[] = "Row " . ($index + 1) . ": Invoice number already exists for this customer";
                    continue;
                }
            }
            
            // Insert opening balance invoice
            $stmt = $pdo->prepare("
                INSERT INTO opening_balance_invoices
                (tenant_id, customer_id, distribution_id, employee_id, invoice_number, debit, credit, invoice_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $tenant_id,
                $customer_id,
                $distribution_id,
                $employee_id,
                $invoice_number,
                $debit,
                $credit,
                $invoice_date
            ]);
            
            // Get distribution and employee names for ledger description
            $distribution_name = 'N/A';
            $employee_name = 'N/A';
            
            if (!empty($distribution_id)) {
                $distStmt = $pdo->prepare("SELECT company_name FROM companies WHERE id = ? AND tenant_id = ?");
                $distStmt->execute([$distribution_id, $tenant_id]);
                $distribution = $distStmt->fetch();
                if ($distribution) $distribution_name = $distribution['company_name'];
            }
            
            if (!empty($employee_id)) {
                $empStmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
                $empStmt->execute([$employee_id, $tenant_id]);
                $employee = $empStmt->fetch();
                if ($employee) $employee_name = $employee['full_name'];
            }
            
            // Create ledger description
            $description = 'Opening invoice: ' . $invoice_number . 
                         ' - Distribution: ' . $distribution_name . 
                         ' - Sales Officer: ' . $employee_name;
            
            // Insert accounting ledger entries (Account 2 = Trade Debtors, Account 90 = Opening Balance Equity)
            $ledger_sql = "INSERT INTO accounting_ledger (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $ledgerStmt = $pdo->prepare($ledger_sql);

            if ($debit > 0) {
                // Debit entry: Trade Debtors Account
                $ledgerStmt->execute([
                    $tenant_id,
                    'customer_opening',
                    'opening_balance_invoices',
                    $customer_id,
                    2,
                    $invoice_date,
                    $description,
                    $debit,
                    0
                ]);

                // Credit entry: Opening Balance Equity Account
                $ledgerStmt->execute([
                    $tenant_id,
                    'customer_opening',
                    'opening_balance_invoices',
                    $customer_id,
                    90,
                    $invoice_date,
                    $description,
                    0,
                    $debit
                ]);

                // Track debit by customer
                if (!isset($customerDebitMap[$customer_id])) {
                    $customerDebitMap[$customer_id] = 0;
                }
                $customerDebitMap[$customer_id] += $debit;
                $totalDebitAdded += $debit;
            } else {
                // Reversed entries for a Credit invoice row
                $ledgerStmt->execute([
                    $tenant_id,
                    'customer_opening',
                    'opening_balance_invoices',
                    $customer_id,
                    90,
                    $invoice_date,
                    $description,
                    $credit,
                    0
                ]);

                $ledgerStmt->execute([
                    $tenant_id,
                    'customer_opening',
                    'opening_balance_invoices',
                    $customer_id,
                    2,
                    $invoice_date,
                    $description,
                    0,
                    $credit
                ]);

                // Track credit by customer
                if (!isset($customerCreditMap[$customer_id])) {
                    $customerCreditMap[$customer_id] = 0;
                }
                $customerCreditMap[$customer_id] += $credit;
                $totalCreditAdded += $credit;
            }

            $savedCount++;

        } catch (Exception $rowError) {
            $errors[] = "Row " . ($index + 1) . ": " . $rowError->getMessage();
            continue;
        }
    }
    
    // Update customer opening_debit_amount / opening_credit_amount if records were saved
    if ($savedCount > 0) {
        foreach ($customerDebitMap as $cust_id => $total_debit) {
            $updateStmt = $pdo->prepare("
                UPDATE customers
                SET opening_debit_amount = opening_debit_amount + ?
                WHERE id = ? AND tenant_id = ?
            ");
            $updateStmt->execute([$total_debit, $cust_id, $tenant_id]);
        }
        foreach ($customerCreditMap as $cust_id => $total_credit) {
            $updateStmt = $pdo->prepare("
                UPDATE customers
                SET opening_credit_amount = opening_credit_amount + ?
                WHERE id = ? AND tenant_id = ?
            ");
            $updateStmt->execute([$total_credit, $cust_id, $tenant_id]);
        }
    }

    // Commit transaction
    $pdo->commit();

    $response = [
        'success' => true,
        'message' => $savedCount . ' invoices saved successfully',
        'saved_count' => $savedCount,
        'total_debit' => $totalDebitAdded,
        'total_credit' => $totalCreditAdded
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
