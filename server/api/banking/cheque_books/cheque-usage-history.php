<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenantId = (int)($_SESSION['tenant_id'] ?? 0);
if (!$tenantId || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // NOTE: the tables involved use mixed collations (utf8mb4_general_ci vs utf8mb4_unicode_ci),
    // so every text column that is joined on or UNIONed below is forced to utf8mb4_unicode_ci.
    $sql = "
        SELECT h.cheque_no, h.bank_account_id, h.bank_name, h.account_number, h.account_holder,
               h.transaction_type, h.voucher_number, h.party_name, h.transaction_date,
               h.amount, h.transaction_status, h.reference_table, h.reference_id
        FROM (
            SELECT pv.cheque_no COLLATE utf8mb4_unicode_ci AS cheque_no, pv.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci AS bank_name,
                   ba.account_number COLLATE utf8mb4_unicode_ci AS account_number,
                   ba.account_title COLLATE utf8mb4_unicode_ci AS account_holder,
                   'Payment' AS transaction_type,
                   pv.voucher_number COLLATE utf8mb4_unicode_ci AS voucher_number,
                   COALESCE(c.customer_name COLLATE utf8mb4_unicode_ci, s.supplier_name COLLATE utf8mb4_unicode_ci, '') AS party_name,
                   pv.voucher_date AS transaction_date, pv.amount,
                   COALESCE(pdc.status COLLATE utf8mb4_unicode_ci, 'Issued') AS transaction_status,
                   'payment_voucher' AS reference_table, pv.id AS reference_id
            FROM payment_voucher pv
            LEFT JOIN bank_accounts ba ON ba.id = pv.bank_account_id AND ba.tenant_id = pv.tenant_id
            LEFT JOIN customers c ON c.id = pv.customer_id AND c.tenant_id = pv.tenant_id
            LEFT JOIN suppliers s ON s.id = pv.supplier_id AND s.tenant_id = pv.tenant_id
            LEFT JOIN post_dated_cheques pdc ON pdc.tenant_id = pv.tenant_id
                AND pdc.reference_table = 'payment_voucher' AND pdc.reference_id = pv.id
                AND pdc.cheque_no COLLATE utf8mb4_unicode_ci = pv.cheque_no COLLATE utf8mb4_unicode_ci
            WHERE pv.tenant_id = ? AND pv.payment_method_id = 6 AND pv.cheque_no IS NOT NULL

            UNION ALL

            SELECT tv.cheque_no COLLATE utf8mb4_unicode_ci, tv.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci,
                   ba.account_number COLLATE utf8mb4_unicode_ci,
                   ba.account_title COLLATE utf8mb4_unicode_ci,
                   'Transfer',
                   tv.voucher_number COLLATE utf8mb4_unicode_ci,
                   COALESCE(fc.customer_name COLLATE utf8mb4_unicode_ci, fs.supplier_name COLLATE utf8mb4_unicode_ci,
                            tc.customer_name COLLATE utf8mb4_unicode_ci, ts.supplier_name COLLATE utf8mb4_unicode_ci, ''),
                   tv.voucher_date, tv.amount,
                   COALESCE(pdc.status COLLATE utf8mb4_unicode_ci, 'Issued'),
                   'transfer_voucher', tv.id
            FROM transfer_voucher tv
            LEFT JOIN bank_accounts ba ON ba.id = tv.bank_account_id AND ba.tenant_id = tv.tenant_id
            LEFT JOIN customers fc ON fc.id = tv.from_customer_id AND fc.tenant_id = tv.tenant_id
            LEFT JOIN suppliers fs ON fs.id = tv.from_supplier_id AND fs.tenant_id = tv.tenant_id
            LEFT JOIN customers tc ON tc.id = tv.to_customer_id AND tc.tenant_id = tv.tenant_id
            LEFT JOIN suppliers ts ON ts.id = tv.to_supplier_id AND ts.tenant_id = tv.tenant_id
            LEFT JOIN post_dated_cheques pdc ON pdc.tenant_id = tv.tenant_id
                AND pdc.reference_table = 'transfer_voucher' AND pdc.reference_id = tv.id
                AND pdc.cheque_no COLLATE utf8mb4_unicode_ci = tv.cheque_no COLLATE utf8mb4_unicode_ci
            WHERE tv.tenant_id = ? AND tv.payment_method_id = 6 AND tv.cheque_no IS NOT NULL

            UNION ALL

            SELECT evl.cheque_no COLLATE utf8mb4_unicode_ci, evl.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci,
                   ba.account_number COLLATE utf8mb4_unicode_ci,
                   ba.account_title COLLATE utf8mb4_unicode_ci,
                   'Expense',
                   ev.voucher_no COLLATE utf8mb4_unicode_ci,
                   '',
                   ev.date, evl.amount,
                   COALESCE(pdc.status COLLATE utf8mb4_unicode_ci, 'Issued'),
                   'expense_voucher', ev.id
            FROM expense_voucher_line evl
            JOIN expense_voucher ev ON ev.id = evl.voucher_id AND ev.tenant_id = evl.tenant_id
            LEFT JOIN bank_accounts ba ON ba.id = evl.bank_account_id AND ba.tenant_id = evl.tenant_id
            LEFT JOIN post_dated_cheques pdc ON pdc.tenant_id = evl.tenant_id
                AND pdc.reference_table = 'expense_voucher' AND pdc.reference_id = ev.id
                AND pdc.cheque_no COLLATE utf8mb4_unicode_ci = evl.cheque_no COLLATE utf8mb4_unicode_ci
            WHERE evl.tenant_id = ? AND evl.payment_method_id = 6 AND evl.cheque_no IS NOT NULL

            UNION ALL

            SELECT pei.cheque_no COLLATE utf8mb4_unicode_ci, pei.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci,
                   ba.account_number COLLATE utf8mb4_unicode_ci,
                   ba.account_title COLLATE utf8mb4_unicode_ci,
                   'Payroll',
                   pe.payroll_code COLLATE utf8mb4_unicode_ci,
                   COALESCE(e.full_name COLLATE utf8mb4_unicode_ci, pei.employee_id COLLATE utf8mb4_unicode_ci),
                   pe.payroll_date, pei.amount, 'Issued',
                   'payroll_entries', pe.id
            FROM payroll_entries_items pei
            JOIN payroll_entries pe ON pe.id = pei.payroll_id AND pe.tenant_id = pei.tenant_id
            LEFT JOIN bank_accounts ba ON ba.id = pei.bank_account_id AND ba.tenant_id = pei.tenant_id
            LEFT JOIN employees e ON e.employee_id COLLATE utf8mb4_unicode_ci = pei.employee_id COLLATE utf8mb4_unicode_ci
                AND e.tenant_id = pei.tenant_id
            WHERE pei.tenant_id = ? AND pei.payment_method_id = 6 AND pei.cheque_no IS NOT NULL

            UNION ALL

            SELECT pei.cheque_no COLLATE utf8mb4_unicode_ci, pei.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci,
                   ba.account_number COLLATE utf8mb4_unicode_ci,
                   ba.account_title COLLATE utf8mb4_unicode_ci,
                   'Production Expense',
                   CONCAT('PE-', LPAD(pe.id, 5, '0')) COLLATE utf8mb4_unicode_ci,
                   COALESCE(s.supplier_name COLLATE utf8mb4_unicode_ci, ''),
                   pe.entry_date, pei.amount,
                   COALESCE(pdc.status COLLATE utf8mb4_unicode_ci, 'Issued'),
                   'production_expenses', pe.id
            FROM production_expense_items pei
            JOIN production_expenses pe ON pe.id = pei.expense_id
            LEFT JOIN bank_accounts ba ON ba.id = pei.bank_account_id AND ba.tenant_id = pe.tenant_id
            LEFT JOIN suppliers s ON s.id = pei.vendor_id AND s.tenant_id = pe.tenant_id
            LEFT JOIN post_dated_cheques pdc ON pdc.tenant_id = pe.tenant_id
                AND pdc.reference_table = 'production_expenses' AND pdc.reference_id = pe.id
                AND pdc.cheque_no COLLATE utf8mb4_unicode_ci = pei.cheque_no COLLATE utf8mb4_unicode_ci
            WHERE pe.tenant_id = ? AND pei.payment_mode = 'cheque' AND pei.payment_status = 'paid' AND pei.cheque_no IS NOT NULL

            UNION ALL

            SELECT rv.cheque_no COLLATE utf8mb4_unicode_ci, rv.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci,
                   ba.account_number COLLATE utf8mb4_unicode_ci,
                   ba.account_title COLLATE utf8mb4_unicode_ci,
                   'Receipt',
                   rv.voucher_number COLLATE utf8mb4_unicode_ci,
                   COALESCE(c.customer_name COLLATE utf8mb4_unicode_ci, ''),
                   rv.voucher_date, rv.amount,
                   COALESCE(pdc.status COLLATE utf8mb4_unicode_ci, 'Received'),
                   'receive_voucher', rv.id
            FROM receive_voucher rv
            LEFT JOIN bank_accounts ba ON ba.id = rv.bank_account_id AND ba.tenant_id = rv.tenant_id
            LEFT JOIN customers c ON c.id = rv.customer_id AND c.tenant_id = rv.tenant_id
            LEFT JOIN post_dated_cheques pdc ON pdc.tenant_id = rv.tenant_id
                AND pdc.reference_table = 'receive_voucher' AND pdc.reference_id = rv.id
                AND pdc.cheque_no COLLATE utf8mb4_unicode_ci = rv.cheque_no COLLATE utf8mb4_unicode_ci
            WHERE rv.tenant_id = ? AND rv.payment_method_id = 6 AND rv.cheque_no IS NOT NULL

            UNION ALL

            -- Wasted cheques (cheque_no is stored as digits only, so prefix + zero padding is rebuilt from the cheque book)
            SELECT CONCAT(cb.cheque_prefix, LPAD(wc.cheque_no, cb.number_width, '0')) COLLATE utf8mb4_unicode_ci,
                   wc.bank_account_id,
                   ba.bank_name COLLATE utf8mb4_unicode_ci,
                   ba.account_number COLLATE utf8mb4_unicode_ci,
                   ba.account_title COLLATE utf8mb4_unicode_ci,
                   'Wasted',
                   '',
                   CONCAT('Reason: ', wc.reason) COLLATE utf8mb4_unicode_ci,
                   wc.waste_date, 0,
                   'Wasted',
                   'wasted_cheques', wc.id
            FROM wasted_cheques wc
            JOIN cheque_books cb ON cb.id = wc.cheque_book_id AND cb.tenant_id = wc.tenant_id
            LEFT JOIN bank_accounts ba ON ba.id = wc.bank_account_id AND ba.tenant_id = wc.tenant_id
            WHERE wc.tenant_id = ?
        ) h
        WHERE 1 = 1
    ";
    // one tenant_id placeholder per UNION part (7 parts)
    $params = array_fill(0, 7, $tenantId);

    foreach ([
        'bank' => ['h.bank_name', false],
        'bank_account_id' => ['h.bank_account_id', false],
        'account_number' => ['h.account_number', true],
        'account_holder' => ['h.account_holder', true],
        'cheque_no' => ['h.cheque_no', true],
        'transaction_type' => ['h.transaction_type', false]
    ] as $key => [$column, $partial]) {
        $value = trim((string)($_GET[$key] ?? ''));
        if ($value !== '') {
            $sql .= " AND $column " . ($partial ? 'LIKE ?' : '= ?');
            $params[] = $partial ? '%' . $value . '%' : $value;
        }
    }

    // Status filter: Pending / Approved / Cancelled
    // Adjust these lists if your post_dated_cheques.status uses different words.
    $statusMap = [
        'pending'   => ['pending', 'issued', 'received'],
        'approved'  => ['approved', 'cleared'],
        'cancelled' => ['cancelled', 'canceled', 'cancel', 'wasted']
    ];
    $statusKey = strtolower(trim((string)($_GET['status'] ?? '')));
    if ($statusKey !== '' && isset($statusMap[$statusKey])) {
        $statusValues = $statusMap[$statusKey];
        $sql .= ' AND LOWER(h.transaction_status) IN (' . implode(',', array_fill(0, count($statusValues), '?')) . ')';
        foreach ($statusValues as $statusValue) {
            $params[] = $statusValue;
        }
    }

    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));
    if ($dateFrom !== '') {
        $sql .= ' AND h.transaction_date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= ' AND h.transaction_date <= ?';
        $params[] = $dateTo;
    }
    // order=asc -> oldest first (top to bottom), anything else -> newest first
    $direction = strtolower(trim((string)($_GET['order'] ?? ''))) === 'asc' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY h.transaction_date $direction, h.reference_id $direction";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['cheque_no'] = (string)$row['cheque_no'];
        $row['amount'] = (float)$row['amount'];
    }
    unset($row);

    $bankStmt = $pdo->prepare('SELECT DISTINCT bank_name FROM bank_accounts WHERE tenant_id = ? AND is_active = 1 ORDER BY bank_name');
    $bankStmt->execute([$tenantId]);
    $accountStmt = $pdo->prepare(
        'SELECT id, bank_name, account_number, account_title
         FROM bank_accounts WHERE tenant_id = ? AND is_active = 1 ORDER BY bank_name, account_number'
    );
    $accountStmt->execute([$tenantId]);
    echo json_encode([
        'success' => true,
        'data' => $rows,
        'banks' => $bankStmt->fetchAll(PDO::FETCH_COLUMN),
        'accounts' => $accountStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Throwable $error) {
    error_log('Cheque usage history error: ' . $error->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Unable to load cheque history.'];
    // TEMPORARY: open this API URL directly in the browser with ?debug=1 to see the real SQL error, then remove these 3 lines
    if (!empty($_GET['debug'])) {
        $response['debug'] = $error->getMessage();
    }
    echo json_encode($response);
}