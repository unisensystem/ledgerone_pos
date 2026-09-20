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
    // Get query parameters
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $company = $_GET['company'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $where = ['c.tenant_id = ?'];
    $params = [$tenant_id];

    if ($search) {
             $where[] = '(c.customer_name LIKE ? OR c.shop_name LIKE ? OR c.customer_code LIKE ? OR c.address LIKE ? OR c.email LIKE ? OR c.primary_phone LIKE ? OR ct.type_name LIKE ? OR cc.category_name LIKE ? OR s.brand_name LIKE ? OR ci.city_name LIKE ? OR cz.city_zone_name LIKE ? OR ar.area_name LIKE ? OR e.full_name LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 13, $searchTerm));
    }
    
    if ($status === 'active') {
        $where[] = 'c.is_blacklisted = FALSE';
    } elseif ($status === 'blacklisted') {
        $where[] = 'c.is_blacklisted = TRUE';
    }
    
    if ($company) {
        $where[] = 'c.company_id = ?';
        $params[] = (int)$company;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Shared joins used by both count and list queries
   $joins = "LEFT JOIN companies comp ON c.company_id = comp.id AND comp.tenant_id = c.tenant_id
        LEFT JOIN customer_types ct ON c.customer_type_id = ct.id AND (ct.tenant_id = 0 OR ct.tenant_id = c.tenant_id)
        LEFT JOIN customer_categories cc ON c.customer_category_id = cc.id AND cc.tenant_id = c.tenant_id
        LEFT JOIN suppliers s ON c.brand_id = s.id
        LEFT JOIN cities ci ON c.city_id = ci.id AND ci.tenant_id = c.tenant_id
        LEFT JOIN city_zones cz ON c.city_zone_id = cz.id AND cz.tenant_id = c.tenant_id
        LEFT JOIN areas ar ON c.area_id = ar.id AND ar.tenant_id = c.tenant_id
        LEFT JOIN employees e ON c.associated_sales_officer_id = e.id AND e.tenant_id = c.tenant_id";
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM customers c $joins $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get customers
   // Get customers
$sql = "SELECT c.id, c.customer_code, c.customer_name, c.shop_name, c.address, c.primary_phone, c.email,
        c.current_balance, c.opening_debit_amount, c.opening_credit_amount, c.is_blacklisted, c.created_at,
        comp.company_name as company_name,
        ct.type_name as customer_group_name,
        cc.category_name as customer_category_name,
        s.supplier_name as brand_name,
        ci.city_name as city_name,
        cz.city_zone_name as zone_name,
        ar.area_name as area_name,
        e.full_name as salesman_name,
        (
            c.opening_debit_amount - c.opening_credit_amount
            + COALESCE((
                SELECT SUM(si.net_amount)
                FROM sale_invoice si
                WHERE si.tenant_id = c.tenant_id
                  AND si.customer_id = c.id
                  AND si.status = 'Posted'
            ), 0)
            - COALESCE((
                SELECT SUM(rv.amount)
                FROM receive_voucher rv
                WHERE rv.tenant_id = c.tenant_id
                  AND rv.customer_id = c.id
            ), 0)
                      - COALESCE((
                SELECT SUM(sr.net_amount)
                FROM sale_return sr
                WHERE sr.tenant_id = c.tenant_id
                  AND sr.customer_id = c.id
                  AND sr.status = 'Posted'
            ), 0)
            -- Adjustment Party 1 (single + cross)
            + COALESCE((
                SELECT SUM(l.debit - l.credit)
                FROM journal_voucher jv
                JOIN journal_voucher_line l ON l.voucher_id = jv.id AND l.tenant_id = jv.tenant_id
                 AND l.id = (SELECT MIN(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND tenant_id = jv.tenant_id)
                WHERE jv.tenant_id = c.tenant_id AND jv.status = 'posted'
                  AND jv.party_type = 'customer' AND jv.party_id = c.id
            ), 0)
            -- Adjustment Party 2 (cross mode only)
            + COALESCE((
                SELECT SUM(l.debit - l.credit)
                FROM journal_voucher jv
                JOIN journal_voucher_line l ON l.voucher_id = jv.id AND l.tenant_id = jv.tenant_id
                 AND l.id = (SELECT MAX(id) FROM journal_voucher_line WHERE voucher_id = jv.id AND tenant_id = jv.tenant_id)
                WHERE jv.tenant_id = c.tenant_id AND jv.status = 'posted'
                  AND jv.adj_mode = 'cross' AND jv.party_type2 = 'customer' AND jv.party_id2 = c.id
            ), 0)
        ) as current_balance_real
        FROM customers c
        $joins
        $whereClause 
        ORDER BY c.created_at DESC 
        LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $statsSql = "SELECT 
        COUNT(*) as total_customers,
        COUNT(CASE WHEN is_blacklisted = FALSE THEN 1 END) as active_customers,
        COUNT(CASE WHEN is_blacklisted = TRUE THEN 1 END) as blacklisted_customers,
        SUM(CASE WHEN current_balance > 0 THEN current_balance ELSE 0 END) as total_receivables
        FROM customers WHERE tenant_id = ?";
    
    $stmt = $pdo->prepare($statsSql);
    $stmt->execute([$tenant_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ],
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
