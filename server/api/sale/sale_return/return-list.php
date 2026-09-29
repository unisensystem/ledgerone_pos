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
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    $company_id = !empty($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $customer_id = !empty($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
    $date_from = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = !empty($_GET['date_to']) ? $_GET['date_to'] : null;
    $status = !empty($_GET['status']) ? $_GET['status'] : null;
    $search = !empty($_GET['search']) ? trim($_GET['search']) : null;

    $where = ['si.tenant_id = ?'];
    $params = [$tenant_id];

    if ($status && in_array($status, ['Draft', 'Posted'], true)) {
        $where[] = 'si.status = ?';
        $params[] = $status;
    } else {
        // Default view: only finalized returns, matching this page's prior behavior.
        $where[] = "si.status = 'Posted'";
    }

    if ($company_id) {
        $where[] = 'si.company_id = ?';
        $params[] = $company_id;
    }

    if ($customer_id) {
        $where[] = 'si.customer_id = ?';
        $params[] = $customer_id;
    }

    if ($date_from) {
        $where[] = 'si.sale_date >= ?';
        $params[] = $date_from;
    }

    if ($date_to) {
        $where[] = 'si.sale_date <= ?';
        $params[] = $date_to;
    }

    if ($search) {
        $where[] = '(si.bill_no LIKE ? OR c.customer_name LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereClause = implode(' AND ', $where);

    // Get total count (search needs the customers join, so keep it consistent with the main query)
    $countSql = "
        SELECT COUNT(*)
        FROM sale_return si
        LEFT JOIN customers c ON si.customer_id = c.id
        WHERE $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();

    // Summary totals across the WHOLE filtered result set (not just the current page),
    // so the "Total Amount" box means what it says regardless of which page is showing.
    $summarySql = "
        SELECT
            COALESCE(SUM(si.net_amount), 0) as total_amount,
            COUNT(DISTINCT si.currency_id) as currency_count,
            MIN(cur.symbol) as single_currency_symbol
        FROM sale_return si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        WHERE $whereClause
    ";
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($params);
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    $summary = [
        'total_count' => (int) $totalRecords,
        'total_amount' => (float) $summaryRow['total_amount'],
        // Only meaningful when every matching return shares one currency
        'currency_symbol' => ((int) $summaryRow['currency_count'] === 1) ? $summaryRow['single_currency_symbol'] : ''
    ];

    // Get paginated data
    $sql = "
        SELECT
            si.id,
            si.bill_no,
            si.sale_date,
            si.company_id,
            si.customer_id,
            si.status,
            c.customer_name,
            COUNT(sii.id) as item_count,
            si.net_amount,
            cur.symbol as currency_symbol
        FROM sale_return si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN sale_return_items sii ON si.id = sii.sale_invoice_id
        LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        WHERE $whereClause
        GROUP BY si.id
        ORDER BY si.sale_date DESC, si.id DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'invoices' => $invoices,
        'summary' => $summary,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $totalRecords,
            'pages' => (int) ceil($totalRecords / $limit)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
