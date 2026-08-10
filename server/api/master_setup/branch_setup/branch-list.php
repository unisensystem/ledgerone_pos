<?php
require_once '../../../../includes/connection.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $companyId = $_GET['company_id'] ?? '';
    $type = $_GET['type'] ?? '';
    
    $where = "b.tenant_id = ?";
    $params = [$tenant_id];
    
    if ($search) {
        $where .= " AND (b.branch_code LIKE ? OR b.branch_name LIKE ? OR b.city LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($status) {
        $where .= " AND b.is_active = ?";
        $params[] = ($status === 'active') ? 1 : 0;
    }
    
    if ($companyId) {
        $where .= " AND b.company_id = ?";
        $params[] = $companyId;
    }
    
    if ($type) {
        $where .= " AND b.branch_type = ?";
        $params[] = $type;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM branches b WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get paginated data
    $sql = "SELECT b.id, b.branch_code, b.branch_name, b.branch_type, b.parent_branch_id, b.address, b.country, b.state, b.city, b.zipcode, b.phone, b.email, b.manager_id, b.is_active, b.allows_sales, b.allows_inventory, b.company_id, c.company_name FROM branches b LEFT JOIN companies c ON b.company_id = c.id WHERE $where ORDER BY b.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $branches,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}