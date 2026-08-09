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
        $params_c = [$tenant_id];
        if ($customer_id) { $sql .= " AND c.id = ?"; $params_c[] = $customer_id; }
        $sql .= " ORDER BY c.customer_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_c);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($customers)) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        // Get base currency once (outside the loop)
        $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
        $stmt->execute([$tenant_id]);
        $base_currency_id = $stmt->fetchColumn();

        $customer_ids = array_column($customers, 'id');
        $in_ph = implode(',', array_fill(0, count($customer_ids), '?'));
        $company_filter = $company_id ? " AND company_id = ?" : "";
        $company_params = $company_id ? [$company_id] : [];

        // Aggregate rows [customer_id, currency_id, total] into [customer_id => converted_total]
        $sumByCid = function(array $rows, string $col = 'total') use ($converter, $target_currency_id): array {
            $out = [];
            foreach ($rows as $r) {
                $cid = $r['customer_id'];
                $amt = floatval($r[$col]);
                if (!empty($r['currency_id']) && $r['currency_id'] != $target_currency_id) {
                    $amt = $converter->convert($amt, $r['currency_id'], $target_currency_id);
                }
                $out[$cid] = ($out[$cid] ?? 0.0) + $amt;
            }
            return $out;
        };

        // ── SOFT OPENING (pre from_date) — one bulk query per data source ──────
        $pre_invoices = $pre_payments = $pre_pv = $pre_rent = $pre_tv_from = $pre_tv_to = [];
        $pre_returns_refunded = $pre_returns_credit = [];

        if ($from_date) {
            $stmt = $pdo->prepare("SELECT customer_id, currency_id, SUM(net_amount) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id IN ($in_ph) AND sale_date < ?{$company_filter} GROUP BY customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date], $company_params));
            $pre_invoices = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("SELECT rv.customer_id, rv.currency_id, SUM(rv.amount) as total FROM receive_voucher rv WHERE rv.tenant_id = ? AND rv.customer_id IN ($in_ph) AND rv.voucher_date < ?{$company_filter} AND rv.id NOT IN (SELECT pdc.reference_id FROM post_dated_cheques pdc WHERE pdc.tenant_id = ? AND pdc.reference_table = 'receive_voucher' AND pdc.status = 'Pending') GROUP BY rv.customer_id, rv.currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date], $company_params, [$tenant_id]));
            $pre_payments = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("SELECT customer_id, currency_id, SUM(amount) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id IN ($in_ph) AND voucher_date < ?{$company_filter} GROUP BY customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date], $company_params));
            $pre_pv = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("SELECT customer_id, currency_id, SUM(amount_refunded) as refunded, SUM(net_amount - amount_refunded) as credit FROM sale_return WHERE tenant_id = ? AND customer_id IN ($in_ph) AND sale_date < ? AND status = 'Posted'{$company_filter} GROUP BY customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date], $company_params));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cid = $r['customer_id'];
                $ref = floatval($r['refunded']); $cred = floatval($r['credit']);
                if (!empty($r['currency_id']) && $r['currency_id'] != $target_currency_id) {
                    $ref  = $converter->convert($ref,  $r['currency_id'], $target_currency_id);
                    $cred = $converter->convert($cred, $r['currency_id'], $target_currency_id);
                }
                $pre_returns_refunded[$cid] = ($pre_returns_refunded[$cid] ?? 0) + $ref;
                $pre_returns_credit[$cid]   = ($pre_returns_credit[$cid]   ?? 0) + $cred;
            }

            $stmt = $pdo->prepare("SELECT rm.customer_id, rm.currency_id, SUM(al.credit) as total FROM accounting_ledger al JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management' WHERE al.tenant_id = ? AND al.account_id = 2 AND rm.customer_id IN ($in_ph) AND al.date < ? GROUP BY rm.customer_id, rm.currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date]));
            $pre_rent = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("SELECT from_customer_id as customer_id, currency_id, SUM(amount) as total FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id IN ($in_ph) AND voucher_date < ?{$company_filter} GROUP BY from_customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date], $company_params));
            $pre_tv_from = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

            $stmt = $pdo->prepare("SELECT to_customer_id as customer_id, currency_id, SUM(amount) as total FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id IN ($in_ph) AND voucher_date < ?{$company_filter} GROUP BY to_customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$from_date], $company_params));
            $pre_tv_to = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // ── PERIOD DATA — one bulk query per data source ───────────────────────
        $date_filter_sale  = ($from_date && $to_date) ? " AND sale_date BETWEEN ? AND ?"    : "";
        $date_filter_vdate = ($from_date && $to_date) ? " AND voucher_date BETWEEN ? AND ?" : "";
        $date_filter_al    = ($from_date && $to_date) ? " AND al.date BETWEEN ? AND ?"      : "";
        $date_params       = ($from_date && $to_date) ? [$from_date, $to_date]              : [];

        if ($distribution_id) {
            $dist_date = ($from_date && $to_date) ? " AND si.sale_date BETWEEN ? AND ?" : "";
            $stmt = $pdo->prepare("SELECT si.customer_id, NULL as currency_id, COALESCE(SUM(sii.net_amount + (sii.net_amount / NULLIF(si.total_bill, 0)) * (si.total_discount_amount + si.total_gst_amount + COALESCE(si.shipping_fees, 0))), 0) as total FROM sale_invoice si JOIN sale_invoice_items sii ON sii.sale_invoice_id = si.id JOIN products p ON sii.product_id = p.id WHERE si.tenant_id = ? AND si.customer_id IN ($in_ph) AND p.vendor_id = ?{$dist_date} GROUP BY si.customer_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$distribution_id], $date_params));
        } else {
            $stmt = $pdo->prepare("SELECT customer_id, currency_id, SUM(net_amount) as total FROM sale_invoice WHERE tenant_id = ? AND customer_id IN ($in_ph){$company_filter}{$date_filter_sale} GROUP BY customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, $company_params, $date_params));
        }
        $period_invoices = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $pdo->prepare("SELECT customer_id, currency_id, SUM(amount) as total FROM payment_voucher WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL AND customer_id IN ($in_ph){$company_filter}{$date_filter_vdate} GROUP BY customer_id, currency_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $company_params, $date_params));
        $period_pv = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $pdo->prepare("SELECT rv.customer_id, rv.currency_id, SUM(rv.amount) as total FROM receive_voucher rv WHERE rv.tenant_id = ? AND rv.customer_id IN ($in_ph){$company_filter}{$date_filter_vdate} AND rv.id NOT IN (SELECT pdc.reference_id FROM post_dated_cheques pdc WHERE pdc.tenant_id = ? AND pdc.reference_table = 'receive_voucher' AND pdc.status = 'Pending') GROUP BY rv.customer_id, rv.currency_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $company_params, $date_params, [$tenant_id]));
        $period_payments = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        if ($distribution_id) {
            $dist_date = ($from_date && $to_date) ? " AND sr.sale_date BETWEEN ? AND ?" : "";
            $stmt = $pdo->prepare("SELECT sr.customer_id, NULL as currency_id, COALESCE(SUM(sri.net_amount + (sri.net_amount / NULLIF(sr.total_bill, 0)) * (sr.total_discount_amount + sr.total_gst_amount + COALESCE(sr.shipping_fees, 0))), 0) as total FROM sale_return sr JOIN sale_return_items sri ON sri.sale_invoice_id = sr.id JOIN products p ON sri.product_id = p.id WHERE sr.tenant_id = ? AND sr.customer_id IN ($in_ph) AND sr.status = 'Posted' AND p.vendor_id = ?{$dist_date} GROUP BY sr.customer_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, [$distribution_id], $date_params));
        } else {
            $stmt = $pdo->prepare("SELECT customer_id, currency_id, SUM(net_amount) as total FROM sale_return WHERE tenant_id = ? AND customer_id IN ($in_ph) AND status = 'Posted'{$company_filter}{$date_filter_sale} GROUP BY customer_id, currency_id");
            $stmt->execute(array_merge([$tenant_id], $customer_ids, $company_params, $date_params));
        }
        $period_returns = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $pdo->prepare("SELECT rm.customer_id, rm.currency_id, SUM(al.credit) as total FROM accounting_ledger al JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management' WHERE al.tenant_id = ? AND al.account_id = 2 AND rm.customer_id IN ($in_ph){$date_filter_al} GROUP BY rm.customer_id, rm.currency_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $date_params));
        $period_rent = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $pdo->prepare("SELECT from_customer_id as customer_id, currency_id, SUM(amount) as total FROM transfer_voucher WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id IN ($in_ph){$company_filter}{$date_filter_vdate} GROUP BY from_customer_id, currency_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $company_params, $date_params));
        $period_tv_from = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $pdo->prepare("SELECT to_customer_id as customer_id, currency_id, SUM(amount) as total FROM transfer_voucher WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id IN ($in_ph){$company_filter}{$date_filter_vdate} GROUP BY to_customer_id, currency_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $company_params, $date_params));
        $period_tv_to = $sumByCid($stmt->fetchAll(PDO::FETCH_ASSOC));

        // ── JOURNAL VOUCHER ADJUSTMENTS — bulk, three passes ──────────────────
        $adj_debit_by_cid = $adj_credit_by_cid = [];
        $jv_date_filter = ($from_date && $to_date) ? " AND jv.voucher_date BETWEEN ? AND ?" : "";
        $jv_date_params = ($from_date && $to_date) ? [$from_date, $to_date] : [];

        // single mode: party1 is the customer
        $stmt = $pdo->prepare("SELECT jv.party_id as customer_id, SUM(jvl.debit) as adj_debit, SUM(jvl.credit) as adj_credit FROM journal_voucher jv JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2 WHERE jv.tenant_id = ? AND jv.status = 'posted' AND jv.adj_mode = 'single' AND jv.party_type = 'customer' AND jv.party_id IN ($in_ph){$jv_date_filter} GROUP BY jv.party_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $jv_date_params));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cid = $r['customer_id'];
            $adj_debit_by_cid[$cid]  = ($adj_debit_by_cid[$cid]  ?? 0) + floatval($r['adj_debit']);
            $adj_credit_by_cid[$cid] = ($adj_credit_by_cid[$cid] ?? 0) + floatval($r['adj_credit']);
        }

        // cross mode party1 (pick MIN line id)
        $stmt = $pdo->prepare("SELECT jv.party_id as customer_id, SUM(jvl.debit) as adj_debit, SUM(jvl.credit) as adj_credit FROM journal_voucher jv JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2 AND jvl.id = (SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2) WHERE jv.tenant_id = ? AND jv.status = 'posted' AND jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id IN ($in_ph){$jv_date_filter} GROUP BY jv.party_id");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $jv_date_params));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cid = $r['customer_id'];
            $adj_debit_by_cid[$cid]  = ($adj_debit_by_cid[$cid]  ?? 0) + floatval($r['adj_debit']);
            $adj_credit_by_cid[$cid] = ($adj_credit_by_cid[$cid] ?? 0) + floatval($r['adj_credit']);
        }

        // cross mode party2 (pick MAX line id)
        $stmt = $pdo->prepare("SELECT jv.party_id2 as customer_id, SUM(jvl.debit) as adj_debit, SUM(jvl.credit) as adj_credit FROM journal_voucher jv JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2 AND jvl.id = (SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2) WHERE jv.tenant_id = ? AND jv.status = 'posted' AND jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 IN ($in_ph){$jv_date_filter} GROUP BY jv.party_id2");
        $stmt->execute(array_merge([$tenant_id], $customer_ids, $jv_date_params));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cid = $r['customer_id'];
            $adj_debit_by_cid[$cid]  = ($adj_debit_by_cid[$cid]  ?? 0) + floatval($r['adj_debit']);
            $adj_credit_by_cid[$cid] = ($adj_credit_by_cid[$cid] ?? 0) + floatval($r['adj_credit']);
        }

        // ── BUILD RESULT — pure PHP arithmetic, no more per-customer DB queries ─
        $data = [];
        foreach ($customers as $customer) {
            $cid = $customer['id'];

            $base_opening_debit  = floatval($customer['opening_debit_amount']);
            $base_opening_credit = floatval($customer['opening_credit_amount']);
            if ($base_currency_id != $target_currency_id) {
                $base_opening_debit  = $converter->convert($base_opening_debit,  $base_currency_id, $target_currency_id);
                $base_opening_credit = $converter->convert($base_opening_credit, $base_currency_id, $target_currency_id);
            }
            $base_opening = $base_opening_debit - $base_opening_credit;

            $opening_balance = $base_opening;
            if ($from_date) {
                $opening_balance = $base_opening
                    + ($pre_invoices[$cid]         ?? 0)
                    + ($pre_returns_refunded[$cid]  ?? 0)
                    + ($pre_tv_to[$cid]             ?? 0)
                    + ($pre_pv[$cid]                ?? 0)
                    - ($pre_payments[$cid]          ?? 0)
                    - ($pre_returns_credit[$cid]    ?? 0)
                    - ($pre_rent[$cid]              ?? 0)
                    - ($pre_tv_from[$cid]           ?? 0);
            }

            $total_debit  = ($period_invoices[$cid] ?? 0) + ($period_pv[$cid] ?? 0)      + ($period_tv_to[$cid]   ?? 0);
            $total_credit = ($period_payments[$cid] ?? 0) + ($period_returns[$cid] ?? 0) + ($period_rent[$cid]    ?? 0) + ($period_tv_from[$cid] ?? 0);
            $adj_net      = ($adj_debit_by_cid[$cid] ?? 0) - ($adj_credit_by_cid[$cid] ?? 0);

            $closing_balance = $opening_balance + $total_debit - $total_credit + $adj_net;

            if ($closing_balance != 0 || $total_debit != 0 || $total_credit != 0 || $adj_net != 0) {
                $data[] = [
                    'customer_name'   => $customer['customer_name'],
                    'opening_balance' => $opening_balance,
                    'total_debit'     => $total_debit,
                    'total_credit'    => $total_credit,
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

        // Get journal voucher adjustments for this customer (single + cross-party)
        // Join journal_voucher_line directly — for cross-party, each line belongs to one party
        // party_type/party_id = party1 (first line), party_type2/party_id2 = party2 (second line)
        // We pick the line that belongs to THIS customer only
        $adj_sql = "SELECT jv.voucher_date as date,
                CONCAT('Adjustment - ', jv.voucher_number) as description,
                jv.voucher_number as reference,
                jvl.debit, jvl.credit,
                'adjustment' as type, null as sub_account_id, null as currency_id,
                jv.description as adj_note,
                CASE
                    WHEN jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = ? THEN
                        CONCAT(UPPER(jv.party_type2), ': ', COALESCE(
                            (SELECT customer_name FROM customers WHERE id = jv.party_id2 LIMIT 1),
                            (SELECT supplier_name FROM suppliers WHERE id = jv.party_id2 LIMIT 1)
                        ))
                    WHEN jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = ? THEN
                        CONCAT(UPPER(jv.party_type), ': ', COALESCE(
                            (SELECT customer_name FROM customers WHERE id = jv.party_id LIMIT 1),
                            (SELECT supplier_name FROM suppliers WHERE id = jv.party_id LIMIT 1)
                        ))
                    ELSE (
                        SELECT a.name FROM journal_voucher_line jvl2
                        JOIN accounts a ON a.id = jvl2.account_id
                        WHERE jvl2.voucher_id = jv.id AND jvl2.account_id != 2
                        LIMIT 1
                    )
                END as contra_account_name
                FROM journal_voucher jv
                JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2
                WHERE jv.tenant_id = ? AND jv.status = 'posted'
                  AND (
                      -- single mode: party1 is this customer, take the one line with account_id=2
                      (jv.adj_mode = 'single' AND jv.party_type = 'customer' AND jv.party_id = ?)
                      OR
                      -- cross mode party1: this customer is party1, pick the line matching party1 side
                      -- party1 is always the FIRST line inserted (lower id)
                      (jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = ?
                       AND jvl.id = (SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2))
                      OR
                      -- cross mode party2: this customer is party2, pick the line matching party2 side
                      (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = ?
                       AND jvl.id = (SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2))
                  )";
        $adj_params = [$customer_id, $customer_id, $tenant_id, $customer_id, $customer_id, $customer_id];
        if ($from_date && $to_date) {
            $adj_sql .= " AND jv.voucher_date BETWEEN ? AND ?";
            $adj_params[] = $from_date;
            $adj_params[] = $to_date;
        }
        $stmt = $pdo->prepare($adj_sql);
        $stmt->execute($adj_params);
        $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Batch-fetch all invoice/return items upfront to avoid N+1 per transaction
        $invoice_ids = array_values(array_filter(array_column($invoices, 'id')));
        $return_ids  = array_values(array_filter(array_column($returns,  'id')));
        $items_map_invoice = [];
        $items_map_return  = [];

        if (!empty($invoice_ids)) {
            $in_inv = implode(',', array_fill(0, count($invoice_ids), '?'));
            $isql = "SELECT sii.sale_invoice_id, p.name as product_name, sii.quantity, u.uom_name, sii.sale_price, sii.net_amount FROM sale_invoice_items sii JOIN products p ON sii.product_id = p.id LEFT JOIN uom u ON sii.uom_id = u.id WHERE sii.sale_invoice_id IN ($in_inv)";
            $iparams = $invoice_ids;
            if ($distribution_id) { $isql .= " AND p.vendor_id = ?"; $iparams[] = $distribution_id; }
            $istmt = $pdo->prepare($isql);
            $istmt->execute($iparams);
            foreach ($istmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $items_map_invoice[$item['sale_invoice_id']][] = $item;
            }
        }

        if (!empty($return_ids)) {
            $in_ret = implode(',', array_fill(0, count($return_ids), '?'));
            $rsql = "SELECT sri.sale_invoice_id, p.name as product_name, sri.quantity, u.uom_name, sri.sale_price, sri.net_amount FROM sale_return_items sri JOIN products p ON sri.product_id = p.id LEFT JOIN uom u ON sri.uom_id = u.id WHERE sri.sale_invoice_id IN ($in_ret)";
            $rparams = $return_ids;
            if ($distribution_id) { $rsql .= " AND p.vendor_id = ?"; $rparams[] = $distribution_id; }
            $rstmt = $pdo->prepare($rsql);
            $rstmt->execute($rparams);
            foreach ($rstmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $items_map_return[$item['sale_invoice_id']][] = $item;
            }
        }

        // Combine and sort transactions
        $transactions = array_merge($invoices, $payments, $returns, $pdcs, $rent_cash_transactions, $tv_out, $tv_in, $adjustments);
        
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
                
                if ($transaction['type'] === 'invoice') {
                    $transaction['items'] = $items_map_invoice[$transaction['id']] ?? [];
                } elseif ($transaction['type'] === 'return') {
                    $transaction['items'] = $items_map_return[$transaction['id']] ?? [];
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
                    
                    if ($transaction['type'] === 'invoice') {
                        $transaction['items'] = $items_map_invoice[$transaction['id']] ?? [];
                    } elseif ($transaction['type'] === 'return') {
                        $transaction['items'] = $items_map_return[$transaction['id']] ?? [];
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