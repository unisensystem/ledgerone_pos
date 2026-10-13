<?php
session_start();
date_default_timezone_set('Asia/Karachi');
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $branch_id  = !empty($_GET['branch_id'])  ? intval($_GET['branch_id'])  : null;
    $counter_id = !empty($_GET['counter_id']) ? intval($_GET['counter_id']) : null;
    $date_range = $_GET['date_range'] ?? 'today';
    $from_date  = $_GET['from_date']  ?? '';
    $to_date    = $_GET['to_date']    ?? '';
    $page       = max(1, intval($_GET['page']  ?? 1));
    $limit      = max(1, intval($_GET['limit'] ?? 50));
    $offset     = ($page - 1) * $limit;

    // Resolve date bounds
    switch ($date_range) {
        case 'today':
            $d_from = $d_to = date('Y-m-d');
            break;
        case 'week':
            $d_from = date('Y-m-d', strtotime('monday this week'));
            $d_to   = date('Y-m-d');
            break;
        case 'month':
            $d_from = date('Y-m-01');
            $d_to   = date('Y-m-d');
            break;
        case 'custom':
            $d_from = $from_date ?: date('Y-m-01');
            $d_to   = $to_date   ?: date('Y-m-d');
            break;
        default:
            $d_from = date('Y-m-01');
            $d_to   = date('Y-m-d');
    }

    // ── Base filter conditions for receive_voucher (cash only = payment_method_id 1) ──
    $conditions = [
        'rv.tenant_id = ?',
        'rv.payment_method_id = 1',  // cash only
        'rv.voucher_date BETWEEN ? AND ?'
    ];
    $params = [$tenant_id, $d_from, $d_to];

    if ($branch_id) {
        // receive_voucher has no branch_id; join through sale_invoice via bill_no
        $conditions[] = 'si.branch_id = ?';
        $params[] = $branch_id;
    }
    if ($counter_id) {
        $conditions[] = 'rv.counter_id = ?';
        $params[] = $counter_id;
    }

    $where = implode(' AND ', $conditions);

    // ── Cash-in transactions (receive vouchers from POS cash sales) ──
    $txStmt = $pdo->prepare("
        SELECT rv.voucher_date   AS txn_date,
               rv.voucher_number AS reference,
               rv.bill_no,
               COALESCE(cu.customer_name, 'Walk-in') AS customer,
               rv.amount,
               ct.counter_name,
               rv.counter_id,
               em.full_name AS sale_officer
        FROM receive_voucher rv
        LEFT JOIN sale_invoice si ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
        LEFT JOIN customers    cu ON cu.id = rv.customer_id
        LEFT JOIN counters     ct ON ct.id = rv.counter_id
        LEFT JOIN employees    em ON em.id = si.sale_officer_id AND em.tenant_id = rv.tenant_id
        WHERE $where
        ORDER BY rv.voucher_date DESC, rv.id DESC
        LIMIT $limit OFFSET $offset
    ");
    $txStmt->execute($params);
    $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Total rows for pagination ──
    $countParams = $params;
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM receive_voucher rv
        LEFT JOIN sale_invoice si ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
        WHERE $where
    ");
    $countStmt->execute($countParams);
    $totalRows = (int) $countStmt->fetchColumn();

    // ── Summary: total cash-in for the period ──
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0)
        FROM receive_voucher rv
        LEFT JOIN sale_invoice si ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
        WHERE $where
    ");
    $sumStmt->execute($params);
    $totalCashIn = floatval($sumStmt->fetchColumn());

    // ── Opening cash balance ──
    $openingParams = [$tenant_id, $d_from];
    $openingWhere  = 'co.tenant_id = ? AND co.as_of_date = ?';
    if ($branch_id) { $openingWhere .= ' AND co.branch_id = ?';  $openingParams[] = $branch_id;  }
    if ($counter_id) { $openingWhere .= ' AND co.counter_id = ?'; $openingParams[] = $counter_id; }

    $openingStmt = $pdo->prepare("
        SELECT COALESCE(SUM(co.opening_amount), 0)
        FROM cash_opening co
        WHERE $openingWhere
    ");
    $openingStmt->execute($openingParams);
    $openingBalance = floatval($openingStmt->fetchColumn());

    // ── Cash given to sales officers at the counter (Counter Cash page) = opening float ──
    $givenWhere  = 'x.tenant_id = ? AND x.entry_date BETWEEN ? AND ?';
    $givenParams = [$tenant_id, $d_from, $d_to];
    if ($branch_id)  { $givenWhere .= ' AND x.branch_id = ?';  $givenParams[] = $branch_id;  }
    if ($counter_id) { $givenWhere .= ' AND x.counter_id = ?'; $givenParams[] = $counter_id; }

    $givenStmt = $pdo->prepare("SELECT COALESCE(SUM(x.amount), 0) FROM counter_cash_entries x WHERE $givenWhere");
    $givenStmt->execute($givenParams);
    $openingGiven = floatval($givenStmt->fetchColumn());

    // Opening = existing opening + cash given today (e.g. 1000)
    $openingTotal = $openingBalance + $openingGiven;

    // ── Received back from sales officers + adjustments (Counter Settlement page) ──
    $setWhere  = 's.tenant_id = ? AND s.entry_date BETWEEN ? AND ?';
    $setParams = [$tenant_id, $d_from, $d_to];
    if ($branch_id)  { $setWhere .= ' AND s.branch_id = ?';  $setParams[] = $branch_id;  }
    if ($counter_id) { $setWhere .= ' AND s.counter_id = ?'; $setParams[] = $counter_id; }

    $setStmt = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN s.entry_type = 'Received'   THEN s.amount END), 0) AS received,
               COALESCE(SUM(CASE WHEN s.entry_type = 'Adjustment' THEN s.amount END), 0) AS adjustment
        FROM counter_settlements s
        WHERE $setWhere
    ");
    $setStmt->execute($setParams);
    $setRow = $setStmt->fetch(PDO::FETCH_ASSOC);
    $totalReceived   = floatval($setRow['received']);
    $totalAdjustment = floatval($setRow['adjustment']);
    // > 0 = short cash, < 0 = excess
    $shortCash = ($openingTotal + $totalCashIn) - $totalReceived - $totalAdjustment;

    // ── Summary details (shown when user clicks Summary) ──
    $details = ['opening' => [], 'sales' => [], 'received' => [], 'adjustment' => []];

    // Opening: cash given to sales officers (date, counter, officer)
    $opDetStmt = $pdo->prepare("
        SELECT x.entry_date AS txn_date, ct.counter_name, em.full_name AS sale_officer,
               x.amount, x.remarks, 'Cash given' AS source
        FROM counter_cash_entries x
        LEFT JOIN counters  ct ON ct.id = x.counter_id
        LEFT JOIN employees em ON em.id = x.sale_officer_id
        WHERE $givenWhere
        ORDER BY x.entry_date, x.id
    ");
    $opDetStmt->execute($givenParams);
    $details['opening'] = $opDetStmt->fetchAll(PDO::FETCH_ASSOC);

    // Opening: old cash_opening setup rows (no officer)
    $opOldStmt = $pdo->prepare("
        SELECT co.as_of_date AS txn_date, ct.counter_name, NULL AS sale_officer,
               co.opening_amount AS amount, NULL AS remarks, 'Opening setup' AS source
        FROM cash_opening co
        LEFT JOIN counters ct ON ct.id = co.counter_id
        WHERE $openingWhere AND co.opening_amount <> 0
        ORDER BY co.id
    ");
    $opOldStmt->execute($openingParams);
    $details['opening'] = array_merge($opOldStmt->fetchAll(PDO::FETCH_ASSOC), $details['opening']);

    // Sales: cash sales grouped by date / counter / officer
    $slDetStmt = $pdo->prepare("
        SELECT rv.voucher_date AS txn_date, ct.counter_name, em.full_name AS sale_officer,
               COUNT(*) AS txn_count, SUM(rv.amount) AS amount
        FROM receive_voucher rv
        LEFT JOIN sale_invoice si ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
        LEFT JOIN counters     ct ON ct.id = rv.counter_id
        LEFT JOIN employees    em ON em.id = si.sale_officer_id AND em.tenant_id = rv.tenant_id
        WHERE $where
        GROUP BY rv.voucher_date, rv.counter_id, ct.counter_name, si.sale_officer_id, em.full_name
        ORDER BY rv.voucher_date, ct.counter_name
    ");
    $slDetStmt->execute($params);
    $details['sales'] = $slDetStmt->fetchAll(PDO::FETCH_ASSOC);

    // Received + adjustments
    $stDetStmt = $pdo->prepare("
        SELECT s.entry_date AS txn_date, s.entry_type, ct.counter_name, em.full_name AS sale_officer,
               s.amount, s.remarks
        FROM counter_settlements s
        LEFT JOIN counters  ct ON ct.id = s.counter_id
        LEFT JOIN employees em ON em.id = s.sale_officer_id
        WHERE $setWhere
        ORDER BY s.entry_date, s.id
    ");
    $stDetStmt->execute($setParams);
    foreach ($stDetStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['entry_type'] === 'Received') $details['received'][] = $r;
        else                                 $details['adjustment'][] = $r;
    }

    // ── Per-counter summary (when viewing all counters in a branch) ──
    $summaryData = [];
    if ($branch_id && !$counter_id) {
        $sumByCounterStmt = $pdo->prepare("
            SELECT ct.id, ct.counter_name,
                   COALESCE(SUM(rv.amount), 0) AS cash_in,
                   (SELECT COALESCE(SUM(x.amount), 0)
                      FROM counter_cash_entries x
                     WHERE x.tenant_id = ct.tenant_id
                       AND x.counter_id = ct.id
                       AND x.entry_date BETWEEN ? AND ?) AS opening_given,
                   (SELECT COALESCE(SUM(s.amount), 0)
                      FROM counter_settlements s
                     WHERE s.tenant_id = ct.tenant_id AND s.counter_id = ct.id
                       AND s.entry_type = 'Received'
                       AND s.entry_date BETWEEN ? AND ?) AS received,
                   (SELECT COALESCE(SUM(s.amount), 0)
                      FROM counter_settlements s
                     WHERE s.tenant_id = ct.tenant_id AND s.counter_id = ct.id
                       AND s.entry_type = 'Adjustment'
                       AND s.entry_date BETWEEN ? AND ?) AS adjustment
            FROM counters ct
            LEFT JOIN receive_voucher rv
                   ON rv.counter_id = ct.id
                  AND rv.tenant_id = ct.tenant_id
                  AND rv.payment_method_id = 1
                  AND rv.voucher_date BETWEEN ? AND ?
            WHERE ct.tenant_id = ? AND ct.branch_id = ? AND ct.is_active = 1
            GROUP BY ct.id, ct.counter_name
            ORDER BY ct.counter_name
        ");
        $sumByCounterStmt->execute([$d_from, $d_to, $d_from, $d_to, $d_from, $d_to, $d_from, $d_to, $tenant_id, $branch_id]);
        $summaryData = $sumByCounterStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Counter list for filter dropdown ──
    $counterListParams = [$tenant_id];
    $counterListWhere  = 'tenant_id = ?';
    if ($branch_id) { $counterListWhere .= ' AND branch_id = ?'; $counterListParams[] = $branch_id; }
    $clStmt = $pdo->prepare("SELECT id, counter_name FROM counters WHERE $counterListWhere AND is_active = 1 ORDER BY counter_name");
    $clStmt->execute($counterListParams);
    $counterList = $clStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'          => true,
        'opening_balance'  => $openingTotal,
        'opening_given'    => $openingGiven,
        'details'          => $details,
        'total_received'   => $totalReceived,
        'total_adjustment' => $totalAdjustment,
        'short_cash'       => $shortCash,
        'total_cash_in'    => $totalCashIn,
        'closing_balance'  => $openingTotal + $totalCashIn,
        'transactions'     => $transactions,
        'total_rows'       => $totalRows,
        'page'             => $page,
        'limit'            => $limit,
        'counter_summary'  => $summaryData,
        'counter_list'     => $counterList,
        'date_from'        => $d_from,
        'date_to'          => $d_to,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}