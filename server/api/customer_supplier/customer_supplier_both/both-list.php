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
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $company = $_GET['company'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(500, max(10, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $where = ['c.tenant_id = ?', 'c.is_both = 1'];
    $params = [$tenant_id];

    if ($search) {
        $where[] = '(c.customer_name LIKE ? OR c.customer_code LIKE ? OR s.supplier_code LIKE ? OR c.address LIKE ? OR c.email LIKE ? OR c.primary_phone LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 6, $searchTerm));
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

    $joins = "LEFT JOIN companies comp ON c.company_id = comp.id AND comp.tenant_id = c.tenant_id
            LEFT JOIN suppliers s ON c.linked_supplier_id = s.id AND s.tenant_id = c.tenant_id";

    $countSql = "SELECT COUNT(*) as total FROM customers c $joins $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    $sql = "SELECT c.id as customer_id, c.customer_code, c.customer_name, c.address, c.primary_phone, c.email,
            c.current_balance as customer_balance, c.opening_debit_amount as customer_opening_debit,
            c.opening_credit_amount as customer_opening_credit, c.is_blacklisted, c.created_at,
            c.linked_supplier_id as supplier_id,
            comp.company_name,
            s.supplier_code,
            s.current_balance as supplier_balance,
            s.opening_debit_amount as supplier_opening_debit,
            s.opening_credit_amount as supplier_opening_credit
            FROM customers c
            $joins
            $whereClause
            ORDER BY c.created_at DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statsSql = "SELECT
        COUNT(*) as total_both,
        COUNT(CASE WHEN is_blacklisted = FALSE THEN 1 END) as active_both,
        COUNT(CASE WHEN is_blacklisted = TRUE THEN 1 END) as blacklisted_both
        FROM customers WHERE tenant_id = ? AND is_both = 1";
    $stmt = $pdo->prepare($statsSql);
    $stmt->execute([$tenant_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'rows' => $rows,
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
