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
    // Get filter parameters
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $po_filter = $_GET['po_filter'] ?? null;
    $status_filter = $_GET['status'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_conditions = ['pe.tenant_id = ?'];
    $params = [$tenant_id];
    
    if ($date_from) {
        $where_conditions[] = 'pe.entry_date >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = 'pe.entry_date <= ?';
        $params[] = $date_to;
    }
    
    if ($po_filter) {
        $where_conditions[] = 'po.order_no LIKE ?';
        $params[] = '%' . $po_filter . '%';
    }
    
    if ($status_filter) {
        $where_conditions[] = 'pe.status = ?';
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM production_expenses pe
        INNER JOIN production_orders po ON pe.production_order_id = po.id
        WHERE $where_clause
    ";
    
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetch()['total'];
    
    // Get paginated data
    $sql = "
        SELECT 
            pe.id,
            pe.entry_date,
            pe.narration,
            pe.total_amount,
            pe.status,
            po.order_no,
            p.name as product_name
        FROM production_expenses pe
        INNER JOIN production_orders po ON pe.production_order_id = po.id
        INNER JOIN products p ON po.product_id = p.id
        WHERE $where_clause
        ORDER BY pe.entry_date DESC, pe.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $expenses = [];
    while ($row = $stmt->fetch()) {
        $expenses[] = [
            'id' => 'PE-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
            'entry_date' => $row['entry_date'],
            'po_number' => $row['order_no'],
            'finished_good' => $row['product_name'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'narration' => $row['narration']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $expenses,
        'pagination' => [
            'total' => $total_records,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_records / $per_page)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
