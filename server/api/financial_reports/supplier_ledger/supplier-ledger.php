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
        $stmt = $pdo->prepare("SELECT c.id, c.code, c.name, c.symbol, tc.is_base_currency FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_active = 1 ORDER BY tc.is_base_currency DESC, c.name");
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
            $base_opening_debit = $supplier['opening_debit_amount'];
            $base_opening_credit = $supplier['opening_credit_amount'];
            
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
            if ($from_date) {
                $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
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
                
                $stmt = $pdo->prepare("SELECT amount, currency_id FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND voucher_date < ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date, $tenant_id];
                if ($company_id) $params_prev[] = $company_id;
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
                
                $stmt = $pdo->prepare("SELECT pdc.amount, pv.currency_id FROM post_dated_cheques pdc LEFT JOIN payment_voucher pv ON pdc.reference_id = pv.id AND pdc.reference_table = 'payment_voucher' WHERE pdc.tenant_id = ? AND pdc.supplier_id = ? AND pdc.transaction_type = 'Payment' AND pdc.status = 'Approved' AND pdc.cheque_date < ?" . ($company_id ? " AND pdc.company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_pdcs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_pdcs = 0;
                foreach ($prev_pdcs_data as $pdc) {
                    $amount = $pdc['amount'];
                    if ($pdc['currency_id'] && $pdc['currency_id'] != $target_currency_id) {
                        $amount = $converter->convert($amount, $pdc['currency_id'], $target_currency_id);
                    }
                    $prev_pdcs += $amount;
                }
                
                $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_return WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_returns_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $prev_returns = 0;
                foreach ($prev_returns_data as $ret) {
                    $amount = $ret['net_amount'];
                    if ($ret['currency_id'] && $ret['currency_id'] != $target_currency_id) {
                        $amount = $converter->convert($amount, $ret['currency_id'], $target_currency_id);
                    }
                    $prev_returns += $amount;
                }
                
                // Supplier = Credit-normal: tv_out (FROM) = credit (payable+), tv_in (TO) = debit (payable-)
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'supplier' AND from_supplier_id = ? AND voucher_date < ?" . ($company_id ? " AND company_id = ?" : "") . " GROUP BY currency_id");
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_tv_from = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $amt = $row['total'];
                    if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                    $prev_tv_from += $amt;
                }

                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'supplier' AND to_supplier_id = ? AND voucher_date < ?" . ($company_id ? " AND company_id = ?" : "") . " GROUP BY currency_id");
                $params_prev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_prev[] = $company_id;
                $stmt->execute($params_prev);
                $prev_tv_to = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $amt = $row['total'];
                    if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                    $prev_tv_to += $amt;
                }

                // Expense vouchers before from_date
                $stmt = $pdo->prepare("SELECT total_amount, type FROM expense_voucher WHERE tenant_id = ? AND vendor_id = ? AND date < ?" . ($company_id ? " AND company_id = ?" : ""));
                $params_ev = [$tenant_id, $supplier['id'], $from_date];
                if ($company_id) $params_ev[] = $company_id;
                $stmt->execute($params_ev);
                $prev_ev_dr = 0; $prev_ev_cr = 0;
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
                    if ($ev['type'] === 'DR') $prev_ev_dr += floatval($ev['total_amount']);
                    else $prev_ev_cr += floatval($ev['total_amount']);
                }
                $opening_balance = $base_opening - $prev_invoices + $prev_payments + $prev_pdcs + $prev_returns - $prev_tv_from + $prev_tv_to + $prev_ev_dr - $prev_ev_cr;
            }
            $invoice_sql = "SELECT pi.net_amount, pi.currency_id FROM purchase_invoice pi WHERE pi.tenant_id = ? AND pi.supplier_id = ?" . ($company_id ? " AND pi.company_id = ?" : "");
            $payment_sql = "SELECT pv.amount, pv.currency_id FROM payment_voucher pv WHERE pv.tenant_id = ? AND pv.supplier_id = ? AND pv.id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND pv.company_id = ?" : "");
            $return_sql = "SELECT pr.net_amount, pr.currency_id FROM purchase_return pr WHERE pr.tenant_id = ? AND pr.supplier_id = ?" . ($company_id ? " AND pr.company_id = ?" : "");
            
            $invoice_params = [$tenant_id, $supplier['id']];
            if ($company_id) $invoice_params[] = $company_id;
            $payment_params = [$tenant_id, $supplier['id'], $tenant_id];
            if ($company_id) $payment_params[] = $company_id;
            $return_params = [$tenant_id, $supplier['id']];
            if ($company_id) $return_params[] = $company_id;
            
            if ($from_date && $to_date) {
                $invoice_sql .= " AND purchase_date BETWEEN ? AND ?";
                $payment_sql .= " AND voucher_date BETWEEN ? AND ?";
                $return_sql .= " AND purchase_date BETWEEN ? AND ?";
                $invoice_params[] = $from_date;
                $invoice_params[] = $to_date;
                $payment_params[] = $from_date;
                $payment_params[] = $to_date;
                $return_params[] = $from_date;
                $return_params[] = $to_date;
            }
            
            // Get invoices and convert currency
            $stmt = $pdo->prepare($invoice_sql);
            $stmt->execute($invoice_params);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_credit = 0;
            foreach ($invoices as $inv) {
                $amount = $inv['net_amount'];
                if ($inv['currency_id'] && $inv['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $inv['currency_id'], $target_currency_id);
                }
                $total_credit += $amount;
            }
            
            // Get payments and convert currency
            $stmt = $pdo->prepare($payment_sql);
            $stmt->execute($payment_params);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_debit = 0;
            foreach ($payments as $pay) {
                $amount = $pay['amount'];
                if ($pay['currency_id'] && $pay['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $pay['currency_id'], $target_currency_id);
                }
                $total_debit += $amount;
            }
            
            // Get purchase returns and convert currency
            $stmt = $pdo->prepare($return_sql);
            $stmt->execute($return_params);
            $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_returns = 0;
            foreach ($returns as $ret) {
                $amount = $ret['net_amount'];
                if ($ret['currency_id'] && $ret['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $ret['currency_id'], $target_currency_id);
                }
                $total_returns += $amount;
            }
            
            // Get approved PDCs with currency conversion
            $pdc_sql = "SELECT pdc.amount, pv.currency_id FROM post_dated_cheques pdc LEFT JOIN payment_voucher pv ON pdc.reference_id = pv.id AND pdc.reference_table = 'payment_voucher' WHERE pdc.tenant_id = ? AND pdc.supplier_id = ? AND pdc.transaction_type = 'Payment' AND pdc.status = 'Approved'" . ($company_id ? " AND pdc.company_id = ?" : "");
            $pdc_params = [$tenant_id, $supplier['id']];
            if ($company_id) $pdc_params[] = $company_id;
            
            if ($from_date && $to_date) {
                $pdc_sql .= " AND pdc.cheque_date BETWEEN ? AND ?";
                $pdc_params[] = $from_date;
                $pdc_params[] = $to_date;
            }
            
            $stmt = $pdo->prepare($pdc_sql);
            $stmt->execute($pdc_params);
            $pdcs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_pdc = 0;
            foreach ($pdcs_data as $pdc) {
                $amount = $pdc['amount'];
                if ($pdc['currency_id'] && $pdc['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $pdc['currency_id'], $target_currency_id);
                }
                $total_pdc += $amount;
            }
            
            // Transfer voucher amounts in date range
            $tv_from_sql = "SELECT amount, currency_id FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'supplier' AND from_supplier_id = ?" . ($company_id ? " AND company_id = ?" : "");
            $tv_from_params = [$tenant_id, $supplier['id']];
            if ($company_id) $tv_from_params[] = $company_id;
            $tv_to_sql = "SELECT amount, currency_id FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'supplier' AND to_supplier_id = ?" . ($company_id ? " AND company_id = ?" : "");
            $tv_to_params = [$tenant_id, $supplier['id']];
            if ($company_id) $tv_to_params[] = $company_id;
            if ($from_date && $to_date) {
                $tv_from_sql .= " AND voucher_date BETWEEN ? AND ?";
                $tv_from_params[] = $from_date; $tv_from_params[] = $to_date;
                $tv_to_sql .= " AND voucher_date BETWEEN ? AND ?";
                $tv_to_params[] = $from_date; $tv_to_params[] = $to_date;
            }
            // tv_out (FROM supplier) = credit (payable increases), tv_in (TO supplier) = debit (payable reduces)
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

            // Expense vouchers in period
            $ev_period_sql = "SELECT total_amount, type FROM expense_voucher WHERE tenant_id = ? AND vendor_id = ?" . ($company_id ? " AND company_id = ?" : "");
            $ev_period_params = [$tenant_id, $supplier['id']];
            if ($company_id) $ev_period_params[] = $company_id;
            if ($from_date && $to_date) { $ev_period_sql .= " AND date BETWEEN ? AND ?"; $ev_period_params[] = $from_date; $ev_period_params[] = $to_date; }
            $stmt = $pdo->prepare($ev_period_sql); $stmt->execute($ev_period_params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
                if ($ev['type'] === 'DR') $total_debit += floatval($ev['total_amount']);
                else $total_credit += floatval($ev['total_amount']);
            }

            // Journal voucher adjustments for this supplier (single + cross-party)
            $adj_stmt = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN jv.adj_mode='single' OR (jv.adj_mode='cross' AND jv.party_type='supplier' AND jv.party_id=?
                        AND jvl.id=(SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=14))
                        OR (jv.adj_mode='cross' AND jv.party_type2='supplier' AND jv.party_id2=?
                        AND jvl.id=(SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=14))
                        THEN jvl.debit ELSE 0 END), 0) as adj_debit,
                    COALESCE(SUM(CASE WHEN jv.adj_mode='single' OR (jv.adj_mode='cross' AND jv.party_type='supplier' AND jv.party_id=?
                        AND jvl.id=(SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=14))
                        OR (jv.adj_mode='cross' AND jv.party_type2='supplier' AND jv.party_id2=?
                        AND jvl.id=(SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=14))
                        THEN jvl.credit ELSE 0 END), 0) as adj_credit
                 FROM journal_voucher jv
                 JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 14
                 WHERE jv.tenant_id = ? AND jv.status = 'posted'
                   AND (
                       (jv.adj_mode = 'single' AND jv.party_type = 'supplier' AND jv.party_id = ?)
                       OR (jv.adj_mode = 'cross' AND jv.party_type = 'supplier' AND jv.party_id = ?)
                       OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'supplier' AND jv.party_id2 = ?)
                   )"
            );
            $adj_stmt->execute([
                $supplier['id'], $supplier['id'],
                $supplier['id'], $supplier['id'],
                $tenant_id,
                $supplier['id'], $supplier['id'], $supplier['id']
            ]);
            $adj_row = $adj_stmt->fetch(PDO::FETCH_ASSOC);
            $adj_net = floatval($adj_row['adj_debit']) - floatval($adj_row['adj_credit']);

            $total_debit = $total_debit + $total_pdc;
            $closing_balance = $opening_balance - $total_credit + $total_debit + $total_returns + $adj_net;
            
            $data[] = [
                'supplier_name' => $supplier['supplier_name'],
                'opening_balance' => $opening_balance,
                'total_debit' => $total_debit,
                'total_credit' => $total_credit,
                'closing_balance' => $closing_balance
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $data, 'target_currency_id' => $target_currency_id]);
    } else {
        if (!$supplier_id) {
            echo json_encode(['success' => false, 'message' => 'Supplier ID required for detailed ledger']);
            exit;
        }
        
        // Get supplier opening balance
        $stmt = $pdo->prepare("SELECT opening_debit_amount, opening_credit_amount FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$supplier_id, $tenant_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Convert opening balance to target currency
        $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
        $stmt->execute([$tenant_id]);
        $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
        $base_currency_id = $baseCurrency['currency_id'];
        
        $opening_debit = $supplier['opening_debit_amount'];
        $opening_credit = $supplier['opening_credit_amount'];
        
        if ($base_currency_id != $target_currency_id) {
            $opening_debit = $converter->convert($opening_debit, $base_currency_id, $target_currency_id);
            $opening_credit = $converter->convert($opening_credit, $base_currency_id, $target_currency_id);
        }
        
        $base_opening = $opening_debit - $opening_credit;
        
        // Get sub accounts with opening balances
        $stmt = $pdo->prepare("SELECT id, sub_account_name, debit, credit FROM supplier_sub_accounts WHERE tenant_id = ? AND supplier_id = ? ORDER BY sub_account_name");
        $stmt->execute([$tenant_id, $supplier_id]);
        $sub_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate soft opening balance if from_date is provided
        $opening_balance = $base_opening;
        if ($from_date) {
            // Get transactions before from_date with currency conversion
            $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date];
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
            
            $stmt = $pdo->prepare("SELECT amount, currency_id FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND voucher_date < ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date, $tenant_id];
            if ($company_id) $params_prev[] = $company_id;
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
            
            $stmt = $pdo->prepare("SELECT pdc.amount, pv.currency_id FROM post_dated_cheques pdc LEFT JOIN payment_voucher pv ON pdc.reference_id = pv.id AND pdc.reference_table = 'payment_voucher' WHERE pdc.tenant_id = ? AND pdc.supplier_id = ? AND pdc.transaction_type = 'Payment' AND pdc.status = 'Approved' AND pdc.cheque_date < ?" . ($company_id ? " AND pdc.company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_pdcs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $prev_pdcs = 0;
            foreach ($prev_pdcs_data as $pdc) {
                $amount = $pdc['amount'];
                if ($pdc['currency_id'] && $pdc['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $pdc['currency_id'], $target_currency_id);
                }
                $prev_pdcs += $amount;
            }
            
            $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_return WHERE tenant_id = ? AND supplier_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_returns_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $prev_returns = 0;
            foreach ($prev_returns_data as $ret) {
                $amount = $ret['net_amount'];
                if ($ret['currency_id'] && $ret['currency_id'] != $target_currency_id) {
                    $amount = $converter->convert($amount, $ret['currency_id'], $target_currency_id);
                }
                $prev_returns += $amount;
            }
            
            // Supplier = Credit-normal: tv_out (FROM) = credit (payable+), tv_in (TO) = debit (payable-)
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'supplier' AND from_supplier_id = ? AND voucher_date < ?" . ($company_id ? " AND company_id = ?" : "") . " GROUP BY currency_id");
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_tv_from = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amt = $row['total'];
                if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                $prev_tv_from += $amt;
            }

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total, currency_id FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'supplier' AND to_supplier_id = ? AND voucher_date < ?" . ($company_id ? " AND company_id = ?" : "") . " GROUP BY currency_id");
            $params_prev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_prev[] = $company_id;
            $stmt->execute($params_prev);
            $prev_tv_to = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $amt = $row['total'];
                if ($row['currency_id'] && $row['currency_id'] != $target_currency_id) $amt = $converter->convert($amt, $row['currency_id'], $target_currency_id);
                $prev_tv_to += $amt;
            }

            // Expense vouchers before from_date for detailed opening
            $stmt = $pdo->prepare("SELECT total_amount, type FROM expense_voucher WHERE tenant_id = ? AND vendor_id = ? AND date < ?" . ($company_id ? " AND company_id = ?" : ""));
            $params_ev = [$tenant_id, $supplier_id, $from_date];
            if ($company_id) $params_ev[] = $company_id;
            $stmt->execute($params_ev);
            $prev_ev_dr = 0; $prev_ev_cr = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
                if ($ev['type'] === 'DR') $prev_ev_dr += floatval($ev['total_amount']);
                else $prev_ev_cr += floatval($ev['total_amount']);
            }

            $opening_balance = $base_opening - $prev_invoices + $prev_payments + $prev_pdcs + $prev_returns - $prev_tv_from + $prev_tv_to + $prev_ev_dr - $prev_ev_cr;
        }
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
        
        // Get payment vouchers (exclude those linked to PDCs) with currency
        $sql = "SELECT pv.voucher_date as date,
                CONCAT('Payment - ', pv.voucher_number,
                    CASE WHEN ev.voucher_no IS NOT NULL
                        THEN CONCAT(' (Exp: ', ev.voucher_no, ')')
                        ELSE ''
                    END
                ) as description,
                pv.voucher_number as reference, pv.amount as debit, 0 as credit, pv.sub_account_id, pv.currency_id
                FROM payment_voucher pv
                LEFT JOIN expense_voucher ev ON pv.expense_voucher_ref_id = ev.id
                WHERE pv.tenant_id = ? AND pv.supplier_id = ?
                AND pv.id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND pv.company_id = ?" : "");
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
        
        $sql = "SELECT pdc.cheque_date as date, 
                CONCAT('PDC - ', pdc.cheque_no, ' (', pdc.status, ')') as description,
                pdc.cheque_no as reference, 
                pdc.amount as debit,
                0 as credit,
                pdc.status as pdc_status,
                pv.currency_id
                FROM post_dated_cheques pdc
                LEFT JOIN payment_voucher pv ON pdc.reference_id = pv.id AND pdc.reference_table = 'payment_voucher'
                WHERE pdc.tenant_id = ? AND pdc.supplier_id = ? AND pdc.transaction_type = 'Payment'" . ($company_id ? " AND pdc.company_id = ?" : "");
        $params = [$tenant_id, $supplier_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) {
            $sql .= " AND pdc.cheque_date BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
        } elseif ($from_date) {
            $sql .= " AND pdc.cheque_date >= ?";
            $params[] = $from_date;
        } elseif ($to_date) {
            $sql .= " AND pdc.cheque_date <= ?";
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
        
        // Supplier = Credit-normal: Transfer Out (FROM) = credit (payable+), Transfer In (TO) = debit (payable-)
        $tv_company_filter = ($company_id ? " AND company_id = ?" : "");
        $sql = "SELECT voucher_date as date,
                CONCAT('Transfer Out - ', voucher_number) as description,
                voucher_number as reference,
                0 as debit, amount as credit, null as sub_account_id, currency_id
                FROM transfer_voucher
                WHERE tenant_id = ? AND from_type = 'supplier' AND from_supplier_id = ?{$tv_company_filter}";
        $params = [$tenant_id, $supplier_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) { $sql .= " AND voucher_date BETWEEN ? AND ?"; $params[] = $from_date; $params[] = $to_date; }
        elseif ($from_date) { $sql .= " AND voucher_date >= ?"; $params[] = $from_date; }
        elseif ($to_date) { $sql .= " AND voucher_date <= ?"; $params[] = $to_date; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $tv_out = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT voucher_date as date,
                CONCAT('Transfer In - ', voucher_number) as description,
                voucher_number as reference,
                amount as debit, 0 as credit, null as sub_account_id, currency_id
                FROM transfer_voucher
                WHERE tenant_id = ? AND to_type = 'supplier' AND to_supplier_id = ?{$tv_company_filter}";
        $params = [$tenant_id, $supplier_id];
        if ($company_id) $params[] = $company_id;
        if ($from_date && $to_date) { $sql .= " AND voucher_date BETWEEN ? AND ?"; $params[] = $from_date; $params[] = $to_date; }
        elseif ($from_date) { $sql .= " AND voucher_date >= ?"; $params[] = $from_date; }
        elseif ($to_date) { $sql .= " AND voucher_date <= ?"; $params[] = $to_date; }
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $tv_in = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get expense vouchers linked to this supplier
        $ev_sql = "SELECT ev.date, 
                CONCAT('Expense Voucher - ', ev.voucher_no) as description,
                ev.voucher_no as reference,
                CASE WHEN ev.type = 'DR' THEN ev.total_amount ELSE 0 END as debit,
                CASE WHEN ev.type = 'CR' THEN ev.total_amount ELSE 0 END as credit,
                NULL as sub_account_id, NULL as currency_id, NULL as invoice_id, NULL as return_id
                FROM expense_voucher ev
                WHERE ev.tenant_id = ? AND ev.vendor_id = ?" . ($company_id ? " AND ev.company_id = ?" : "");
        $ev_params = [$tenant_id, $supplier_id];
        if ($company_id) $ev_params[] = $company_id;
        if ($from_date && $to_date) { $ev_sql .= " AND ev.date BETWEEN ? AND ?"; $ev_params[] = $from_date; $ev_params[] = $to_date; }
        elseif ($from_date) { $ev_sql .= " AND ev.date >= ?"; $ev_params[] = $from_date; }
        elseif ($to_date) { $ev_sql .= " AND ev.date <= ?"; $ev_params[] = $to_date; }
        $stmt = $pdo->prepare($ev_sql); $stmt->execute($ev_params);
        $expense_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get journal voucher adjustments for this supplier (single + cross-party)
        $adj_sql = "SELECT jv.voucher_date as date,
                CONCAT('Adjustment - ', jv.voucher_number) as description,
                jv.voucher_number as reference,
                jvl.debit, jvl.credit,
                'adjustment' as type, null as sub_account_id, null as currency_id, null as invoice_id, null as return_id,
                jv.description as adj_note,
                CASE
                    WHEN jv.adj_mode = 'cross' AND jv.party_type = 'supplier' AND jv.party_id = ? THEN
                        CONCAT(UPPER(jv.party_type2), ': ', COALESCE(
                            (SELECT customer_name FROM customers WHERE id = jv.party_id2 LIMIT 1),
                            (SELECT supplier_name FROM suppliers WHERE id = jv.party_id2 LIMIT 1)
                        ))
                    WHEN jv.adj_mode = 'cross' AND jv.party_type2 = 'supplier' AND jv.party_id2 = ? THEN
                        CONCAT(UPPER(jv.party_type), ': ', COALESCE(
                            (SELECT customer_name FROM customers WHERE id = jv.party_id LIMIT 1),
                            (SELECT supplier_name FROM suppliers WHERE id = jv.party_id LIMIT 1)
                        ))
                    ELSE (
                        SELECT a.name FROM journal_voucher_line jvl2
                        JOIN accounts a ON a.id = jvl2.account_id
                        WHERE jvl2.voucher_id = jv.id AND jvl2.account_id != 14
                        LIMIT 1
                    )
                END as contra_account_name
                FROM journal_voucher jv
                JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 14
                WHERE jv.tenant_id = ? AND jv.status = 'posted'
                  AND (
                      (jv.adj_mode = 'single' AND jv.party_type = 'supplier' AND jv.party_id = ?)
                      OR
                      (jv.adj_mode = 'cross' AND jv.party_type = 'supplier' AND jv.party_id = ?
                       AND jvl.id = (SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 14))
                      OR
                      (jv.adj_mode = 'cross' AND jv.party_type2 = 'supplier' AND jv.party_id2 = ?
                       AND jvl.id = (SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 14))
                  )";
        $adj_params = [$supplier_id, $supplier_id, $tenant_id, $supplier_id, $supplier_id, $supplier_id];
        if ($from_date && $to_date) {
            $adj_sql .= " AND jv.voucher_date BETWEEN ? AND ?";
            $adj_params[] = $from_date;
            $adj_params[] = $to_date;
        } elseif ($from_date) {
            $adj_sql .= " AND jv.voucher_date >= ?";
            $adj_params[] = $from_date;
        } elseif ($to_date) {
            $adj_sql .= " AND jv.voucher_date <= ?";
            $adj_params[] = $to_date;
        }
        $stmt = $pdo->prepare($adj_sql);
        $stmt->execute($adj_params);
        $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Combine and sort transactions
        $transactions = array_merge($invoices, $payments, $pdcs, $returns, $tv_out, $tv_in, $expense_vouchers, $adjustments);
        
        // Convert currencies for all transactions
        foreach ($transactions as &$transaction) {
            if (isset($transaction['currency_id']) && $transaction['currency_id'] && $transaction['currency_id'] != $target_currency_id) {
                if ($transaction['debit'] > 0) {
                    $transaction['debit'] = $converter->convert($transaction['debit'], $transaction['currency_id'], $target_currency_id);
                }
                if ($transaction['credit'] > 0) {
                    $transaction['credit'] = $converter->convert($transaction['credit'], $transaction['currency_id'], $target_currency_id);
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
                    $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_invoice WHERE tenant_id = ? AND supplier_id = ? AND sub_account_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                    $params_sub = [$tenant_id, $supplier_id, $sub_account_id, $from_date];
                    if ($company_id) $params_sub[] = $company_id;
                    $stmt->execute($params_sub);
                    $prev_inv_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $prev_invoices = 0;
                    foreach ($prev_inv_data as $inv) {
                        $amount = $inv['net_amount'];
                        if ($inv['currency_id'] && $inv['currency_id'] != $target_currency_id) {
                            $amount = $converter->convert($amount, $inv['currency_id'], $target_currency_id);
                        }
                        $prev_invoices += $amount;
                    }
                    
                    $stmt = $pdo->prepare("SELECT amount, currency_id FROM payment_voucher WHERE tenant_id = ? AND supplier_id = ? AND sub_account_id = ? AND voucher_date < ? AND id NOT IN (SELECT reference_id FROM post_dated_cheques WHERE tenant_id = ? AND reference_table = 'payment_voucher')" . ($company_id ? " AND company_id = ?" : ""));
                    $params_sub = [$tenant_id, $supplier_id, $sub_account_id, $from_date, $tenant_id];
                    if ($company_id) $params_sub[] = $company_id;
                    $stmt->execute($params_sub);
                    $prev_pay_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $prev_payments = 0;
                    foreach ($prev_pay_data as $pay) {
                        $amount = $pay['amount'];
                        if ($pay['currency_id'] && $pay['currency_id'] != $target_currency_id) {
                            $amount = $converter->convert($amount, $pay['currency_id'], $target_currency_id);
                        }
                        $prev_payments += $amount;
                    }
                    
                    $stmt = $pdo->prepare("SELECT net_amount, currency_id FROM purchase_return WHERE tenant_id = ? AND supplier_id = ? AND sub_account_id = ? AND purchase_date < ?" . ($company_id ? " AND company_id = ?" : ""));
                    $params_sub = [$tenant_id, $supplier_id, $sub_account_id, $from_date];
                    if ($company_id) $params_sub[] = $company_id;
                    $stmt->execute($params_sub);
                    $prev_ret_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $prev_returns = 0;
                    foreach ($prev_ret_data as $ret) {
                        $amount = $ret['net_amount'];
                        if ($ret['currency_id'] && $ret['currency_id'] != $target_currency_id) {
                            $amount = $converter->convert($amount, $ret['currency_id'], $target_currency_id);
                        }
                        $prev_returns += $amount;
                    }
                    
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
            'target_currency_id' => $target_currency_id
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
