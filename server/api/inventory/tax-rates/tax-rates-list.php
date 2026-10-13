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
    $tax_type = $_GET['tax_type'] ?? '';
    $transaction_type = $_GET['transaction_type'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $sql = "
        SELECT id, tax_name, tax_type, transaction_type, rate_percentage, tax_authority, 
               is_active, effective_from, effective_to, is_adjustable, is_refundable,
               (CASE WHEN tenant_id = 0 THEN 'System' ELSE 'Custom' END) as record_type,
               tenant_id
        FROM tax_rates
        WHERE (tenant_id = ? OR tenant_id = 0)
    ";
    
    $params = [$tenant_id];
    
    if ($search) {
        $sql .= " AND (tax_name LIKE ? OR tax_authority LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($tax_type) {
        $sql .= " AND tax_type = ?";
        $params[] = $tax_type;
    }
    
    if ($transaction_type) {
        $sql .= " AND transaction_type = ?";
        $params[] = $transaction_type;
    }
    
    if ($status !== '') {
        $active = ($status === 'active') ? 1 : 0;
        $sql .= " AND is_active = ?";
        $params[] = $active;
    }
    
    // Count total records
    $countSql = "
        SELECT COUNT(*) as total 
        FROM tax_rates
        WHERE (tenant_id = ? OR tenant_id = 0)
    ";
    $countParams = [$tenant_id];
    
    if ($search) {
        $countSql .= " AND (tax_name LIKE ? OR tax_authority LIKE ?)";
        $countParams[] = "%$search%";
        $countParams[] = "%$search%";
    }
    if ($tax_type) {
        $countSql .= " AND tax_type = ?";
        $countParams[] = $tax_type;
    }
    if ($transaction_type) {
        $countSql .= " AND transaction_type = ?";
        $countParams[] = $transaction_type;
    }
    if ($status !== '') {
        $active = ($status === 'active') ? 1 : 0;
        $countSql .= " AND is_active = ?";
        $countParams[] = $active;
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Get paginated records
    $sql .= " ORDER BY effective_from DESC, tax_name ASC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taxRates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tax_rates' => $taxRates,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'per_page' => $limit
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
