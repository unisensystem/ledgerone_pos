<?php
// ============================================================================
// Invoice Payment Report
//
// For each Posted sale invoice, shows what was received against it via
// receive_voucher (linked by bill_no + customer_id — see
// server/api/sale/pos_invoice/pos-add.php's "Insert receive voucher(s)"
// block, which creates one receive_voucher row per payment split at invoice
// creation/edit time): who paid (party/customer), how much, by what method
// (Cash / Bank Transfer + which bank), when, and under which voucher number.
// Invoices with no matching receive_voucher still appear, with paid_amount 0
// and balance = net_amount, so unpaid/credit invoices are visible too.
// ============================================================================

require_once '../../../../includes/connection.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $action = $_GET['action'] ?? 'report';

    $currency_symbol = '$';
    try {
        $stmt = $pdo->prepare("
            SELECT c.symbol FROM tenant_currencies tc
            JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
            WHERE tc.tenant_id = ? AND tc.is_base_currency = 1 LIMIT 1
        ");
        $stmt->execute([$tenant_id]);
        $sym = $stmt->fetchColumn();
        if ($sym) $currency_symbol = $sym;
    } catch (Exception $e) {
        error_log('Invoice Payment Report currency error: ' . $e->getMessage());
    }

    switch ($action) {

        case 'report':
            $from_date        = !empty($_GET['from_date'])        ? trim($_GET['from_date'])        : null;
            $to_date          = !empty($_GET['to_date'])          ? trim($_GET['to_date'])          : null;
            $company_id       = !empty($_GET['company_id'])       ? trim($_GET['company_id'])       : null;
            $branch_id        = !empty($_GET['branch_id'])        ? trim($_GET['branch_id'])        : null;
            $customer_id      = !empty($_GET['customer_id'])      ? trim($_GET['customer_id'])      : null;
            $payment_method   = !empty($_GET['payment_method'])   ? trim($_GET['payment_method'])   : null; // 'cash' | 'bank'
            $only_paid        = !empty($_GET['only_paid']) && $_GET['only_paid'] === '1';
            $payment_source   = !empty($_GET['payment_source'])   ? trim($_GET['payment_source'])   : null; // 'invoice' | 'receive_voucher'

            // PAYMENT SOURCE CLASSIFICATION
            //
            // There is no explicit "created via" column on receive_voucher, so
            // this is a heuristic based on timing: pos-add.php inserts a
            // receive_voucher row in the SAME request as the sale_invoice
            // itself when "Amount Paid" > 0 (see server/api/sale/pos_invoice/
            // pos-add.php "Insert receive voucher(s)" block) — that row's
            // created_at lands within seconds of the invoice's created_at.
            // A voucher entered separately and later via the standalone
            // Receive Voucher module (client/pages/vouchers/receive_voucher/
            // receive-add.php) — an accountant reconciling real bank/cash
            // receipts against the bill — lands minutes/hours/days later.
            // Verified against real data: same-invoice vouchers were 0-46
            // seconds apart; separately-entered ones were 8 minutes to 8.5
            // hours apart. 5 minutes is used as the cutoff.
            $sourceCase = "
                CASE
                    WHEN rv.id IS NULL THEN NULL
                    WHEN TIMESTAMPDIFF(SECOND, si.created_at, rv.created_at) <= 300 THEN 'invoice'
                    ELSE 'receive_voucher'
                END
            ";

            $sql = "
                SELECT
                    si.id AS invoice_id,
                    si.bill_no,
                    si.sale_date,
                    c.customer_name,
                    si.net_amount AS invoice_net_amount,
                    si.amount_paid_auto_fill,
                    rv.id AS voucher_id,
                    rv.voucher_number,
                    rv.voucher_date,
                    rv.amount AS paid_amount,
                    rv.payment_method_id,
                    pm.name AS payment_method_name,
                    ba.bank_name,
                    $sourceCase AS payment_source
                FROM sale_invoice si
                JOIN customers c ON si.customer_id = c.id
                LEFT JOIN receive_voucher rv
                    ON rv.bill_no = si.bill_no
                    AND rv.customer_id = si.customer_id
                    AND rv.tenant_id = si.tenant_id
                LEFT JOIN accounts pm ON rv.payment_method_id = pm.id
                LEFT JOIN bank_accounts ba ON rv.bank_account_id = ba.id
                WHERE si.tenant_id = ?
                AND si.status = 'Posted'
            ";
            $params = [$tenant_id];

            if ($from_date) { $sql .= " AND si.sale_date >= ?"; $params[] = $from_date; }
            if ($to_date)   { $sql .= " AND si.sale_date <= ?"; $params[] = $to_date; }
            if ($company_id)  { $sql .= " AND si.company_id = ?"; $params[] = $company_id; }
            if ($branch_id)   { $sql .= " AND si.branch_id = ?"; $params[] = $branch_id; }
            if ($customer_id) { $sql .= " AND si.customer_id = ?"; $params[] = $customer_id; }
            if ($payment_method === 'cash') { $sql .= " AND rv.payment_method_id = 1"; }
            if ($payment_method === 'bank') { $sql .= " AND rv.payment_method_id = 5"; }
            if ($only_paid) { $sql .= " AND rv.id IS NOT NULL"; }
            if ($payment_source === 'invoice' || $payment_source === 'receive_voucher') {
                $sql .= " HAVING payment_source = ?";
                $params[] = $payment_source;
            }

            $sql .= " ORDER BY si.sale_date DESC, si.bill_no DESC, rv.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_invoice_amount = 0;
            $total_paid = 0;
            $total_paid_via_invoice = 0;
            $total_paid_via_voucher = 0;
            $seen_invoices = [];

            foreach ($rows as &$row) {
                $row['invoice_net_amount'] = (float)$row['invoice_net_amount'];
                $row['paid_amount'] = $row['paid_amount'] !== null ? (float)$row['paid_amount'] : 0.0;

                if (!isset($seen_invoices[$row['invoice_id']])) {
                    $seen_invoices[$row['invoice_id']] = true;
                    $total_invoice_amount += $row['invoice_net_amount'];
                }
                $total_paid += $row['paid_amount'];
                if ($row['payment_source'] === 'invoice') {
                    $total_paid_via_invoice += $row['paid_amount'];
                } elseif ($row['payment_source'] === 'receive_voucher') {
                    $total_paid_via_voucher += $row['paid_amount'];
                }
            }
            unset($row);

            echo json_encode([
                'success' => true,
                'data' => $rows,
                'currency' => $currency_symbol,
                'summary' => [
                    'total_invoices' => count($seen_invoices),
                    'total_invoice_amount' => $total_invoice_amount,
                    'total_paid' => $total_paid,
                    'total_balance' => $total_invoice_amount - $total_paid,
                    'total_paid_via_invoice' => $total_paid_via_invoice,
                    'total_paid_via_voucher' => $total_paid_via_voucher
                ]
            ]);
            break;

        case 'companies':
            $stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 ORDER BY company_name");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'branches':
            $stmt = $pdo->prepare("SELECT id, branch_name FROM branches WHERE tenant_id = ? AND parent_branch_id IS NULL ORDER BY branch_name");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'customers':
            $stmt = $pdo->prepare("SELECT id, customer_name FROM customers WHERE tenant_id = ? AND status = 'ACTIVE' ORDER BY customer_name");
            $stmt->execute([$tenant_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    error_log('Invoice Payment Report PDO Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Invoice Payment Report Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}
