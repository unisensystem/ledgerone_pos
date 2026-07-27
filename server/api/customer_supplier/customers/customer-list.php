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

    // Filter by user's assigned companies
    $stmtUC = $pdo->prepare("SELECT company_id FROM user_companies WHERE tenant_id = ? AND user_id = ?");
    $stmtUC->execute([$tenant_id, $user_id]);
    $assignedCompanies = $stmtUC->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($assignedCompanies)) {
        $ph = implode(',', array_fill(0, count($assignedCompanies), '?'));
        $where[] = "c.company_id IN ($ph)";
        $params = array_merge($params, $assignedCompanies);
    }
    
    if ($search) {
        $where[] = '(c.customer_name LIKE ? OR c.customer_code LIKE ? OR c.email LIKE ? OR c.primary_phone LIKE ? OR ct.type_name LIKE ? OR cc.category_name LIKE ? OR s.brand_name LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
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
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM customers c
            LEFT JOIN customer_types ct ON c.customer_type_id = ct.id AND (ct.tenant_id = 0 OR ct.tenant_id = c.tenant_id)
            LEFT JOIN customer_categories cc ON c.customer_category_id = cc.id AND cc.tenant_id = c.tenant_id
            LEFT JOIN suppliers s ON c.brand_id = s.id
            $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get customers
    $sql = "SELECT c.id, c.customer_code, c.customer_name, c.primary_phone, c.email, c.current_balance, c.is_blacklisted, c.created_at, 
            ct.type_name as customer_group_name,
            cc.category_name as customer_category_name,
            s.brand_name as brand_name
            FROM customers c
            LEFT JOIN customer_types ct ON c.customer_type_id = ct.id AND (ct.tenant_id = 0 OR ct.tenant_id = c.tenant_id)
            LEFT JOIN customer_categories cc ON c.customer_category_id = cc.id AND cc.tenant_id = c.tenant_id
            LEFT JOIN suppliers s ON c.brand_id = s.id
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