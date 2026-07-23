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
    
    // Get filters
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    $companyFilter = $_GET['company'] ?? null;
    $customerFilter = $_GET['customer'] ?? null;
    $search = $_GET['search'] ?? null;
    
    // Build WHERE clause
    $whereConditions = ["si.tenant_id = ?", "si.status = 'Posted'"];
    $params = [$tenant_id];
    
    if ($dateFrom) {
        $whereConditions[] = "si.sale_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $whereConditions[] = "si.sale_date <= ?";
        $params[] = $dateTo;
    }
    if ($companyFilter) {
        $whereConditions[] = "co.company_name = ?";
        $params[] = $companyFilter;
    }
    if ($customerFilter) {
        $whereConditions[] = "c.customer_name = ?";
        $params[] = $customerFilter;
    }
    if ($search) {
        $whereConditions[] = "(si.bill_no LIKE ? OR c.customer_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count
    $countQuery = "SELECT COUNT(DISTINCT si.id) FROM sale_invoice si 
                   LEFT JOIN customers c ON si.customer_id = c.id 
                   LEFT JOIN companies co ON si.company_id = co.id 
                   WHERE {$whereClause}";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    
    // Get paginated data
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    $query = "SELECT 
            si.id,
            si.bill_no,
            si.sale_date,
            si.customer_id,
            c.customer_name,
            co.company_name,
            COUNT(sii.id) as item_count,
            si.net_amount,
            cur.symbol as currency_symbol
        FROM sale_invoice si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN companies co ON si.company_id = co.id
        LEFT JOIN sale_invoice_items sii ON si.id = sii.sale_invoice_id
        LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        WHERE {$whereClause}
        GROUP BY si.id
        ORDER BY si.sale_date DESC, si.id DESC
        LIMIT {$limit} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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