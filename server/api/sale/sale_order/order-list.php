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
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    $company_id  = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
    $date_from   = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
    $date_to     = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
    $status      = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
    $search      = isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null;

    // Build WHERE clause dynamically, with typed params so we can bind correctly
    $where = "si.tenant_id = ? AND si.status = 'Posted'";
    $params = [['value' => $tenant_id, 'type' => PDO::PARAM_INT]];

    if ($company_id) {
        $where .= " AND si.company_id = ?";
        $params[] = ['value' => $company_id, 'type' => PDO::PARAM_INT];
    }

    if ($customer_id) {
        $where .= " AND si.customer_id = ?";
        $params[] = ['value' => $customer_id, 'type' => PDO::PARAM_INT];
    }

    if ($date_from) {
        $where .= " AND DATE(si.sale_date) >= ?";
        $params[] = ['value' => $date_from, 'type' => PDO::PARAM_STR];
    }

    if ($date_to) {
        $where .= " AND DATE(si.sale_date) <= ?";
        $params[] = ['value' => $date_to, 'type' => PDO::PARAM_STR];
    }

    if ($search) {
        $where .= " AND (si.bill_no LIKE ? OR c.customer_name LIKE ?)";
        $params[] = ['value' => "%$search%", 'type' => PDO::PARAM_STR];
        $params[] = ['value' => "%$search%", 'type' => PDO::PARAM_STR];
    }

    // fulfillment_status is a computed CASE column, so it needs HAVING, not WHERE
    $having = '';
    $havingParam = null;
    if ($status) {
        $having = " HAVING fulfillment_status = ?";
        $havingParam = ['value' => $status, 'type' => PDO::PARAM_STR];
    }

    // ---- Count query ----
    $countSql = "
        SELECT COUNT(*) FROM (
            SELECT
                si.id,
                CASE
                    WHEN EXISTS(
                        SELECT 1 FROM sale_invoice inv WHERE inv.sale_order_id = si.id AND inv.status = 'Posted'
                    ) THEN 'Partially Fulfilled'
                    ELSE 'Pending'
                END as fulfillment_status
            FROM sale_order si
            LEFT JOIN customers c ON si.customer_id = c.id
            WHERE $where
            $having
        ) AS filtered
    ";

    $countStmt = $pdo->prepare($countSql);
    $bindIndex = 1;
    foreach ($params as $p) {
        $countStmt->bindValue($bindIndex++, $p['value'], $p['type']);
    }
    if ($havingParam) {
        $countStmt->bindValue($bindIndex++, $havingParam['value'], $havingParam['type']);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();

    // ---- Paginated data query ----
    // NOTE: join back to sale_invoice_id (original column) — the earlier
    // change to sale_order_id was an unverified guess and broke the query.
    // If item_count still looks wrong, confirm the real FK column name in
    // sale_order_items and tell me — I'll correct it instead of guessing.
    $sql = "
        SELECT 
            si.id,
            si.bill_no,
            si.sale_date,
            c.customer_name,
            e.full_name as sales_officer_name,
            sm.full_name as supplier_man_name,
            COUNT(sii.id) as item_count,
            si.net_amount,
            cur.symbol as currency_symbol,
            CASE 
                WHEN EXISTS(
                    SELECT 1 FROM sale_invoice inv WHERE inv.sale_order_id = si.id AND inv.status = 'Posted'
                ) THEN 'Partially Fulfilled'
                ELSE 'Pending'
            END as fulfillment_status
        FROM sale_order si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN employees e ON si.sale_officer_id = e.id
        LEFT JOIN employees sm ON si.supplier_man_id = sm.id
        LEFT JOIN sale_order_items sii ON si.id = sii.sale_invoice_id
        LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        WHERE $where
        GROUP BY si.id
        $having
        ORDER BY si.sale_date DESC, si.id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);
    $bindIndex = 1;
    foreach ($params as $p) {
        $stmt->bindValue($bindIndex++, $p['value'], $p['type']);
    }
    if ($havingParam) {
        $stmt->bindValue($bindIndex++, $havingParam['value'], $havingParam['type']);
    }
    $stmt->bindValue($bindIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'invoices' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => (int)ceil($totalRecords / $limit)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
