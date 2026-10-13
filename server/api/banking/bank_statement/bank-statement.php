<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'statement';

try {
    // ---- List of bank accounts for the picker ----
    if ($type === 'banks') {
        $company_id = $_GET['company_id'] ?? null;
        $sql = "
            SELECT ba.id, ba.bank_name, ba.account_title, ba.account_number, ba.account_type,
                   ba.iban, ba.swift_code, ba.branch_name, ba.account_id,
                   c.symbol as currency_symbol, c.code as currency_code
            FROM bank_accounts ba
            LEFT JOIN tenant_currencies tc ON ba.currency_id = tc.id
            LEFT JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
            WHERE ba.tenant_id = ? AND ba.is_active = 1
        ";
        $params = [$tenant_id];
        if ($company_id) {
            $sql .= " AND (ba.company_id = ? OR ba.company_id IS NULL)";
            $params[] = $company_id;
        }
        $sql .= " ORDER BY ba.bank_name, ba.account_title";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // ---- The statement itself ----
    $bank_account_id = $_GET['bank_account_id'] ?? null;
    $from_date = $_GET['from_date'] ?? null;
    $to_date = $_GET['to_date'] ?? date('Y-m-d');
    $tx_type = $_GET['tx_type'] ?? 'all'; // all | deposits | withdrawals
    $search = trim($_GET['search'] ?? '');

    if (!$bank_account_id) {
        echo json_encode(['success' => false, 'message' => 'Bank account is required']);
        exit;
    }

    $bankStmt = $pdo->prepare("
        SELECT ba.*, c.symbol as currency_symbol, c.code as currency_code, c.name as currency_name,
               co.company_name, co.timezone as company_timezone
        FROM bank_accounts ba
        LEFT JOIN tenant_currencies tc ON ba.currency_id = tc.id
        LEFT JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        LEFT JOIN companies co ON ba.company_id = co.id
        WHERE ba.id = ? AND ba.tenant_id = ?
    ");
    $bankStmt->execute([$bank_account_id, $tenant_id]);
    $bank = $bankStmt->fetch(PDO::FETCH_ASSOC);

    if (!$bank) {
        echo json_encode(['success' => false, 'message' => 'Bank account not found']);
        exit;
    }

    $account_id = $bank['account_id'];

    // Opening balance = every ledger entry posted to this bank's chart-of-accounts
    // row before the statement's start date (includes the bank's own "Opening
    // Balance" seed entry, which already lives in accounting_ledger — see
    // reference_table = 'bank_accounts' rows — so no need to separately read
    // bank_accounts.opening_balance/debit_amount/credit_amount here).
    $opening_balance = 0;
    if ($from_date) {
        $openStmt = $pdo->prepare("
            SELECT COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as bal
            FROM accounting_ledger
            WHERE tenant_id = ? AND account_id = ? AND date < ?
        ");
        $openStmt->execute([$tenant_id, $account_id, $from_date]);
        $opening_balance = (float) $openStmt->fetchColumn();
    }

    // Main statement query. Most reference tables (transfer_voucher, expense_voucher,
    // payroll, PDC-tagged payment_voucher rows, the opening-balance seed row) already
    // carry a good human-readable accounting_ledger.description — only receive_voucher,
    // non-PDC payment_voucher, and journal_voucher rows store it as NULL/"null"/"" (see
    // how they're written in their respective save endpoints), so those three get a
    // purpose-built description via the LEFT JOINs below instead.
    $sql = "
        SELECT
            al.id, al.date, al.transaction_type, al.reference_table, al.reference_id,
            al.debit, al.credit,
            CASE
                WHEN al.reference_table = 'receive_voucher' THEN
                    CONCAT('Receive Voucher ', COALESCE(rv.voucher_number, ''),
                           CASE WHEN rvc.customer_name IS NOT NULL THEN CONCAT(' — ', rvc.customer_name) ELSE '' END)
                WHEN al.reference_table = 'payment_voucher' AND al.transaction_type != 'PDC' THEN
                    CONCAT('Payment Voucher ', COALESCE(pv.voucher_number, ''),
                           CASE
                               WHEN pvs.supplier_name IS NOT NULL THEN CONCAT(' — ', pvs.supplier_name)
                               WHEN pvc.customer_name IS NOT NULL THEN CONCAT(' — ', pvc.customer_name)
                               ELSE ''
                           END)
                WHEN al.reference_table = 'journal_voucher' THEN
                    CONCAT('Journal Entry ', COALESCE(jv.voucher_number, ''),
                           CASE WHEN jv.description IS NOT NULL AND jv.description != '' THEN CONCAT(' — ', jv.description) ELSE '' END)
                WHEN al.description IS NOT NULL AND al.description != '' AND al.description != 'null' THEN al.description
                ELSE CONCAT(REPLACE(al.transaction_type, '_', ' '), ' #', al.reference_id)
            END as description,
            CASE al.reference_table
                WHEN 'receive_voucher' THEN rv.voucher_number
                WHEN 'payment_voucher' THEN pv.voucher_number
                WHEN 'transfer_voucher' THEN tv.voucher_number
                WHEN 'journal_voucher' THEN jv.voucher_number
                WHEN 'expense_voucher' THEN ev.voucher_no
                ELSE NULL
            END as voucher_number
        FROM accounting_ledger al
        LEFT JOIN receive_voucher rv ON al.reference_table = 'receive_voucher' AND al.reference_id = rv.id
        LEFT JOIN customers rvc ON rv.customer_id = rvc.id
        LEFT JOIN payment_voucher pv ON al.reference_table = 'payment_voucher' AND al.reference_id = pv.id
        LEFT JOIN suppliers pvs ON pv.supplier_id = pvs.id
        LEFT JOIN customers pvc ON pv.customer_id = pvc.id
        LEFT JOIN transfer_voucher tv ON al.reference_table = 'transfer_voucher' AND al.reference_id = tv.id
        LEFT JOIN journal_voucher jv ON al.reference_table = 'journal_voucher' AND al.reference_id = jv.id
        LEFT JOIN expense_voucher ev ON al.reference_table = 'expense_voucher' AND al.reference_id = ev.id
        WHERE al.tenant_id = ? AND al.account_id = ?
    ";
    $params = [$tenant_id, $account_id];

    if ($from_date) {
        $sql .= " AND al.date >= ?";
        $params[] = $from_date;
    }
    if ($to_date) {
        $sql .= " AND al.date <= ?";
        $params[] = $to_date;
    }
    if ($tx_type === 'deposits') {
        $sql .= " AND al.debit > 0";
    } elseif ($tx_type === 'withdrawals') {
        $sql .= " AND al.credit > 0";
    }

    $sql .= " ORDER BY al.date ASC, al.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Free-text search over the resolved description/voucher number (applied in PHP
    // since description is computed, not a plain column).
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $rows = array_values(array_filter($rows, function ($r) use ($needle) {
            return mb_strpos(mb_strtolower($r['description']), $needle) !== false
                || mb_strpos(mb_strtolower((string) $r['voucher_number']), $needle) !== false;
        }));
    }

    $running = $opening_balance;
    $total_deposits = 0;
    $total_withdrawals = 0;
    $transactions = [];
    foreach ($rows as $r) {
        $debit = (float) $r['debit'];
        $credit = (float) $r['credit'];
        $running += $debit - $credit;
        $total_deposits += $debit;
        $total_withdrawals += $credit;
        $transactions[] = [
            'date' => $r['date'],
            'description' => $r['description'],
            'voucher_number' => $r['voucher_number'],
            'transaction_type' => $r['transaction_type'],
            'deposit' => $debit,
            'withdrawal' => $credit,
            'balance' => $running,
        ];
    }

    $deposit_count = 0;
    $withdrawal_count = 0;
    foreach ($transactions as $t) {
        if ($t['deposit'] > 0) $deposit_count++;
        if ($t['withdrawal'] > 0) $withdrawal_count++;
    }

    $userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $generated_by = $userStmt->fetchColumn() ?: null;

    date_default_timezone_set($bank['company_timezone'] ?: 'UTC');

    echo json_encode([
        'success' => true,
        'statement_ref' => 'STMT-' . str_pad($bank_account_id, 4, '0', STR_PAD_LEFT) . '-' . date('Ymd'),
        'generated_by' => $generated_by,
        'generated_at' => date('Y-m-d H:i:s'),
        'bank' => [
            'bank_name' => $bank['bank_name'],
            'is_mfb' => (bool) $bank['is_mfb'],
            'mfb_name' => $bank['mfb_name'],
            'account_title' => $bank['account_title'],
            'account_number' => $bank['account_number'],
            'account_type' => $bank['account_type'],
            'iban' => $bank['iban'],
            'swift_code' => $bank['swift_code'],
            'branch_name' => $bank['branch_name'],
            'branch_code' => $bank['branch_code'],
            'branch_city' => $bank['branch_city'],
            'branch_state' => $bank['branch_state'],
            'branch_address' => $bank['branch_address'],
            'contact_person' => $bank['contact_person'],
            'contact_number' => $bank['contact_number'],
            'email' => $bank['email'],
            'bank_logo_path' => $bank['bank_logo_path'],
            'is_active' => (bool) $bank['is_active'],
            'as_of_date' => $bank['as_of_date'],
            'company_name' => $bank['company_name'],
            'currency_symbol' => $bank['currency_symbol'] ?? '',
            'currency_code' => $bank['currency_code'] ?? '',
            'currency_name' => $bank['currency_name'] ?? '',
        ],
        'from_date' => $from_date,
        'to_date' => $to_date,
        'opening_balance' => $opening_balance,
        'closing_balance' => $running,
        'total_deposits' => $total_deposits,
        'total_withdrawals' => $total_withdrawals,
        'net_change' => $total_deposits - $total_withdrawals,
        'transaction_count' => count($transactions),
        'deposit_count' => $deposit_count,
        'withdrawal_count' => $withdrawal_count,
        'transactions' => $transactions,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'debug' => $e->getMessage()]);
}
