<?php
/**
 * Shared Recovery Sheet query — the single source of truth for both the
 * live report API (recovery-sheet.php) and the print view (print.php).
 * Previously each had its own hand-duplicated copy of this query; they drifted
 * (print.php was missing 2 of the 4 UNION parts, the unallocated_transfer/
 * unallocated_adjustment columns, and used a stale current_balance > 0 filter
 * instead of <> 0 — causing customers with a credit/negative balance to
 * silently vanish from print while still showing on the live page).
 */

function getRecoverySheetRows($pdo, $tenant_id, array $filters) {
    $date_from           = $filters['date_from']           ?? null;
    $date_to             = $filters['date_to']             ?? null;
    $recovery_officer_id = $filters['recovery_officer_id'] ?? null;
    $country_id          = $filters['country_id']          ?? null;
    $region_id           = $filters['region_id']           ?? null;
    $city_id             = $filters['city_id']              ?? null;
    $city_zone_id        = $filters['city_zone_id']        ?? null;
    $area_id             = $filters['area_id']              ?? null;
    $company_id          = $filters['company_id']          ?? null;
    $customer_id         = $filters['customer_id']         ?? null;

    // Signed net transfer-voucher amount for a customer (c.id), reused in both
    // UNION halves. Sign convention: POSITIVE reduces the customer's balance
    // (they paid out, or someone paid in on their behalf), NEGATIVE increases it
    // (a customer-to-customer transfer landed on them as a new charge).
    $transferSubquery = "
        COALESCE((
            SELECT SUM(
                CASE
                    WHEN tv.from_type = 'customer' AND tv.from_customer_id = c.id THEN tv.amount
                    WHEN tv.to_type = 'customer' AND tv.to_customer_id = c.id AND tv.from_type = 'supplier' THEN tv.amount
                    WHEN tv.to_type = 'customer' AND tv.to_customer_id = c.id AND tv.from_type = 'customer' THEN -tv.amount
                    ELSE 0
                END
            )
            FROM transfer_voucher tv
            WHERE tv.tenant_id = ?
            AND (
                (tv.from_type = 'customer' AND tv.from_customer_id = c.id)
                OR (tv.to_type = 'customer' AND tv.to_customer_id = c.id)
            )
        ), 0) as unallocated_transfer
    ";

    // Net Journal Voucher adjustment for this customer (single + cross-party),
    // same logic used in customer-ledger.php / customer-aging.php. Sign is
    // flipped to match the "positive reduces balance" convention used by the
    // other unallocated_* subqueries above.
    $adjustmentSubquery = "
        COALESCE((
            SELECT SUM(
                CASE
                    WHEN jv.adj_mode = 'single'
                        OR (jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = c.id
                            AND jvl.id = (SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2))
                        OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = c.id
                            AND jvl.id = (SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND account_id = 2))
                    THEN jvl.credit - jvl.debit
                    ELSE 0
                END
            )
            FROM journal_voucher jv
            JOIN journal_voucher_line jvl ON jvl.voucher_id = jv.id AND jvl.account_id = 2
            WHERE jv.tenant_id = ? AND jv.status = 'posted'
            AND (
                (jv.adj_mode = 'single' AND jv.party_type = 'customer' AND jv.party_id = c.id)
                OR (jv.adj_mode = 'cross' AND jv.party_type = 'customer' AND jv.party_id = c.id)
                OR (jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = c.id)
            )
        ), 0) as unallocated_adjustment
    ";

    $query = "
       SELECT
            c.customer_name,
            c.address,
            c.primary_phone,
            obi.invoice_number as bill_no,
            obi.invoice_date,
            DATEDIFF(CURDATE(), obi.invoice_date) as days_overdue,
            (obi.debit - obi.credit) - COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.bill_no = obi.invoice_number
                AND rv.customer_id = c.id
                AND rv.tenant_id = ?
            ), 0) as current_balance,
            0 as total_sales,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                AND rv.tenant_id = ?
                " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
                " . ($date_to ? " AND rv.voucher_date <= ?" : "") . "
                " . ($recovery_officer_id ? " AND rv.recovery_officer_id = ?" : "") . "
            ), 0) as total_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                AND sr.tenant_id = ?
                AND sr.status = 'Posted'
                " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
                " . ($date_to ? " AND sr.sale_date <= ?" : "") . "
            ), 0) as total_returns,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                AND rv.tenant_id = ?
                AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
            ), 0) as unallocated_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                AND sr.tenant_id = ?
                AND sr.status = 'Posted'
                AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = 0)
            ), 0) as unallocated_returns,
            {$adjustmentSubquery},
            {$transferSubquery}
        FROM opening_balance_invoices obi
        JOIN customers c ON obi.customer_id = c.id
        WHERE obi.tenant_id = ?
        AND c.status = 'ACTIVE'
        AND (obi.debit > 0 OR obi.credit > 0)
        " . ($company_id ? " AND c.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
        " . ($country_id ? " AND c.country_id = ?" : "") . "
        " . ($region_id ? " AND c.region_id = ?" : "") . "
        " . ($city_id ? " AND c.city_id = ?" : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?" : "") . "
        " . ($area_id ? " AND c.area_id = ?" : "") . "

        UNION ALL

       SELECT
            c.customer_name,
            c.address,
            c.primary_phone,
            si.bill_no,
            si.sale_date as invoice_date,
            DATEDIFF(CURDATE(), si.sale_date) as days_overdue,
            si.net_amount - COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.bill_no = si.bill_no
                AND rv.customer_id = c.id
                AND rv.tenant_id = ?
            ), 0) - COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.sale_invoice_no = si.id
                AND sr.tenant_id = ?
                AND sr.status = 'Posted'
            ), 0) as current_balance,
            si.net_amount as total_sales,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                AND rv.tenant_id = ?
                " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
                " . ($date_to ? " AND rv.voucher_date <= ?" : "") . "
                " . ($recovery_officer_id ? " AND rv.recovery_officer_id = ?" : "") . "
            ), 0) as total_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                AND sr.tenant_id = ?
                AND sr.status = 'Posted'
                " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
                " . ($date_to ? " AND sr.sale_date <= ?" : "") . "
            ), 0) as total_returns,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                AND rv.tenant_id = ?
                AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
            ), 0) as unallocated_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                AND sr.tenant_id = ?
                AND sr.status = 'Posted'
                AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = 0)
            ), 0) as unallocated_returns,
            {$adjustmentSubquery},
            {$transferSubquery}
        FROM sale_invoice si
        JOIN customers c ON si.customer_id = c.id
        WHERE si.tenant_id = ?
        AND c.status = 'ACTIVE'
        AND si.status = 'Posted'
        AND si.net_amount > 0
        " . ($recovery_officer_id ? " AND si.sale_officer_id = ?" : "") . "
        " . ($date_from ? " AND si.sale_date >= ?" : "") . "
        " . ($date_to ? " AND si.sale_date <= ?" : "") . "
        " . ($company_id ? " AND si.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
        " . ($country_id ? " AND c.country_id = ?" : "") . "
        " . ($region_id ? " AND c.region_id = ?" : "") . "
        " . ($city_id ? " AND c.city_id = ?" : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?" : "") . "
        " . ($area_id ? " AND c.area_id = ?" : "") . "

        UNION ALL

        -- Unbilled Receive Vouchers (advance/unlinked receipts) shown as
        -- their own credit row, same treatment as an opening credit balance.
       SELECT
            c.customer_name,
            c.address,
            c.primary_phone,
            rv.voucher_number as bill_no,
            rv.voucher_date as invoice_date,
            DATEDIFF(CURDATE(), rv.voucher_date) as days_overdue,
            -rv.amount as current_balance,
            0 as total_sales,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                " . ($date_from ? " AND rv2.voucher_date >= ?" : "") . "
                " . ($date_to ? " AND rv2.voucher_date <= ?" : "") . "
                " . ($recovery_officer_id ? " AND rv2.recovery_officer_id = ?" : "") . "
            ), 0) as total_received,
            COALESCE((
                SELECT SUM(sr2.net_amount)
                FROM sale_return sr2
                WHERE sr2.customer_id = c.id
                AND sr2.tenant_id = ?
                AND sr2.status = 'Posted'
                " . ($date_from ? " AND sr2.sale_date >= ?" : "") . "
                " . ($date_to ? " AND sr2.sale_date <= ?" : "") . "
            ), 0) as total_returns,
            COALESCE((
                SELECT SUM(rv3.amount)
                FROM receive_voucher rv3
                WHERE rv3.customer_id = c.id
                AND rv3.tenant_id = ?
                AND (rv3.bill_no IS NULL OR rv3.bill_no = '' OR rv3.bill_no = 'null')
            ), 0) as unallocated_received,
            COALESCE((
                SELECT SUM(sr3.net_amount)
                FROM sale_return sr3
                WHERE sr3.customer_id = c.id
                AND sr3.tenant_id = ?
                AND sr3.status = 'Posted'
                AND (sr3.sale_invoice_no IS NULL OR sr3.sale_invoice_no = 0)
            ), 0) as unallocated_returns,
            {$adjustmentSubquery},
            {$transferSubquery}
        FROM receive_voucher rv
        JOIN customers c ON rv.customer_id = c.id
        WHERE rv.tenant_id = ?
        AND c.status = 'ACTIVE'
        AND (rv.bill_no IS NULL OR rv.bill_no = '' OR rv.bill_no = 'null')
        " . ($company_id ? " AND c.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
        " . ($country_id ? " AND c.country_id = ?" : "") . "
        " . ($region_id ? " AND c.region_id = ?" : "") . "
        " . ($city_id ? " AND c.city_id = ?" : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?" : "") . "
        " . ($area_id ? " AND c.area_id = ?" : "") . "

        UNION ALL

        -- Unbilled Sale Returns (not linked to any sale invoice) shown as
        -- their own credit row, same treatment as an opening credit balance.
       SELECT
            c.customer_name,
            c.address,
            c.primary_phone,
            sr.bill_no as bill_no,
            sr.sale_date as invoice_date,
            DATEDIFF(CURDATE(), sr.sale_date) as days_overdue,
            -sr.net_amount as current_balance,
            0 as total_sales,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                AND rv.tenant_id = ?
                " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
                " . ($date_to ? " AND rv.voucher_date <= ?" : "") . "
                " . ($recovery_officer_id ? " AND rv.recovery_officer_id = ?" : "") . "
            ), 0) as total_received,
            COALESCE((
                SELECT SUM(sr2.net_amount)
                FROM sale_return sr2
                WHERE sr2.customer_id = c.id
                AND sr2.tenant_id = ?
                AND sr2.status = 'Posted'
                " . ($date_from ? " AND sr2.sale_date >= ?" : "") . "
                " . ($date_to ? " AND sr2.sale_date <= ?" : "") . "
            ), 0) as total_returns,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
            COALESCE((
                SELECT SUM(sr3.net_amount)
                FROM sale_return sr3
                WHERE sr3.customer_id = c.id
                AND sr3.tenant_id = ?
                AND sr3.status = 'Posted'
                AND (sr3.sale_invoice_no IS NULL OR sr3.sale_invoice_no = 0)
            ), 0) as unallocated_returns,
            {$adjustmentSubquery},
            {$transferSubquery}
        FROM sale_return sr
        JOIN customers c ON sr.customer_id = c.id
        WHERE sr.tenant_id = ?
        AND c.status = 'ACTIVE'
        AND sr.status = 'Posted'
        AND (sr.sale_invoice_no IS NULL OR sr.sale_invoice_no = 0)
        " . ($company_id ? " AND c.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
        " . ($country_id ? " AND c.country_id = ?" : "") . "
        " . ($region_id ? " AND c.region_id = ?" : "") . "
        " . ($city_id ? " AND c.city_id = ?" : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?" : "") . "
        " . ($area_id ? " AND c.area_id = ?" : "") . "

        UNION ALL

        -- Payment Voucher to Customer (an advance/charge paid out to the customer
        -- debits Trade Debtors — see payment-add.php's debit_account_id = 2 for
        -- payment_type='customer' — so it INCREASES what the customer owes, same
        -- treatment as customer-ledger.php's prev-payment-vouchers accumulator.
        -- Sign is POSITIVE here, the opposite convention from receive_voucher
        -- and sale_return above, which REDUCE the balance.
       SELECT
            c.customer_name,
            c.address,
            c.primary_phone,
            pv.voucher_number as bill_no,
            pv.voucher_date as invoice_date,
            DATEDIFF(CURDATE(), pv.voucher_date) as days_overdue,
            pv.amount as current_balance,
            0 as total_sales,
            COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.customer_id = c.id
                AND rv.tenant_id = ?
                " . ($date_from ? " AND rv.voucher_date >= ?" : "") . "
                " . ($date_to ? " AND rv.voucher_date <= ?" : "") . "
                " . ($recovery_officer_id ? " AND rv.recovery_officer_id = ?" : "") . "
            ), 0) as total_received,
            COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.customer_id = c.id
                AND sr.tenant_id = ?
                AND sr.status = 'Posted'
                " . ($date_from ? " AND sr.sale_date >= ?" : "") . "
                " . ($date_to ? " AND sr.sale_date <= ?" : "") . "
            ), 0) as total_returns,
            COALESCE((
                SELECT SUM(rv2.amount)
                FROM receive_voucher rv2
                WHERE rv2.customer_id = c.id
                AND rv2.tenant_id = ?
                AND (rv2.bill_no IS NULL OR rv2.bill_no = '' OR rv2.bill_no = 'null')
            ), 0) as unallocated_received,
            COALESCE((
                SELECT SUM(sr3.net_amount)
                FROM sale_return sr3
                WHERE sr3.customer_id = c.id
                AND sr3.tenant_id = ?
                AND sr3.status = 'Posted'
                AND (sr3.sale_invoice_no IS NULL OR sr3.sale_invoice_no = 0)
            ), 0) as unallocated_returns,
            {$adjustmentSubquery},
            {$transferSubquery}
        FROM payment_voucher pv
        JOIN customers c ON pv.customer_id = c.id
        WHERE pv.tenant_id = ?
        AND c.status = 'ACTIVE'
        " . ($company_id ? " AND c.company_id = ?" : "") . "
        " . ($customer_id ? " AND c.id = ?" : "") . "
        " . ($country_id ? " AND c.country_id = ?" : "") . "
        " . ($region_id ? " AND c.region_id = ?" : "") . "
        " . ($city_id ? " AND c.city_id = ?" : "") . "
        " . ($city_zone_id ? " AND c.city_zone_id = ?" : "") . "
        " . ($area_id ? " AND c.area_id = ?" : "") . "

        ORDER BY customer_name
    ";

    // Wrap in outer query to filter out zero balances (both positive
    // receivable rows and negative/credit rows are kept). Uses the same
    // 0.01 epsilon as Customer View's computeNetBalance()/ZERO_BALANCE_EPSILON
    // in recovery-sheet.js, instead of an exact <> 0 — a bill whose balance
    // rounds to 0.00 after cent-level arithmetic residue (e.g. 0.003) is
    // still "zero" to the user even though it isn't exactly 0, and used to
    // slip through Bill View showing a confusing "0.00" row.
    $query = "SELECT * FROM ($query) AS recovery_data WHERE ABS(current_balance) > 0.01";

    // UNION Part 1: opening_balance_invoices
    $params = [$tenant_id];
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    if ($recovery_officer_id) $params[] = $recovery_officer_id;
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id; // unallocated_adjustment
    $params[] = $tenant_id; // unallocated_transfer
    $params[] = $tenant_id; // WHERE obi.tenant_id
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($country_id) $params[] = $country_id;
    if ($region_id) $params[] = $region_id;
    if ($city_id) $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id) $params[] = $area_id;

    // UNION Part 2: sale_invoice
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    if ($recovery_officer_id) $params[] = $recovery_officer_id;
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id; // unallocated_adjustment
    $params[] = $tenant_id; // unallocated_transfer
    $params[] = $tenant_id; // WHERE si.tenant_id
    if ($recovery_officer_id) $params[] = $recovery_officer_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($country_id) $params[] = $country_id;
    if ($region_id) $params[] = $region_id;
    if ($city_id) $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id) $params[] = $area_id;

    // UNION Part 3: unbilled Receive Vouchers
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    if ($recovery_officer_id) $params[] = $recovery_officer_id;
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id; // unallocated_adjustment
    $params[] = $tenant_id; // unallocated_transfer
    $params[] = $tenant_id; // WHERE rv.tenant_id
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($country_id) $params[] = $country_id;
    if ($region_id) $params[] = $region_id;
    if ($city_id) $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id) $params[] = $area_id;

    // UNION Part 4: unbilled Sale Returns
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    if ($recovery_officer_id) $params[] = $recovery_officer_id;
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id; // unallocated_adjustment
    $params[] = $tenant_id; // unallocated_transfer
    $params[] = $tenant_id; // WHERE sr.tenant_id
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($country_id) $params[] = $country_id;
    if ($region_id) $params[] = $region_id;
    if ($city_id) $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id) $params[] = $area_id;

    // UNION Part 5: Payment Voucher to Customer
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    if ($recovery_officer_id) $params[] = $recovery_officer_id;
    $params[] = $tenant_id;
    if ($date_from) $params[] = $date_from;
    if ($date_to) $params[] = $date_to;
    $params[] = $tenant_id;
    $params[] = $tenant_id;
    $params[] = $tenant_id; // unallocated_adjustment
    $params[] = $tenant_id; // unallocated_transfer
    $params[] = $tenant_id; // WHERE pv.tenant_id
    if ($company_id) $params[] = $company_id;
    if ($customer_id) $params[] = $customer_id;
    if ($country_id) $params[] = $country_id;
    if ($region_id) $params[] = $region_id;
    if ($city_id) $params[] = $city_id;
    if ($city_zone_id) $params[] = $city_zone_id;
    if ($area_id) $params[] = $area_id;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mirrors recovery-sheet.js's groupByCustomer() + computeNetBalance().
 * IMPORTANT: net balance subtracts unallocated_transfer/unallocated_adjustment
 * only — NOT unallocated_received/unallocated_returns, since unbilled receive
 * vouchers and unbilled sale returns already appear as their own rows (UNION
 * parts 3 & 4) and are already summed into total_balance. Subtracting them
 * again here would double-count them.
 */
function groupRecoverySheetByCustomer(array $rows) {
    $map = [];
    foreach ($rows as $row) {
        $name = $row['customer_name'];
        if (!isset($map[$name])) {
            $map[$name] = [
                'customer_name'          => $row['customer_name'],
                'address'                => $row['address'],
                'primary_phone'          => $row['primary_phone'],
                'total_balance'          => 0.0,
                'total_received'         => floatval($row['total_received']),
                'total_returns'          => floatval($row['total_returns']),
                'unallocated_transfer'   => floatval($row['unallocated_transfer']),
                'unallocated_adjustment' => floatval($row['unallocated_adjustment']),
                'bills'                  => []
            ];
        }
        $map[$name]['total_balance'] += floatval($row['current_balance']);
        $map[$name]['bills'][] = $row;
    }
    foreach ($map as &$customer) {
        usort($customer['bills'], fn($a, $b) => strcmp($a['invoice_date'], $b['invoice_date']));
    }
    return array_values($map);
}

function recoverySheetNetBalance(array $customer) {
    return $customer['total_balance'] - $customer['unallocated_transfer'] - $customer['unallocated_adjustment'];
}
