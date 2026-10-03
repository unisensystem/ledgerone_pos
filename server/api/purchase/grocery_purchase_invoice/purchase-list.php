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

try {
    $page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    $company_id  = isset($_GET['company_id'])  && $_GET['company_id']  !== '' ? (int)$_GET['company_id']  : null;
    $supplier_id = isset($_GET['supplier_id']) && $_GET['supplier_id'] !== '' ? (int)$_GET['supplier_id'] : null;
    $search      = isset($_GET['search'])      && $_GET['search']      !== '' ? trim($_GET['search'])      : null;
    $date_from   = isset($_GET['date_from'])   && $_GET['date_from']   !== '' ? $_GET['date_from']         : null;
    $date_to     = isset($_GET['date_to'])     && $_GET['date_to']     !== '' ? $_GET['date_to']           : null;
    $status      = isset($_GET['status'])      && $_GET['status']      !== '' ? $_GET['status']            : null;
    $supplier_invoice_no = isset($_GET['supplier_invoice_no']) && $_GET['supplier_invoice_no'] !== '' ? trim($_GET['supplier_invoice_no']) : null;

    // Build WHERE
    $where  = "pi.tenant_id = ? AND (pi.is_tax = 0 OR pi.is_tax IS NULL)";
    $params = [$tenant_id];

    if ($company_id) {
        $where .= " AND pi.company_id = ?";
        $params[] = $company_id;
    }
    if ($supplier_id) {
        $where .= " AND pi.supplier_id = ?";
        $params[] = $supplier_id;
    }
    if ($date_from) {
        $where .= " AND pi.purchase_date >= ?";
        $params[] = $date_from;
    }
    if ($date_to) {
        $where .= " AND pi.purchase_date <= ?";
        $params[] = $date_to;
    }
    if ($search) {
        $where .= " AND (pi.bill_no LIKE ? OR s.supplier_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($supplier_invoice_no) {
        $where .= " AND pi.supplier_invoice_no LIKE ?";
        $params[] = "%$supplier_invoice_no%";
    }

    // Count query (needs supplier join for search)
    $countSql = "SELECT COUNT(DISTINCT pi.id) FROM purchase_invoice pi
        LEFT JOIN suppliers s ON pi.supplier_id = s.id
        WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();

    // Status filter is applied in HAVING (needs aggregated paid_amount)
    $having = '';
    $havingParams = [];
    if ($status === 'paid')    { $having = 'HAVING paid_amount >= pi.net_amount AND pi.net_amount > 0'; }
    elseif ($status === 'partial') { $having = 'HAVING paid_amount > 0 AND paid_amount < pi.net_amount'; }
    elseif ($status === 'pending') { $having = 'HAVING paid_amount <= 0'; }

    $dataParams = array_merge($params);

    $stmt = $pdo->prepare("
        SELECT
            pi.id,
            pi.bill_no,
            pi.purchase_date,
            pi.supplier_invoice_no,
            pi.company_id,
            pi.supplier_id,
            s.supplier_name,
            COUNT(DISTINCT pii.id) as item_count,
            pi.net_amount,
            c.symbol as currency_symbol,
            COALESCE(SUM(DISTINCT pv.amount), 0) as paid_amount
        FROM purchase_invoice pi
        LEFT JOIN suppliers s ON pi.supplier_id = s.id
        LEFT JOIN purchase_invoice_items pii ON pi.id = pii.purchase_invoice_id
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        LEFT JOIN payment_voucher pv ON pv.bill_no = pi.bill_no AND pv.tenant_id = pi.tenant_id
        WHERE $where
        GROUP BY pi.id
        $having
        ORDER BY pi.purchase_date DESC, pi.id DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($dataParams);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'invoices' => $invoices,
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => (int)$totalRecords,
            'pages' => (int)ceil($totalRecords / $limit)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}