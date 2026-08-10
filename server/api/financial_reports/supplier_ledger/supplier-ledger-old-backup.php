<?php
session_start();
require_once '../../../../includes/connection.php';
require_once 'currency-converter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $supplier_id = $_GET['supplier_id'] ?? null;
    $from_date = !empty($_GET['from_date']) ? $_GET['from_date'] : null;
    $to_date = !empty($_GET['to_date']) ? $_GET['to_date'] : null;
    $type = $_GET['type'] ?? 'summary';
    $sub_account_id = $_GET['sub_account_id'] ?? null;
    $company_id = !empty($_GET['company_id']) ? $_GET['company_id'] : null;
    $target_currency_id = !empty($_GET['currency_id']) ? $_GET['currency_id'] : null;
    
    $converter = new CurrencyConverter($pdo);

    if ($type === 'suppliers') {
        $stmt = $pdo->prepare("SELECT id, supplier_code, supplier_name FROM suppliers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY supplier_name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($type === 'currencies') {
        $stmt = $pdo->prepare("SELECT c.id, c.code, c.name, c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_active = 1 ORDER BY tc.is_base_currency DESC, c.name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'sub_accounts') {
        $stmt = $pdo->prepare("SELECT id, sub_account_name FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? ORDER BY sub_account_name");
        $stmt->execute([$tenant_id, $supplier_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'summary') {
        $sql = "SELECT s.id, s.supplier_name, s.opening_debit_amount, s.opening_credit_amount FROM suppliers s WHERE s.tenant_id = ? AND s.status = 'ACTIVE'";
        $params = [$tenant_id];
        
        if ($supplier_id) {
            $sql .= " AND s.id = ?";
            $params[] = $supplier_id;
        }
        
        $sql .= " ORDER BY s.supplier_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($suppliers as $supplier) {
            $base_opening = $supplier['opening_debit_amount'] - $supplier['opening_credit_amount'];
            
            // Calculate soft opening if from_date provided
            $opening_balance = $base_opening;
            if ($from_date) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_invoices = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND voucher_date < ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date, $tenant_id];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_payments = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM post_dated_cheques WHERE tenant_id = ? AND supplier_id = ? AND transaction_type = 'Payment' AND status = 'Approved' AND cheque_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_pdcs = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_returns = $stmt->fetch()['total'];
                
                $opening_balance = $base_opening - $prev_invoices + $prev_payments + $prev_pdcs + $prev_returns;
            }
            
            // Get transactions in date range with currency conversion
            if ($target_currency_id) {
                $invoice_sql = "SELECT pi.net_amount, pi.currency_id FROM purchase_invoice pi WHERE pi.tenant_id = ? AND pi.supplier_id = ?" . ($company_id ? " AND pi.company_id = ?" : "");
                $payment_sql = "SELECT pv.amount, pv.currency_id FROM payment_voucher pv WHERE pv.tenant_id = ? AND pv.supplier_id = ? AND pv.id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND pv.company_id = ?" : "");
            } else {
                $invoice_sql = "SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ?" . ($company_id ? " AND company_id = ?" : "");
                $payment_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : "");
            }
            $invoice_params = [$tenant_id, $supplier['id']];
            if ($company_id) $invoice_params[] = $company_id;
            $payment_params = [$tenant_id, $supplier['id'], $tenant_id];
            if ($company_id) $payment_params[] = $company_id;
            
            if ($from_date && $to_date) {
                $invoice_sql .= " AND purchase_date BETWEEN ? AND ?";
                $payment_sql .= " AND voucher_date BETWEEN ? AND ?";
                $invoice_params[] = $from_date;
                $invoice_params[] = $to_date;
                $payment_params[] = $from_date;
                $payment_params[] = $to_date;
            }
            
            $stmt = $pdo->prepare($invoice_sql);
            $stmt->execute($invoice_params);
            if ($target_currency_id) {
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_credit = 0;
                foreach ($invoices as $inv) {
                    $total_credit += $converter->convert($inv['net_amount'], $inv['currency_id'], $target_currency_id);
                }
            } else {
                $total_credit = $stmt->fetch()['total'];
            }
            
            $stmt = $pdo->prepare($payment_sql);
            $stmt->execute($payment_params);
            if ($target_currency_id) {
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_debit = 0;
                foreach ($payments as $pay) {
                    $total_debit += $converter->convert($pay['amount'], $pay['currency_id'], $target_currency_id);
                }
            } else {
                $total_debit = $stmt->fetch()['total'];
            }
            
            // Get purchase returns with currency conversion
            if ($target_currency_id) {
                $return_sql = "SELECT pr.net_amount, pr.currency_id FROM purchase_return pr WHERE pr.tenant_id = ? AND pr.supplier_id = ?" . ($company_id ? " AND pr.company_id = ?" : "");
            } else {
                $return_sql = "SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND supplier_id = ?" . ($company_id ? " AND company_id = ?" : "");
            }
            $return_params = [$tenant_id, $supplier['id']];
            if ($company_id) $return_params[] = $company_id;
            
            if ($from_date && $to_date) {
                $return_sql .= " AND purchase_date BETWEEN ? AND ?";
                $return_params[] = $from_date;
                $return_params[] = $to_date;
            }
            
            $stmt = $pdo->prepare($return_sql);
            $stmt->execute($return_params);
            if ($target_currency_id) {
                $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_returns = 0;
                foreach ($returns as $ret) {
                    $total_returns += $converter->convert($ret['net_amount'], $ret['currency_id'], $target_currency_id);
                }
            } else {
                $total_returns = $stmt->fetch()['total'];
            }
            
            // Get approved PDCs with currency conversion
            $pdc_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM post_dated_cheques WHERE tenant_id = ? AND supplier_id = ? AND transaction_type = 'Payment' AND status = 'Approved'" . ($company_id ? " AND company_id = ?" : "");
            $pdc_params = [$tenant_id, $supplier['id']];
            if ($company_id) $pdc_params[] = $company_id;
            
            if ($from_date && $to_date) {
                $pdc_sql .= " AND cheque_date BETWEEN ? AND ?";
                $pdc_params[] = $from_date;
                $pdc_params[] = $to_date;
            }
            
            $stmt = $pdo->prepare($pdc_sql);
            $stmt->execute($pdc_params);
            $total_pdc = $stmt->fetch()['total'];
            
            $total_debit = $total_debit + $total_pdc;
            $closing_balance = $opening_balance - $total_credit + $total_debit + $total_returns;
            
            $data[] = [
                'supplier_name' => $supplier['supplier_name'],
                'opening_balance' => $opening_balance,
                'total_debit' => $total_debit,
                'total_credit' => $total_credit,
                'closing_balance' => $closing_balance
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        if (!$supplier_id) {
            echo json_encode(['success' => false, 'message' => 'Supplier ID required for detailed ledger']);
            exit;
        }
        
        // Get supplier opening balance
        $stmt = $pdo->prepare("SELECT opening_debit_amount, opening_credit_amount FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$supplier_id, $tenant_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        $base_opening = $supplier['opening_debit_amount'] - $supplier['opening_credit_amount'];
        
        // Get sub accounts with opening balances
        $stmt = $pdo->prepare("SELECT id, sub_account_name, debit, credit FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? ORDER BY sub_account_name");
        $stmt->execute([$tenant_id, $supplier_id]);
        $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate soft opening balance if from_date is provided
        $opening_balance = $base_opening;
        if ($from_date) {
            // Get transactions before from_date
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total_credit FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_invoices = $stmt->fetch()['total_credit'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_debit FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND voucher_date < ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date, $tenant_id];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_payments = $stmt->fetch()['total_debit'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_debit FROM post_dated_cheques WHERE tenant_id = ? AND supplier_id = ? AND transaction_type = 'Payment' AND status = 'Approved' AND cheque_date < ?" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_pdcs = $stmt->fetch()['total_debit'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total_debit FROM purchase_return WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_returns = $stmt->fetch()['total_debit'];
            
            $opening_balance = $base_opening - $prev_invoices + $prev_payments + $prev_pdcs + $prev_returns;
        }
        
        // Get purchase invoices with currency
        $sql = "SELECT pi.purchase_date as date, CONCAT('Purchase Invoice - ', pi.bill_no) as description, 
                pi.bill_no as reference, 0 as debit, pi.net_amount as credit, pi.sub_account_id, pi.id as invoice_id, pi.currency_id
                FROM purchase_invoice pi
                WHERE pi.tenant_id = ? AND pi.supplier_id = ?" . ($company_id ? " AND pi.company_id = ?" : "");
        $params = [$tenant_id, $supplier_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) {
            $sql .= " AND pi.purchase_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        } elseif ($from_date) {
            $sql .= " AND pi.purchase_date >= ?";
            $params[] = $from_date;
        } elseif ($to_date) {
            $sql .= " AND pi.purchase_date <= ?";
            $params[] = $to_date;
        }
        $sql .= " ORDER BY pi.purchase_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_invoice_sql = $sql;
        $debug_invoice_params = $params;
        $debug_invoice_count = count($invoices);
        
        // Get payment vouchers (exclude those linked to PDCs) with currency
        $sql = "SELECT voucher_date as date, CONCAT('Payment - ', voucher_number) as description,
                voucher_number as reference, amount as debit, 0 as credit, sub_account_id, currency_id
                FROM payment_voucher 
                WHERE tenant_id = ? AND supplier_id = ?
                AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : "");
        $params = [$tenant_id, $supplier_id, $tenant_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) {
            $sql .= " AND voucher_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        } elseif ($from_date) {
            $sql .= " AND voucher_date >= ?";
            $params[] = $from_date;
        } elseif ($to_date) {
            $sql .= " AND voucher_date <= ?";
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_payment_sql = $sql;
        $debug_payment_params = $params;
        $debug_payment_count = count($payments);
        
        // Get PDCs
        $sql = "SELECT cheque_date as date, 
                CONCAT('PDC - ', cheque_no, ' (', status, ')') as description,
                cheque_no as reference, 
                amount as debit,
                0 as credit,
                status as pdc_status
                FROM post_dated_cheques 
                WHERE tenant_id = ? AND supplier_id = ? AND transaction_type = 'Payment'" . ($company_id ? " AND company_id = ?" : "");
        $params = [$tenant_id, $supplier_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) {
            $sql .= " AND cheque_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        } elseif ($from_date) {
            $sql .= " AND cheque_date >= ?";
            $params[] = $from_date;
        } elseif ($to_date) {
            $sql .= " AND cheque_date <= ?";
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get purchase returns with currency
        $sql = "SELECT pr.purchase_date as date, CONCAT('Purchase Return - ', pr.bill_no) as description, 
                pr.bill_no as reference, pr.net_amount as debit, 0 as credit, pr.sub_account_id, pr.id as return_id, pr.currency_id
                FROM purchase_return pr
                WHERE pr.tenant_id = ? AND pr.supplier_id = ?" . ($company_id ? " AND pr.company_id = ?" : "");
        $params = [$tenant_id, $supplier_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) {
            $sql .= " AND pr.purchase_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        } elseif ($from_date) {
            $sql .= " AND pr.purchase_date >= ?";
            $params[] = $from_date;
        } elseif ($to_date) {
            $sql .= " AND pr.purchase_date <= ?";
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and sort transactions
        $transactions = array_merge($invoices, $payments, $pdcs, $returns);
        
        // Convert currencies if target currency specified
        if ($target_currency_id) {
            foreach ($transactions as &$transaction) {
                if (isset($transaction['currency_id']) && $transaction['currency_id'] != $target_currency_id) {
                    if ($transaction['debit'] > 0) {
                        $transaction['debit'] = $converter->convert($transaction['debit'], $transaction['currency_id'], $target_currency_id);
                    }
                    if ($transaction['credit'] > 0) {
                        $transaction['credit'] = $converter->convert($transaction['credit'], $transaction['currency_id'], $target_currency_id);
                    }
                }
            }
        }
        
        usort($transactions, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Filter by sub account if specified
        if ($sub_account_id) {
            $transactions = array_filter($transactions, function($t) use ($sub_account_id) {
                return ($t['sub_account_id'] ?? null) == $sub_account_id;
            });
            $transactions = array_values($transactions);
            
            $sub_accounts = array_filter($sub_accounts, function($s) use ($sub_account_id) {
                return $s['id'] == $sub_account_id;
            });
            $sub_accounts = array_values($sub_accounts);
            
            // Recalculate opening balance for the specific sub account
            if (!empty($sub_accounts)) {
                $opening_balance = (floatval($sub_accounts[0]['debit']) - floatval($sub_accounts[0]['credit']));
                
                if ($from_date) {
                    // Get transactions before from_date for this sub account
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ? AND sub_account_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                    $params_sub = [$tenant_id, $supplier_id, $sub_account_id, $from_date];
                    if ($company_id) $params_sub[] = $company_id;
                    $stmt->execute($params_sub);
                    $prev_invoices = $stmt->fetch()['total'];
                    
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND sub_account_id = ? AND voucher_date < ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : ""));
                    $params_sub = [$tenant_id, $supplier_id, $sub_account_id, $from_date, $tenant_id];
                    if ($company_id) $params_sub[] = $company_id;
                    $stmt->execute($params_sub);
                    $prev_payments = $stmt->fetch()['total'];
                    
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND supplier_id = ? AND sub_account_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                    $params_sub = [$tenant_id, $supplier_id, $sub_account_id, $from_date];
                    if ($company_id) $params_sub[] = $company_id;
                    $stmt->execute($params_sub);
                    $prev_returns = $stmt->fetch()['total'];
                    
                    $opening_balance = $opening_balance - $prev_invoices + $prev_payments + $prev_returns;
                }
            }
        }
        
        // Group transactions by sub_account_id
        $grouped_data = [];
        $running_balance = $opening_balance;
        
        foreach ($transactions as &$transaction) {
            $debit = $transaction['debit'];
            $credit = $transaction['credit'];
            
            // Only apply debit if not a non-approved PDC
            if (isset($transaction['pdc_status']) && $transaction['pdc_status'] !== 'Approved') {
                $debit = 0;
            }
            
            $running_balance -= $credit - $debit;
            $transaction['running_balance'] = $running_balance;
            
            $sub_account_id_key = $transaction['sub_account_id'] ?? null;
            if (!isset($grouped_data[$sub_account_id_key])) {
                $grouped_data[$sub_account_id_key] = [];
            }
            $grouped_data[$sub_account_id_key][] = $transaction;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $grouped_data, 
            'sub_accounts' => $sub_accounts, 
            'opening_balance' => $opening_balance, 
            'base_opening' => $base_opening,
            'debug' => [
                'invoice_sql' => $debug_invoice_sql,
                'invoice_params' => $debug_invoice_params,
                'invoice_count' => $debug_invoice_count,
                'invoices' => $invoices,
                'payment_sql' => $debug_payment_sql,
                'payment_params' => $debug_payment_params,
                'payment_count' => $debug_payment_count,
                'payments' => $payments
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}