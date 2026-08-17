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
    $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    
    // Build WHERE clause
    $where = "si.tenant_id = ? AND si.status = 'Posted'";
    $countParams = [$tenant_id];
    $params = [$tenant_id];
    
    if ($company_id) {
        $where .= " AND si.company_id = ?";
        $countParams[] = $company_id;
        $params[] = $company_id;
    }
    
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM sale_order si WHERE $where");
    $countStmt->execute($countParams);
    $totalRecords = $countStmt->fetchColumn();
    
    // Get paginated data
    $stmt = $pdo->prepare("
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
        ORDER BY si.sale_date DESC, si.id DESC
        LIMIT ? OFFSET ?
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'invoices' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}