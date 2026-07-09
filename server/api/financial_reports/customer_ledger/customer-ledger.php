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
    $customer_id = $_GET['customer_id'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? null;
    $type = $_GET['type'] ?? 'summary';
    $distribution_id = $_GET['distribution_id'] ?? null;
    $company_id = $_GET['company_id'] ?? null;
    $target_currency_id = $_GET['currency_id'] ?? null;
    
    // Initialize currency converter
    $converter = new CurrencyConverter($pdo, $tenant_id);
    
    // Get base currency if no target currency specified
    if (!$target_currency_id) {
        $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
        $stmt->execute([$tenant_id]);
        $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
        $target_currency_id = $baseCurrency['currency_id'];
    }
    
    // Get target currency code for conversion
    $stmt = $pdo->prepare("SELECT code FROM ledgerone_public.currencies WHERE id = ?");
    $stmt->execute([$target_currency_id]);
    $targetCurrencyData = $stmt->fetch(PDO::FETCH_ASSOC);
    $target_currency_code = $targetCurrencyData['code'];
    
    if ($type === 'currencies') {
        $stmt = $pdo->prepare("SELECT tc.currency_id as id, c.name, c.symbol, c.code, tc.is_base_currency FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_active = 1 ORDER BY tc.is_base_currency DESC, c.name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'customers') {
        $sql = "SELECT id, customer_code, customer_name FROM customers WHERE tenant_id = ? AND status = 'ACTIVE'";
        $params = [$tenant_id];
        
        $sql .= " ORDER BY customer_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($type === 'distributions') {
        $stmt = $pdo->prepare("SELECT id, supplier_code, supplier_name FROM suppliers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY supplier_name");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($type === 'sub_accounts') {
        $customer_id = $_GET['customer_id'] ?? null;
        if (!$customer_id) {
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, sub_account_name FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ? ORDER BY sub_account_name");
        $stmt->execute([$tenant_id, $customer_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($type === 'summary') {
        $sql = "SELECT c.id, c.customer_name, c.opening_debit_amount, c.opening_credit_amount FROM customers c WHERE c.tenant_id = ? AND c.status = 'ACTIVE'";
        $params = [$tenant_id];
        
        if ($customer_id) {
            $sql .= " AND c.id = ?";
            $params[] = $customer_id;
        }
        
        $sql .= " ORDER BY c.customer_name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [];
        foreach ($customers as $customer) {
            $base_opening_debit = $customer['opening_debit_amount'];
            $base_opening_credit = $customer['opening_credit_amount'];
            
            // Convert opening balance to target currency (assuming base currency)
            $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
            $stmt->execute([$tenant_id]);
            $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
            $base_currency_id = $baseCurrency['currency_id'];
            
            if ($base_currency_id != $target_currency_id) {
                $base_opening_debit = $converter->convert($base_opening_debit, $base_currency_id, $target_currency_id);
                $base_opening_credit = $converter->convert($base_opening_credit, $base_currency_id, $target_currency_id);
            }
            
            $base_opening = $base_opening_debit - $base_opening_credit;
            
            // Calculate soft opening if from_date provided
            $opening_balance = $base_opening;
            $company_filter = ($company_id ? " AND company_id = ?" : "");
            if ($from_date) {
                $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM sale_invoice WHERE tenant_id = ? AND customer_id = ? AND sale_date < ?{$company_filter}");
                $params_prev = [$tenant_id, $customer['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_invoices_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_invoices = 0;
                foreach ($prev_invoices_data as $inv) {
                    $amount = $inv['net_amount'];
                    if ($inv['currency_id'] && $inv['currency_id'] != $target_currency_id) {
                        $amount = $converter->convert($amount, $inv['currency_id'], $target_currency_id);
                    }
                    $prev_invoices += $amount;
                }
                
                $stmt = $pdo->prepare("SELECT rv.amount, rv.currency_id FROM receive_voucher rv WHERE rv.tenant_id = ? AND rv.customer_id = ? AND rv.voucher_date < ?{$company_filter}
                    AND rv.id NOT IN (
                        SELECT pdc.reference_id FROM post_dated_cheques pdc
                        WHERE pdc.tenant_id = ? AND pdc.reference_table = 'receive_voucher' AND pdc.status = 'Pending'
                    )");
                $params_prev = [$tenant_id, $customer['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $params_prev[] = $tenant_id;
                $stmt->execute($params_prev);
                $prev_payments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_payments = 0;
                foreach ($prev_payments_data as $pay) {
                    $amount = $pay['amount'];
                    if ($pay['currency_id'] && $pay['currency_id'] != $target_currency_id) {
                        $amount = $converter->convert($amount, $pay['currency_id'], $target_currency_id);
                    }
                    $prev_payments += $amount;
                }
                
                $stmt = $pdo->prepare("SELECT amount, currency_id FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ? AND voucher_date < ?{$company_filter}");
                $params_prev = [$tenant_id, $customer['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_payment_vouchers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_payment_vouchers = 0;
                foreach ($prev_payment_vouchers_data as $pv) {
                    $amount = $pv['amount'];
                    if ($pv['currency_id'] && $pv['currency_id'] != $target_currency_id) {
                        $amount = $converter->convert($amount, $pv['currency_id'], $target_currency_id);
                    }
                    $prev_payment_vouchers += $amount;
                }
                
                $stmt = $pdo->prepare("SELECT amount_refunded, net_amount, currency_id FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND status = 'Posted'{$company_filter}");
                $params_prev = [$tenant_id, $customer['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_returns_data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_refunded = 0;
                $prev_credit = 0;
                foreach ($prev_returns_data_raw as $ret) {
                    $refunded = $ret['amount_refunded'];
                    $credit = $ret['net_amount'] - $ret['amount_refunded'];
                    if ($ret['currency_id'] && $ret['currency_id'] != $target_currency_id) {
                        $refunded = $converter->convert($refunded, $ret['currency_id'], $target_currency_id);
                        $credit = $converter->convert($credit, $ret['currency_id'], $target_currency_id);
                    }
                    $prev_refunded += $refunded;
                    $prev_credit += $credit;
                }
                $prev_returns_data = ['refunded' => $prev_refunded, 'credit' => $prev_credit];
                
                $stmt = $pdo->prepare("SELECT al.credit, rm.currency_id FROM accounting_ledger al JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management' WHERE al.tenant_id = ? AND al.account_id = 2 AND rm.customer_id = ? AND al.date < ?");
                $stmt->execute([$tenant_id, $customer['id'], $from_date]);
                $prev_rent_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_rent = 0;
                foreach ($prev_rent_data as $rent) {
                    $amount = $rent['credit'];
                    if ($rent['currency_id'] && $rent['currency_id'] != $target_currency_id) {
                        $amount = $converter->convert($amount, $rent['currency_id'], $target_currency_id);
                    }
                    $prev_rent += $amount;
                }
                
                // Transfer voucher soft opening — FROM customer (credit), TO customer (debit)
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id = ? AND voucher_date < ?{$company_filter} GROUP BY currency_id");
                $params_prev = [$tenant_id, $customer['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_tv_from = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $amt = $row['total'];
                    if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                    $prev_tv_from += $amt;
                }

                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id = ? AND voucher_date < ?{$company_filter} GROUP BY currency_id");
                $params_prev = [$tenant_id, $customer['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_tv_to = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $amt = $row['total'];
                    if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                    $prev_tv_to += $amt;
                }

                $opening_balance = $base_opening + $prev_invoices + $prev_returns_data['refunded'] + $prev_tv_to + $prev_payment_vouchers - $prev_payments - $prev_returns_data['credit'] - $prev_rent - $prev_tv_from;
            }
            
            // Get transactions in date range
            if ($distribution_id) {
                // Calculate proportional amounts including invoice-level charges
                $invoice_sql = "SELECT COALESCE(SUM(
                    sii.net_amount + 
                    (sii.net_amount / NULLIF(si.total_bill, 0)) * (si.total_discount_amount + si.total_gst_amount + COALESCE(si.shipping_fees, 0))
                ), 0) as total 
                FROM sale_invoice_items sii
                JOIN sale_invoice si ON sii.sale_invoice_id = si.id
                JOIN products p ON sii.product_id = p.id
                WHERE si.tenant_id = ? AND si.customer_id = ? AND p.vendor_id = ?";
                $invoice_params = [$tenant_id, $customer['id'], $distribution_id];
                
                $return_sql = "SELECT COALESCE(SUM(
                    sri.net_amount + 
                    (sri.net_amount / NULLIF(sr.total_bill, 0)) * (sr.total_discount_amount + sr.total_gst_amount + COALESCE(sr.shipping_fees, 0))
                ), 0) as total
                FROM sale_return_items sri
                JOIN sale_return sr ON sri.sale_invoice_id = sr.id
                JOIN products p ON sri.product_id = p.id
                WHERE sr.tenant_id = ? AND sr.customer_id = ? AND sr.status = 'Posted' AND p.vendor_id = ?";
                $return_params = [$tenant_id, $customer['id'], $distribution_id];
            } else {
                $invoice_sql = "SELECT net_amount, currency_id FROM sale_invoice WHERE tenant_id = ? AND customer_id = ?{$company_filter}";
                $invoice_params = [$tenant_id, $customer['id']];
                if ($company_id) $invoice_params[] = $company_id;
                
                $return_sql = "SELECT net_amount, currency_id FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'{$company_filter}";
                $return_params = [$tenant_id, $customer['id']];
                if ($company_id) $return_params[] = $company_id;
            }
            
            $payment_sql = "SELECT rv.amount, rv.currency_id FROM receive_voucher rv WHERE rv.tenant_id = ? AND rv.customer_id = ?{$company_filter}
                AND rv.id NOT IN (
                    SELECT pdc.reference_id FROM post_dated_cheques pdc
                    WHERE pdc.tenant_id = ? AND pdc.reference_table = 'receive_voucher' AND pdc.status = 'Pending'
                )";
            $payment_params = [$tenant_id, $customer['id']];
            if ($company_id) $payment_params[] = $company_id;
            $payment_params[] = $tenant_id;
            
            $payment_voucher_sql = "SELECT amount, currency_id FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ?{$company_filter}";
            $payment_voucher_params = [$tenant_id, $customer['id']];
            if ($company_id) $payment_voucher_params[] = $company_id;
            
            if ($from_date && $to_date) {
                $invoice_sql .= $distribution_id ? " AND si.sale_date BETWEEN ? AND ?" : " AND sale_date BETWEEN ? AND ?";
                $payment_sql .= " AND voucher_date BETWEEN ? AND ?";
                $payment_voucher_sql .= " AND voucher_date BETWEEN ? AND ?";
                $return_sql .= $distribution_id ? " AND sr.sale_date BETWEEN ? AND ?" : " AND sale_date BETWEEN ? AND ?";
                $invoice_params[] = $from_date;
                $invoice_params[] = $to_date;
                $payment_params[] = $from_date;
                $payment_params[] = $to_date;
                $payment_voucher_params[] = $from_date;
                $payment_voucher_params[] = $to_date;
                $return_params[] = $from_date;
                $return_params[] = $to_date;
            }
            
            $stmt = $pdo->prepare($invoice_sql);
            $stmt->execute($invoice_params);
            $invoice_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_debit = 0;
            foreach ($invoice_data as $inv) {
                $amount = $inv['net_amount'] ?? $inv['total'];
                if (isset($inv['currency_id']) && $inv['currency_id'] && $inv['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $inv['currency_id'], $target_currency_id);
                }
                $total_debit += $amount;
            }
            
            $stmt = $pdo->prepare($payment_voucher_sql);
            $stmt->execute($payment_voucher_params);
            $pv_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pv_data as $pv) {
                $amount = $pv['amount'];
                if ($pv['currency_id'] && $pv['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $pv['currency_id'], $target_currency_id);
                }
                $total_debit += $amount;
            }
            
            $stmt = $pdo->prepare($payment_sql);
            $stmt->execute($payment_params);
            $payment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_credit = 0;
            foreach ($payment_data as $pay) {
                $amount = $pay['amount'];
                if ($pay['currency_id'] && $pay['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $pay['currency_id'], $target_currency_id);
                }
                $total_credit += $amount;
            }
            
            $stmt = $pdo->prepare($return_sql);
            $stmt->execute($return_params);
            $return_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($return_data as $ret) {
                $amount = $ret['net_amount'] ?? $ret['total'];
                if (isset($ret['currency_id']) && $ret['currency_id'] && $ret['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $ret['currency_id'], $target_currency_id);
                }
                $total_credit += $amount;
            }
            
            $rent_sql = "SELECT al.credit, rm.currency_id FROM accounting_ledger al JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management' WHERE al.tenant_id = ? AND al.account_id = 2 AND rm.customer_id = ?";
            $rent_params = [$tenant_id, $customer['id']];
            if ($from_date && $to_date) {
                $rent_sql .= " AND al.date BETWEEN ? AND ?";
                $rent_params[] = $from_date;
                $rent_params[] = $to_date;
            }
            $stmt = $pdo->prepare($rent_sql);
            $stmt->execute($rent_params);
            $rent_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rent_data as $rent) {
                $amount = $rent['credit'];
                if ($rent['currency_id'] && $rent['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $rent['currency_id'], $target_currency_id);
                }
                $total_credit += $amount;
            }
            
            // Transfer voucher amounts in date range
            $tv_from_sql = "SELECT amount, currency_id FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id = ?{$company_filter}";
            $tv_from_params = [$tenant_id, $customer['id']];
            if ($company_id) $tv_from_params[] = $company_id;
            $tv_to_sql = "SELECT amount, currency_id FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id = ?{$company_filter}";
            $tv_to_params = [$tenant_id, $customer['id']];
            if ($company_id) $tv_to_params[] = $company_id;
            if ($from_date && $to_date) {
                $tv_from_sql .= " AND voucher_date BETWEEN ? AND ?";
                $tv_from_params[] = $from_date; $tv_from_params[] = $to_date;
                $tv_to_sql .= " AND voucher_date BETWEEN ? AND ?";
                $tv_to_params[] = $from_date; $tv_to_params[] = $to_date;
            }
            $stmt = $pdo->prepare($tv_from_sql); $stmt->execute($tv_from_params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amt = $row['amount'];
                if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                $total_credit += $amt;
            }
            $stmt = $pdo->prepare($tv_to_sql); $stmt->execute($tv_to_params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amt = $row['amount'];
                if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                $total_debit += $amt;
            }

            $closing_balance = $opening_balance + $total_debit - $total_credit;
            
            if ($closing_balance != 0 || $total_debit != 0 || $total_credit != 0) {
                $data[] = [
                    'customer_name' => $customer['customer_name'],
                    'opening_balance' => $opening_balance,
                    'total_debit' => $total_debit,
                    'total_credit' => $total_credit,
                    'closing_balance' => $closing_balance
                ];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        if (!$customer_id) {
            echo json_encode(['success' => false, 'message' => 'Customer ID required for detailed ledger']);
            exit;
        }
        
        $sub_account_id = $_GET['sub_account_id'] ?? null;
        
        // Get customer opening balance
        $stmt = $pdo->prepare("SELECT opening_debit_amount, opening_credit_amount FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$customer_id, $tenant_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Convert opening balance to target currency
        $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
        $stmt->execute([$tenant_id]);
        $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
        $base_currency_id = $baseCurrency['currency_id'];
        
        $opening_debit = $customer['opening_debit_amount'];
        $opening_credit = $customer['opening_credit_amount'];
        
        if ($base_currency_id != $target_currency_id) {
            $opening_debit = $converter->convert($opening_debit, $base_currency_id, $target_currency_id);
            $opening_credit = $converter->convert($opening_credit, $base_currency_id, $target_currency_id);
        }
        
        $base_opening = $opening_debit - $opening_credit;
        
        // Get sub account opening balances
        $stmt = $pdo->prepare("SELECT id, sub_account_name, debit, credit FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $sub_accounts_opening = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sub_opening_map = [];
        $total_sub_opening = 0;
        foreach ($sub_accounts_opening as $sub) {
            $sub_debit = $sub['debit'];
            $sub_credit = $sub['credit'];
            
            // Convert sub account opening to target currency
            if ($base_currency_id != $target_currency_id) {
                $sub_debit = $converter->convert($sub_debit, $base_currency_id, $target_currency_id);
                $sub_credit = $converter->convert($sub_credit, $base_currency_id, $target_currency_id);
            }
            
            $sub_balance = $sub_debit - $sub_credit;
            $sub_opening_map[$sub['id']] = $sub_balance;
            $total_sub_opening += $sub_balance;
        }
        $main_account_opening = $base_opening - $total_sub_opening;
        
        // Calculate soft opening balance if from_date is provided
        $opening_balance = $base_opening;
        $company_filter_detail = ($company_id ? " AND company_id = ?" : "");
        if ($from_date) {
            // If filtering by sub account, only calculate for that sub account
            if ($sub_account_id) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND sub_account_id = ?{$company_filter_detail}");
                $params_sub = [$tenant_id, $customer_id, $from_date, $sub_account_id];
                if ($company_id) $params_sub[] = $company_id;
                $stmt->execute($params_sub);
                $prev_invoices_sub = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND customer_id = ? AND voucher_date < ? AND sub_account_id = ?{$company_filter_detail}");
                $params_sub = [$tenant_id, $customer_id, $from_date, $sub_account_id];
                if ($company_id) $params_sub[] = $company_id;
                $stmt->execute($params_sub);
                $prev_payments_sub = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ? AND voucher_date < ? AND COALESCE(NULLIF(customer_sub_account_id, 0), sub_account_id) = ?{$company_filter_detail}");
                $params_sub = [$tenant_id, $customer_id, $from_date, $sub_account_id];
                if ($company_id) $params_sub[] = $company_id;
                $stmt->execute($params_sub);
                $prev_payment_vouchers_sub = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_refunded), 0) as refunded, COALESCE(SUM(net_amount - amount_refunded), 0) as credit FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND status = 'Posted' AND sub_account_id = ?{$company_filter_detail}");
                $params_sub = [$tenant_id, $customer_id, $from_date, $sub_account_id];
                if ($company_id) $params_sub[] = $company_id;
                $stmt->execute($params_sub);
                $prev_returns_sub = $stmt->fetch();
                
                // Get sub account opening from table
                $stmt = $pdo->prepare("SELECT debit, credit FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ? AND id = ?");
                $stmt->execute([$tenant_id, $customer_id, $sub_account_id]);
                $sub_opening = $stmt->fetch();
                $sub_base_opening = ($sub_opening['debit'] ?? 0) - ($sub_opening['credit'] ?? 0);
                
                $opening_balance = $sub_base_opening + $prev_invoices_sub + $prev_returns_sub['refunded'] - $prev_payment_vouchers_sub - $prev_payments_sub - $prev_returns_sub['credit'];
            } else {
            // Main account transactions
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND (sub_account_id IS NULL OR sub_account_id = 0)");
            $stmt->execute([$tenant_id, $customer_id, $from_date]);
            $prev_invoices_main = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND customer_id = ? AND voucher_date < ? AND (sub_account_id IS NULL OR sub_account_id = 0)");
            $stmt->execute([$tenant_id, $customer_id, $from_date]);
            $prev_payments_main = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ? AND voucher_date < ? AND (COALESCE(NULLIF(customer_sub_account_id, 0), sub_account_id) IS NULL OR COALESCE(NULLIF(customer_sub_account_id, 0), sub_account_id) = 0)");
            $stmt->execute([$tenant_id, $customer_id, $from_date]);
            $prev_payment_vouchers_main = $stmt->fetch()['total'];
            
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_refunded), 0) as refunded, COALESCE(SUM(net_amount - amount_refunded), 0) as credit FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND status = 'Posted' AND (sub_account_id IS NULL OR sub_account_id = 0)");
            $stmt->execute([$tenant_id, $customer_id, $from_date]);
            $prev_returns_main = $stmt->fetch();
            
            // Transfer vouchers for main account (no sub_account_id)
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id = ? AND voucher_date < ? AND (from_sub_account_id IS NULL OR from_sub_account_id = 0) GROUP BY currency_id");
            $stmt->execute([$tenant_id, $customer_id, $from_date]);
            $prev_tv_from_main = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amt = $row['total'];
                if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                $prev_tv_from_main += $amt;
            }

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id = ? AND voucher_date < ? AND (to_sub_account_id IS NULL OR to_sub_account_id = 0) GROUP BY currency_id");
            $stmt->execute([$tenant_id, $customer_id, $from_date]);
            $prev_tv_to_main = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amt = $row['total'];
                if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                $prev_tv_to_main += $amt;
            }

            $main_account_opening = $main_account_opening + $prev_invoices_main + $prev_returns_main['refunded'] + $prev_tv_to_main + $prev_payment_vouchers_main - $prev_payments_main - $prev_returns_main['credit'] - $prev_tv_from_main;
            
            // Sub account transactions
            foreach ($sub_opening_map as $sub_id => $sub_balance) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND sub_account_id = ?");
                $stmt->execute([$tenant_id, $customer_id, $from_date, $sub_id]);
                $prev_invoices_sub = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND customer_id = ? AND voucher_date < ? AND sub_account_id = ?");
                $stmt->execute([$tenant_id, $customer_id, $from_date, $sub_id]);
                $prev_payments_sub = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ? AND voucher_date < ? AND COALESCE(NULLIF(customer_sub_account_id, 0), sub_account_id) = ?");
                $stmt->execute([$tenant_id, $customer_id, $from_date, $sub_id]);
                $prev_payment_vouchers_sub = $stmt->fetch()['total'];
                
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_refunded), 0) as refunded, COALESCE(SUM(net_amount - amount_refunded), 0) as credit FROM sale_return WHERE tenant_id = ? AND customer_id = ? AND sale_date < ? AND status = 'Posted' AND sub_account_id = ?");
                $stmt->execute([$tenant_id, $customer_id, $from_date, $sub_id]);
                $prev_returns_sub = $stmt->fetch();
                
                $sub_opening_map[$sub_id] = $sub_balance + $prev_invoices_sub + $prev_returns_sub['refunded'] - $prev_payment_vouchers_sub - $prev_payments_sub - $prev_returns_sub['credit'];
            }
            
            $opening_balance = $main_account_opening + array_sum($sub_opening_map);
            }
        } else {
            // If filtering by sub account without date filter, use sub account opening
            if ($sub_account_id) {
                $stmt = $pdo->prepare("SELECT debit, credit FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ? AND id = ?");
                $stmt->execute([$tenant_id, $customer_id, $sub_account_id]);
                $sub_opening = $stmt->fetch();
                $opening_balance = ($sub_opening['debit'] ?? 0) - ($sub_opening['credit'] ?? 0);
            }
        }
        
        // Get sale invoices
        $company_filter = ($company_id ? " AND company_id = ?" : "");
        if ($distribution_id) {
            $sql = "SELECT si.id, si.sale_date as date, CONCAT('Sale Invoice - ', si.bill_no) as description, 
                    si.bill_no as reference, 
                    SUM(sii.net_amount + 
                        (sii.net_amount / NULLIF(si.total_bill, 0)) * (si.total_discount_amount + si.total_gst_amount + COALESCE(si.shipping_fees, 0))
                    ) as debit, 
                    0 as credit, 'invoice' as type, si.sub_account_id
                    FROM sale_invoice si
                    JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
                    JOIN products p ON sii.product_id = p.id
                    WHERE si.tenant_id = ? AND si.customer_id = ? AND p.vendor_id = ?";
            $params = [$tenant_id, $customer_id, $distribution_id];
        } else {
            $sql = "SELECT id, sale_date as date, CONCAT('Sale Invoice - ', bill_no) as description, 
                    bill_no as reference, net_amount as debit, 0 as credit, 'invoice' as type, sub_account_id, currency_id
                    FROM sale_invoice
                    WHERE tenant_id = ? AND customer_id = ?{$company_filter}";
            $params = [$tenant_id, $customer_id];
            if ($company_id) $params[] = $company_id;
        }
        if ($sub_account_id) {
            $sql .= $distribution_id ? " AND si.sub_account_id = ?" : " AND sub_account_id = ?";
            $params[] = $sub_account_id;
        }
        if ($from_date && $to_date) {
            $sql .= $distribution_id ? " AND si.sale_date BETWEEN ? AND ?" : " AND sale_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        if ($distribution_id) {
            $sql .= " GROUP BY si.id, si.sale_date, si.bill_no, si.sub_account_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get receive vouchers (exclude those linked to PDCs)
        $sql = "SELECT voucher_date as date, CONCAT('Receipt - ', voucher_number) as description,
                voucher_number as reference, 0 as debit, amount as credit, 'payment' as type, sub_account_id, currency_id
                FROM receive_voucher 
                WHERE tenant_id = ? AND customer_id = ?{$company_filter}
                AND id NOT IN (
                    SELECT reference_id FROM post_dated_cheques 
                    WHERE tenant_id = ? AND reference_table = 'receive_voucher'
                )";
        $params = [$tenant_id, $customer_id];
        if ($company_id) $params[] = $company_id;
        $params[] = $tenant_id;
        if ($sub_account_id) {
            $sql .= " AND sub_account_id = ?";
            $params[] = $sub_account_id;
        }
        if ($from_date && $to_date) {
            $sql .= " AND voucher_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment vouchers
        $sql = "SELECT id, voucher_date as date, CONCAT('Payment - ', voucher_number) as description,
                voucher_number as reference, amount as debit, 0 as credit, 'payment_voucher' as type, 
                COALESCE(NULLIF(customer_sub_account_id, 0), sub_account_id) as sub_account_id, currency_id
                FROM payment_voucher 
                WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id = ?{$company_filter}";
        $params = [$tenant_id, $customer_id];
        if ($company_id) $params[] = $company_id;
        if ($sub_account_id) {
            $sql .= " AND COALESCE(NULLIF(customer_sub_account_id, 0), sub_account_id) = ?";
            $params[] = $sub_account_id;
        }
        if ($from_date && $to_date) {
            $sql .= " AND voucher_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payment_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payments = array_merge($payments, $payment_vouchers);
        
        // Get sale returns
        if ($distribution_id) {
            $sql = "SELECT sr.id, sr.sale_date as date, CONCAT('Sale Return - ', sr.bill_no) as description,
                    sr.bill_no as reference, 0 as debit, 
                    SUM(sri.net_amount + 
                        (sri.net_amount / NULLIF(sr.total_bill, 0)) * (sr.total_discount_amount + sr.total_gst_amount + COALESCE(sr.shipping_fees, 0))
                    ) as credit, 
                    'return' as type, sr.sub_account_id
                    FROM sale_return sr
                    JOIN sale_return_items sri ON sr.id = sri.sale_invoice_id
                    JOIN products p ON sri.product_id = p.id
                    WHERE sr.tenant_id = ? AND sr.customer_id = ? AND sr.status = 'Posted' AND p.vendor_id = ?";
            $params = [$tenant_id, $customer_id, $distribution_id];
        } else {
            $sql = "SELECT id, sale_date as date, CONCAT('Sale Return - ', bill_no) as description,
                    bill_no as reference, amount_refunded as debit, (net_amount - amount_refunded) as credit, 'return' as type, sub_account_id, currency_id
                    FROM sale_return
                    WHERE tenant_id = ? AND customer_id = ? AND status = 'Posted'{$company_filter}";
            $params = [$tenant_id, $customer_id];
            if ($company_id) $params[] = $company_id;
        }
        if ($sub_account_id) {
            $sql .= $distribution_id ? " AND sr.sub_account_id = ?" : " AND sub_account_id = ?";
            $params[] = $sub_account_id;
        }
        if ($from_date && $to_date) {
            $sql .= $distribution_id ? " AND sr.sale_date BETWEEN ? AND ?" : " AND sale_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        if ($distribution_id) {
            $sql .= " GROUP BY sr.id, sr.sale_date, sr.bill_no, sr.sub_account_id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get PDCs with currency from receive_voucher
        $sql = "SELECT pdc.cheque_date as date, 
                CONCAT('PDC ', pdc.cheque_no, ' (', pdc.status, ')') as description,
                pdc.cheque_no as reference, 
                0 as debit, 
                CASE WHEN pdc.status = 'Approved' THEN pdc.amount ELSE 0 END as credit,
                pdc.amount as pdc_amount,
                pdc.status as pdc_status,
                rv.currency_id,
                'pdc' as type
                FROM post_dated_cheques pdc
                LEFT JOIN receive_voucher rv ON pdc.reference_id = rv.id AND pdc.reference_table = 'receive_voucher'
                WHERE pdc.tenant_id = ? AND pdc.customer_id = ? AND pdc.transaction_type = 'Received'";
        $params = [$tenant_id, $customer_id];
        if ($company_id) {
            $sql .= " AND rv.company_id = ?";
            $params[] = $company_id;
        }
        if ($from_date && $to_date) {
            $sql .= " AND pdc.cheque_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get rent transactions with currency
        $sql = "SELECT al.date, al.description, 
                CONCAT('RENT-', rm.id) as reference,
                0 as debit, al.debit as credit, 'rent' as type, null as sub_account_id, rm.currency_id
                FROM accounting_ledger al
                JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management'
                WHERE al.tenant_id = ? AND al.account_id IN (1, 7) AND rm.customer_id = ? AND al.debit > 0";
        $params = [$tenant_id, $customer_id];
        if ($from_date && $to_date) {
            $sql .= " AND al.date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rent_cash_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get rent transactions from accounting_ledger (only credit entries - payment received)
        $sql = "SELECT al.date, al.description, 
                CONCAT('RENT-', rm.id) as reference,
                al.debit, al.credit, 'rent' as type, null as sub_account_id, rm.currency_id
                FROM accounting_ledger al
                JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management'
                WHERE al.tenant_id = ? AND al.account_id = 2 AND rm.customer_id = ? AND al.credit > 0";
        $params = [$tenant_id, $customer_id];
        if ($from_date && $to_date) {
            $sql .= " AND al.date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get transfer vouchers — FROM this customer (credit), TO this customer (debit)
        $tv_company_filter = ($company_id ? " AND company_id = ?" : "");
        $sql = "SELECT voucher_date as date,
                CONCAT('Transfer Out - ', voucher_number) as description,
                voucher_number as reference,
                0 as debit, amount as credit, 'transfer_out' as type, null as sub_account_id, currency_id
                FROM transfer_voucher
                WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id = ?{$tv_company_filter}";
        $params = [$tenant_id, $customer_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) { $sql .= " AND voucher_date BETWEEN ? AND ?"; $params[] = $from_date; $params[] = $to_date; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $tv_out = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT voucher_date as date,
                CONCAT('Transfer In - ', voucher_number) as description,
                voucher_number as reference,
                amount as debit, 0 as credit, 'transfer_in' as type, null as sub_account_id, currency_id
                FROM transfer_voucher
                WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id = ?{$tv_company_filter}";
        $params = [$tenant_id, $customer_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) { $sql .= " AND voucher_date BETWEEN ? AND ?"; $params[] = $from_date; $params[] = $to_date; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $tv_in = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Combine and sort transactions
        $transactions = array_merge($invoices, $payments, $returns, $pdcs, $rent_cash_transactions, $tv_out, $tv_in);
        
        // Get sub accounts
        $stmt = $pdo->prepare("SELECT id, sub_account_name FROM customer_sub_accounts WHERE tenant_id = ? AND customer_id = ?");
        $stmt->execute([$tenant_id, $customer_id]);
        $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group transactions by sub_account_id
        $grouped_transactions = [];
        foreach ($transactions as $transaction) {
            $sub_id = $transaction['sub_account_id'] ?? null;
            if (!isset($grouped_transactions[$sub_id])) {
                $grouped_transactions[$sub_id] = [];
            }
            $grouped_transactions[$sub_id][] = $transaction;
        }
        
        // Sort each group by date
        foreach ($grouped_transactions as &$group) {
            usort($group, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
        }
        
        // Build hierarchical structure
        $result = [];
        $running_balance = $opening_balance;
        
        // Add main account opening balance (only if not filtering by sub account)
        if (!$sub_account_id) {
            // Fetch individual opening balance invoices
            $ob_stmt = $pdo->prepare("SELECT invoice_number, debit, invoice_date FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ? ORDER BY invoice_date ASC, id ASC");
            $ob_stmt->execute([$tenant_id, $customer_id]);
            $ob_invoices = $ob_stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($from_date) {
                // When date filter is set, show single Soft Opening Balance row
                if ($main_account_opening != 0) {
                    $result[] = [
                        'date' => $from_date,
                        'description' => 'Soft Opening Balance',
                        'reference' => 'OB-MAIN',
                        'debit' => 0,
                        'credit' => 0,
                        'running_balance' => $main_account_opening,
                        'type' => 'opening_balance',
                        'sub_account_id' => null
                    ];
                }
                $running_balance = $main_account_opening;
            } elseif (!empty($ob_invoices)) {
                // No date filter: show individual OB invoice rows
                $ob_running = 0;
                foreach ($ob_invoices as $obi) {
                    $ob_amount = floatval($obi['debit']);
                    $ob_running += $ob_amount;
                    $result[] = [
                        'date' => $obi['invoice_date'] ?: 'Opening Balance',
                        'description' => 'Opening Balance',
                        'reference' => $obi['invoice_number'] ?: 'OB-MAIN',
                        'debit' => $ob_amount,
                        'credit' => 0,
                        'running_balance' => $ob_running,
                        'type' => 'opening_balance',
                        'sub_account_id' => null
                    ];
                }
                $running_balance = $main_account_opening;
            } elseif ($main_account_opening != 0) {
                // Fallback: single OB row
                $result[] = [
                    'date' => 'Opening Balance',
                    'description' => 'Opening Balance',
                    'reference' => 'OB-MAIN',
                    'debit' => 0,
                    'credit' => 0,
                    'running_balance' => $main_account_opening,
                    'type' => 'opening_balance',
                    'sub_account_id' => null
                ];
                $running_balance = $main_account_opening;
            }
        }
        
        // Process main account transactions (sub_account_id = null) - only if not filtering by sub account
        if (!$sub_account_id && isset($grouped_transactions[null])) {
            // Initialize running balance for main account if not already set
            if (!isset($running_balance) || $running_balance == $opening_balance) {
                $running_balance = $main_account_opening;
            }
            
            foreach ($grouped_transactions[null] as &$transaction) {
                // Convert currency if needed
                if (isset($transaction['currency_id']) && $transaction['currency_id'] && $transaction['currency_id'] != $target_currency_id) {
                    if ($transaction['debit'] > 0) {
                        $transaction['debit'] = $converter->convert($transaction['debit'], $transaction['currency_id'], $target_currency_id);
                    }
                    if ($transaction['credit'] > 0) {
                        $transaction['credit'] = $converter->convert($transaction['credit'], $transaction['currency_id'], $target_currency_id);
                    }
                    if (isset($transaction['pdc_amount'])) {
                        $transaction['pdc_amount'] = $converter->convert($transaction['pdc_amount'], $transaction['currency_id'], $target_currency_id);
                    }
                }
                
                $running_balance += $transaction['debit'] - $transaction['credit'];
                $transaction['running_balance'] = $running_balance;
                
                if (isset($transaction['pdc_status']) && $transaction['pdc_status'] !== 'Approved') {
                    $transaction['pdc_display'] = $transaction['pdc_amount'];
                }
                
                // Fetch items
                if ($transaction['type'] === 'invoice') {
                    $items_sql = "SELECT p.name as product_name, sii.quantity, u.uom_name, sii.sale_price, sii.net_amount
                                 FROM sale_invoice_items sii
                                 JOIN products p ON sii.product_id = p.id
                                 LEFT JOIN uom u ON sii.uom_id = u.id
                                 WHERE sii.sale_invoice_id = ?";
                    if ($distribution_id) {
                        $items_sql .= " AND p.vendor_id = ?";
                        $items_stmt = $pdo->prepare($items_sql);
                        $items_stmt->execute([$transaction['id'], $distribution_id]);
                    } else {
                        $items_stmt = $pdo->prepare($items_sql);
                        $items_stmt->execute([$transaction['id']]);
                    }
                    $transaction['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                } elseif ($transaction['type'] === 'return') {
                    $items_sql = "SELECT p.name as product_name, sri.quantity, u.uom_name, sri.sale_price, sri.net_amount
                                 FROM sale_return_items sri
                                 JOIN products p ON sri.product_id = p.id
                                 LEFT JOIN uom u ON sri.uom_id = u.id
                                 WHERE sri.sale_invoice_id = ?";
                    if ($distribution_id) {
                        $items_sql .= " AND p.vendor_id = ?";
                        $items_stmt = $pdo->prepare($items_sql);
                        $items_stmt->execute([$transaction['id'], $distribution_id]);
                    } else {
                        $items_stmt = $pdo->prepare($items_sql);
                        $items_stmt->execute([$transaction['id']]);
                    }
                    $transaction['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $result[] = $transaction;
            }
        }
        
        // Process sub accounts
        foreach ($sub_accounts as $sub_account) {
            $sub_id = $sub_account['id'];
            
            // Skip if filtering by specific sub account and this is not it
            if ($sub_account_id && $sub_id != $sub_account_id) {
                continue;
            }
            
            $has_transactions = isset($grouped_transactions[$sub_id]);
            $has_opening = isset($sub_opening_map[$sub_id]) && $sub_opening_map[$sub_id] != 0;
            
            // If filtering by specific sub account, initialize running balance
            if ($sub_account_id && $sub_id == $sub_account_id) {
                $running_balance = $sub_opening_map[$sub_id] ?? 0;
            }
            
            if ($has_transactions || $has_opening) {
                // Add sub account header
                $result[] = [
                    'type' => 'sub_account_header',
                    'sub_account_name' => $sub_account['sub_account_name'],
                    'sub_account_id' => $sub_id
                ];
                
                // Reset running balance to sub account opening
                $sub_account_opening_balance = $sub_opening_map[$sub_id] ?? 0;
                $running_balance = $sub_account_opening_balance;
                
                // Add sub account opening balance
                if ($has_opening) {
                    $result[] = [
                        'date' => $from_date ?: 'Opening Balance',
                        'description' => $from_date ? 'Soft Opening Balance' : 'Opening Balance',
                        'reference' => 'OB-SUB-' . $sub_id,
                        'debit' => 0,
                        'credit' => 0,
                        'running_balance' => $sub_account_opening_balance,
                        'type' => 'opening_balance',
                        'sub_account_id' => $sub_id
                    ];
                }
                
                if ($has_transactions) {
                
                $subTotalDebit = 0;
                $subTotalCredit = 0;
                
                foreach ($grouped_transactions[$sub_id] as &$transaction) {
                    // Convert currency if needed
                    if (isset($transaction['currency_id']) && $transaction['currency_id'] && $transaction['currency_id'] != $target_currency_id) {
                        if ($transaction['debit'] > 0) {
                            $transaction['debit'] = $converter->convert($transaction['debit'], $transaction['currency_id'], $target_currency_id);
                        }
                        if ($transaction['credit'] > 0) {
                            $transaction['credit'] = $converter->convert($transaction['credit'], $transaction['currency_id'], $target_currency_id);
                        }
                        if (isset($transaction['pdc_amount'])) {
                            $transaction['pdc_amount'] = $converter->convert($transaction['pdc_amount'], $transaction['currency_id'], $target_currency_id);
                        }
                    }
                    
                    $running_balance += $transaction['debit'] - $transaction['credit'];
                    $transaction['running_balance'] = $running_balance;
                    
                    $subTotalDebit += $transaction['debit'];
                    $subTotalCredit += $transaction['credit'];
                    
                    if (isset($transaction['pdc_status']) && $transaction['pdc_status'] !== 'Approved') {
                        $transaction['pdc_display'] = $transaction['pdc_amount'];
                    }
                    
                    // Fetch items
                    if ($transaction['type'] === 'invoice') {
                        $items_sql = "SELECT p.name as product_name, sii.quantity, u.uom_name, sii.sale_price, sii.net_amount
                                     FROM sale_invoice_items sii
                                     JOIN products p ON sii.product_id = p.id
                                     LEFT JOIN uom u ON sii.uom_id = u.id
                                     WHERE sii.sale_invoice_id = ?";
                        if ($distribution_id) {
                            $items_sql .= " AND p.vendor_id = ?";
                            $items_stmt = $pdo->prepare($items_sql);
                            $items_stmt->execute([$transaction['id'], $distribution_id]);
                        } else {
                            $items_stmt = $pdo->prepare($items_sql);
                            $items_stmt->execute([$transaction['id']]);
                        }
                        $transaction['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                    } elseif ($transaction['type'] === 'return') {
                        $items_sql = "SELECT p.name as product_name, sri.quantity, u.uom_name, sri.sale_price, sri.net_amount
                                     FROM sale_return_items sri
                                     JOIN products p ON sri.product_id = p.id
                                     LEFT JOIN uom u ON sri.uom_id = u.id
                                     WHERE sri.sale_invoice_id = ?";
                        if ($distribution_id) {
                            $items_sql .= " AND p.vendor_id = ?";
                            $items_stmt = $pdo->prepare($items_sql);
                            $items_stmt->execute([$transaction['id'], $distribution_id]);
                        } else {
                            $items_stmt = $pdo->prepare($items_sql);
                            $items_stmt->execute([$transaction['id']]);
                        }
                        $transaction['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    $result[] = $transaction;
                }
                
                // Add sub account totals only if not filtering by specific sub account
                if (!$sub_account_id) {
                    $result[] = [
                        'type' => 'sub_account_total',
                        'sub_account_name' => $sub_account['sub_account_name'],
                        'total_debit' => $subTotalDebit,
                        'total_credit' => $subTotalCredit,
                        'running_balance' => $running_balance
                    ];
                }
                }
            }
        }
        
        echo json_encode(['success' => true, 'data' => $result, 'opening_balance' => $opening_balance]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}