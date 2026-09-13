<?php
/**
 * Shared logic for the Outstanding report. Included by both outstanding.php (JSON API)
 * and print-outstanding.php (thermal print view) so the numbers can never drift apart.
 */

/**
 * Computes a customer's ledger balance as of a single cutoff date.
 * Mirrors the "soft opening" formula in customer-ledger.php exactly,
 * just using <= $to_date as the single cutoff instead of a from/to range.
 * Positive = Dr (customer owes you). Negative = Cr (you owe customer).
 */
function getCustomerBalanceAsOf(PDO $pdo, $tenant_id, $customer_id, $opening_debit, $opening_credit, $to_date, $company_id = null)
{
    $base_opening = (float)$opening_debit - (float)$opening_credit;
    $company_filter = $company_id ? " AND company_id = ?" : "";

    // Invoices
    $sql = "SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice
            WHERE tenant_id = ? AND customer_id = ? AND sale_date <= ?{$company_filter}";
    $params = [$tenant_id, $customer_id, $to_date];
    if ($company_id) $params[] = $company_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Receive vouchers (exclude those tied to still-pending PDCs)
    $sql = "SELECT COALESCE(SUM(rv.amount), 0) as total FROM receive_voucher rv
            WHERE rv.tenant_id = ? AND rv.customer_id = ? AND rv.voucher_date <= ?{$company_filter}
            AND rv.id NOT IN (
                SELECT pdc.reference_id FROM post_dated_cheques pdc
                WHERE pdc.tenant_id = ? AND pdc.reference_table = 'receive_voucher' AND pdc.status = 'Pending'
            )";
    $params = [$tenant_id, $customer_id, $to_date];
    if ($company_id) $params[] = $company_id;
    $params[] = $tenant_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $receipts = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Payment vouchers raised against the customer (debit side)
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher
            WHERE tenant_id = ? AND customer_id IS NOT NULL AND supplier_id IS NULL
            AND customer_id = ? AND voucher_date <= ?{$company_filter}";
    $params = [$tenant_id, $customer_id, $to_date];
    if ($company_id) $params[] = $company_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payment_vouchers = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Sale returns
    $sql = "SELECT COALESCE(SUM(amount_refunded), 0) as refunded,
                   COALESCE(SUM(net_amount - amount_refunded), 0) as credit
            FROM sale_return
            WHERE tenant_id = ? AND customer_id = ? AND sale_date <= ? AND status = 'Posted'{$company_filter}";
    $params = [$tenant_id, $customer_id, $to_date];
    if ($company_id) $params[] = $company_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ret = $stmt->fetch(PDO::FETCH_ASSOC);
    $returns_refunded = (float)$ret['refunded'];
    $returns_credit = (float)$ret['credit'];

    // Rent credits (account_id = 2 is the customer control account)
    $sql = "SELECT COALESCE(SUM(al.credit), 0) as total
            FROM accounting_ledger al
            JOIN rent_management rm ON al.reference_id = rm.id AND al.reference_table = 'rent_management'
            WHERE al.tenant_id = ? AND al.account_id = 2 AND rm.customer_id = ? AND al.date <= ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $customer_id, $to_date]);
    $rent = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Transfer vouchers — FROM this customer (credit), TO this customer (debit)
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher
            WHERE tenant_id = ? AND from_type = 'customer' AND from_customer_id = ? AND voucher_date <= ?{$company_filter}";
    $params = [$tenant_id, $customer_id, $to_date];
    if ($company_id) $params[] = $company_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tv_from = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transfer_voucher
            WHERE tenant_id = ? AND to_type = 'customer' AND to_customer_id = ? AND voucher_date <= ?{$company_filter}";
    $params = [$tenant_id, $customer_id, $to_date];
    if ($company_id) $params[] = $company_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tv_to = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Journal voucher adjustments (single + cross-party), same account_id = 2 pattern
    $adj_stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN jv.adj_mode='single' OR (jv.adj_mode='cross' AND jv.party_type='customer' AND jv.party_id=?
                AND jvl.id=(SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=2))
                OR (jv.adj_mode='cross' AND jv.party_type2='customer' AND jv.party_id2=?
                AND jvl.id=(SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=2))
                THEN jvl.debit ELSE 0 END), 0) as adj_debit,
            COALESCE(SUM(CASE WHEN jv.adj_mode='single' OR (jv.adj_mode='cross' AND jv.party_type='customer' AND jv.party_id=?
                AND jvl.id=(SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=2))
                OR (jv.adj_mode='cross' AND jv.party_type2='customer' AND jv.party_id2=?
                AND jvl.id=(SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id=jv.id AND account_id=2))
                THEN jvl.credit ELSE 0 END), 0) as adj_credit
         FROM journal_voucher jv
         JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2
         WHERE jv.tenant_id = ? AND jv.status = 'posted' AND jv.voucher_date <= ?
           AND (
               (jv.adj_mode = 'single' AND jv.party_type = 'customer' AND jv.party_id = ?)
               OR (jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = ?)
               OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = ?)
           )"
    );
    $adj_stmt->execute([
        $customer_id, $customer_id,
        $customer_id, $customer_id,
        $tenant_id, $to_date,
        $customer_id, $customer_id, $customer_id
    ]);
    $adj_row = $adj_stmt->fetch(PDO::FETCH_ASSOC);
    $adj_net = (float)$adj_row['adj_debit'] - (float)$adj_row['adj_credit'];

    return $base_opening + $invoices + $returns_refunded + $tv_to + $payment_vouchers
         - $receipts - $returns_credit - $rent - $tv_from + $adj_net;
}

/**
 * Builds the full grouped Outstanding report (DSR -> area -> customers) for a given
 * tenant/sales-officer/date. Returns the same shape whether called from the JSON API
 * or the print view.
 *
 * $sort parameter: 'high_to_low' or 'low_to_high'.
 * Sorting is applied BOTH to customers within each area AND to the order of the
 * area groups themselves (by area subtotal), so the report reads consistently
 * from top to bottom regardless of which area a customer belongs to.
 */
function buildOutstandingReport(PDO $pdo, $tenant_id, $sales_officer_id, $company_id = null, $from_date = null, $to_date = null, $customer_name = null, $city_id = null, $area_id = null, $sort = 'high_to_low')
{
    if (!$to_date) {
        $to_date = date('Y-m-d');
    }

    $sql = "SELECT c.id, c.customer_name, c.shop_name, c.opening_debit_amount, c.opening_credit_amount,
                   a.area_name
            FROM customers c
            LEFT JOIN areas a ON c.area_id = a.id
            WHERE c.tenant_id = ? AND c.status = 'ACTIVE' AND c.associated_sales_officer_id = ?";
    $params = [$tenant_id, $sales_officer_id];
    if ($company_id) {
        $sql .= " AND c.company_id = ?";
        $params[] = $company_id;
    }
    if ($city_id) {
        $sql .= " AND c.city_id = ?";
        $params[] = $city_id;
    }
    if ($area_id) {
        $sql .= " AND c.area_id = ?";
        $params[] = $area_id;
    }
    if ($customer_name) {
        $sql .= " AND (c.shop_name LIKE ? OR c.customer_name LIKE ?)";
        $like = '%' . $customer_name . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY a.area_name ASC, c.customer_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dsr_stmt = $pdo->prepare("SELECT full_name FROM employees WHERE id = ? AND tenant_id = ?");
    $dsr_stmt->execute([$sales_officer_id, $tenant_id]);
    $dsr_row = $dsr_stmt->fetch(PDO::FETCH_ASSOC);
    $dsr_name = $dsr_row ? $dsr_row['full_name'] : '';

    $groups = [];
    $grand_total = 0.0;

    foreach ($customers as $cust) {
        $balance = getCustomerBalanceAsOf(
            $pdo, $tenant_id, $cust['id'],
            $cust['opening_debit_amount'], $cust['opening_credit_amount'],
            $to_date, $company_id
        );

        // SKIP customers with zero balance
        if (abs($balance) < 0.01) {
            continue;
        }

        $area_key = $cust['area_name'] ?: 'No Area Assigned';
        if (!isset($groups[$area_key])) {
            $groups[$area_key] = ['area_name' => $area_key, 'customers' => [], 'subtotal' => 0.0];
        }

        $groups[$area_key]['customers'][] = [
            'shop_name' => $cust['shop_name'] ?: $cust['customer_name'],
            'balance' => round($balance, 2)
        ];
        $groups[$area_key]['subtotal'] += $balance;
        $grand_total += $balance;
    }

    $direction = ($sort === 'low_to_high') ? 1 : -1;

    // 1) Sort customers WITHIN each area group by balance
    foreach ($groups as &$group) {
        usort($group['customers'], function ($a, $b) use ($direction) {
            return ($a['balance'] <=> $b['balance']) * $direction;
        });
        $group['subtotal'] = round($group['subtotal'], 2);
    }
    unset($group);

    // 2) Sort the AREA GROUPS themselves by their subtotal, same direction,
    //    so the whole report reads low->high or high->low top to bottom.
    uasort($groups, function ($a, $b) use ($direction) {
        return ($a['subtotal'] <=> $b['subtotal']) * $direction;
    });

    // 3) Recalculate serial numbers after sorting (global running number)
    $serial = 0;
    foreach ($groups as &$group) {
        foreach ($group['customers'] as &$customer) {
            $serial++;
            $customer['s_no'] = $serial;
        }
    }
    unset($group);

    return [
        'dsr_name' => $dsr_name,
        'from_date' => $from_date,
        'to_date' => $to_date,
        'groups' => array_values($groups),
        'grand_total' => round($grand_total, 2)
    ];
}