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
    $balance = $_GET['balance'] ?? '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // Build WHERE clause
    $where = ['tenant_id = ?'];
    $params = [$tenant_id];

    // Filter by user's assigned companies
    $assignedCompanies = [];
    try {
        $stmtUC = $pdo->prepare("SELECT company_id FROM user_companies WHERE tenant_id = ? AND user_id = ?");
        $stmtUC->execute([$tenant_id, $user_id]);
        $assignedCompanies = $stmtUC->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { /* table may not exist yet; show all */ }
    if (!empty($assignedCompanies)) {
        $ph = implode(',', array_fill(0, count($assignedCompanies), '?'));
        $where[] = "company_id IN ($ph)";
        $params = array_merge($params, $assignedCompanies);
    }

    if ($search) {
        $where[] = '(supplier_name LIKE ? OR supplier_code LIKE ? OR email LIKE ? OR primary_phone LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if ($status === 'active') {
        $where[] = 'is_blacklisted = FALSE';
    } elseif ($status === 'blacklisted') {
        $where[] = 'is_blacklisted = TRUE';
    }
    
    if ($company) {
        $where[] = 'company_id = ?';
        $params[] = (int)$company;
    }

    if ($balance === 'positive') {
        $where[] = 'current_balance > 0';
    } elseif ($balance === 'negative') {
        $where[] = 'current_balance < 0';
    } elseif ($balance === 'zero') {
        $where[] = 'current_balance = 0';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM suppliers $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Get suppliers
    $sql = "SELECT id, supplier_code, supplier_name, brand_name, primary_phone, email, current_balance, is_blacklisted, created_at 
            FROM suppliers $whereClause 
            ORDER BY created_at DESC 
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stats
    // Stats scoped to user's assigned companies
    $statsWhere = ['tenant_id = ?'];
    $statsParams = [$tenant_id];
    if (!empty($assignedCompanies)) {
        $ph = implode(',', array_fill(0, count($assignedCompanies), '?'));
        $statsWhere[] = "company_id IN ($ph)";
        $statsParams = array_merge($statsParams, $assignedCompanies);
    }
    $statsWhereClause = 'WHERE ' . implode(' AND ', $statsWhere);

    $statsSql = "SELECT 
        COUNT(*) as total_suppliers,
        COUNT(CASE WHEN is_blacklisted = FALSE THEN 1 END) as active_suppliers,
        COUNT(CASE WHEN is_blacklisted = TRUE THEN 1 END) as blacklisted_suppliers,
        SUM(CASE WHEN current_balance < 0 THEN ABS(current_balance) ELSE 0 END) as total_payables
        FROM suppliers $statsWhereClause";

    $stmt = $pdo->prepare($statsSql);
    $stmt->execute($statsParams);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'suppliers' => $suppliers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int) $total,
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