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
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM station_daily_usage WHERE tenant_id = ?");
    $countStmt->execute([$tenant_id]);
    $totalRecords = $countStmt->fetchColumn();
    
    // Get paginated data
    $stmt = $pdo->prepare("
        SELECT 
            sdu.id,
            sdu.usage_date,
            sdu.opening_reading,
            sdu.closing_reading,
            sdu.rate,
            sdu.total_dispensed,
            sdu.total_revenue,
            b.branch_name,
            fs.station_name,
            p.name as product_name,
            p.mrp as product_mrp,
            u.uom_name as unit_name
        FROM station_daily_usage sdu
        INNER JOIN branches b ON sdu.branch_id = b.id
        INNER JOIN fueling_stations fs ON sdu.station_id = fs.id
        INNER JOIN products p ON sdu.product_id = p.id
        INNER JOIN uom u ON sdu.unit_id = u.id
        WHERE sdu.tenant_id = ?
        ORDER BY sdu.usage_date DESC, sdu.id DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$tenant_id]);
    $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'data' => $readings,
        'pagination' => [
            'current_page' => $page,
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $limit),
            'limit' => $limit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>