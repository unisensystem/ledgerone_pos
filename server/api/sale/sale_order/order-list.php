<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

// Previously this only checked "does ANY Posted sale_invoice exist for this
// order" and called that 'Partially Fulfilled' regardless of how much was
// actually served — so a fully-delivered order showed the same badge as one
// with a single item delivered. Compares total ordered qty against total
// served qty (from Posted invoices linked to this order) instead, matching
// the per-item logic get-order-fulfillment.php already uses for its detail
// view, just aggregated across the whole order for a list-row badge.
define('FULFILLMENT_STATUS_SQL', "
    CASE
        WHEN NOT EXISTS (
            SELECT 1 FROM sale_invoice inv WHERE inv.sale_order_id = si.id AND inv.status = 'Posted'
        ) THEN 'Pending'
        WHEN (
            SELECT COALESCE(SUM(soi.quantity), 0) FROM sale_order_items soi
            WHERE soi.sale_invoice_id = si.id AND soi.parent_row_id IS NULL
        ) <= (
            SELECT COALESCE(SUM(sii2.quantity), 0) FROM sale_invoice inv2
            JOIN sale_invoice_items sii2 ON sii2.sale_invoice_id = inv2.id
            WHERE inv2.sale_order_id = si.id AND inv2.status = 'Posted'
        ) THEN 'Fully Fulfilled'
        ELSE 'Partially Fulfilled'
    END
");

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
    // Scoping is enforced server-side by auth context, not merely by the
    // request flag — a mobile bearer token can't opt out of only_mine by
    // sending 0; only a real web session (which already carries its own
    // tenant-wide-view expectations) can see every order.
    $only_mine   = $auth['via'] === 'token' || (isset($_GET['only_mine']) && $_GET['only_mine'] === '1');
    // The order's OWN status (Posted/Draft) — not to be confused with
    // $status above, which filters the computed fulfillment_status
    // (Pending/Partially/Fully Fulfilled). Defaults to 'Posted' exactly
    // like before (web never sends this param, so its behavior — every
    // Posted order in the tenant — is unchanged); 'Draft' surfaces orders
    // the hybrid auto-post rule flagged for review (stale offline price or
    // insufficient stock), which previously had no list anywhere to show up
    // in at all.
    $order_status = isset($_GET['order_status']) && in_array($_GET['order_status'], ['Posted', 'Draft'], true)
        ? $_GET['order_status']
        : 'Posted';

    // Build WHERE clause dynamically, with typed params so we can bind correctly
    $where = "si.tenant_id = ? AND si.status = ?";
    $params = [
        ['value' => $tenant_id, 'type' => PDO::PARAM_INT],
        ['value' => $order_status, 'type' => PDO::PARAM_STR],
    ];

    // Opt-in only — web never sends only_mine, so its behavior (every order
    // for the tenant) is unchanged. The mobile app always sends it: a field
    // booker should see their own orders, not the whole tenant's.
    if ($only_mine) {
        $where .= " AND si.created_by = ?";
        $params[] = ['value' => $user_id, 'type' => PDO::PARAM_INT];
    }

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
    // fulfillment_status is only needed here when $status actually filters on it
    // (the HAVING clause below) — otherwise evaluating that correlated EXISTS
    // subquery per row just to discard it made this count scan every matching
    // row at ~15s for a tenant with a few thousand orders. Skip it entirely
    // when there's no status filter; keep the derived-table form only when needed.
    if ($having) {
        $countSql = "
            SELECT COUNT(*) FROM (
                SELECT
                    si.id,
                    " . FULFILLMENT_STATUS_SQL . " as fulfillment_status
                FROM sale_order si
                LEFT JOIN customers c ON si.customer_id = c.id
                WHERE $where
                $having
            ) AS filtered
        ";
    } else {
        $countSql = "
            SELECT COUNT(*)
            FROM sale_order si
            LEFT JOIN customers c ON si.customer_id = c.id
            WHERE $where
        ";
    }

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
            si.status,
            si.bill_no,
            si.sale_date,
            c.customer_name,
            e.full_name as sales_officer_name,
            sm.full_name as supplier_man_name,
            COUNT(sii.id) as item_count,
            si.net_amount,
            cur.symbol as currency_symbol,
            " . FULFILLMENT_STATUS_SQL . " as fulfillment_status
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
