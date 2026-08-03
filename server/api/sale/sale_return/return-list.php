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

    // Get user_companies restriction
    $ucStmt = $pdo->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
    $ucStmt->execute([$user_id]);
    $userCompanyIds = $ucStmt->fetchAll(PDO::FETCH_COLUMN);

    $companyWhere = '';
    $companyParams = [];
    if (!empty($userCompanyIds)) {
        $placeholders = implode(',', array_fill(0, count($userCompanyIds), '?'));
        $companyWhere = " AND si.company_id IN ($placeholders)";
        $companyParams = $userCompanyIds;
    }
    if ($company_id) {
        $companyWhere .= " AND si.company_id = ?";
        $companyParams[] = $company_id;
    }

    // Get total count
    $countSql = "SELECT COUNT(*) FROM sale_return si WHERE si.tenant_id = ? AND si.status = 'Posted'" . $companyWhere;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(array_merge([$tenant_id], $companyParams));
    $totalRecords = $countStmt->fetchColumn();

    // Get paginated data
    $sql = "
        SELECT 
            si.id,
            si.bill_no,
            si.sale_date,
            si.company_id,
            si.customer_id,
            c.customer_name,
            COUNT(sii.id) as item_count,
            si.net_amount,
            cur.symbol as currency_symbol
        FROM sale_return si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN sale_return_items sii ON si.id = sii.sale_invoice_id
        LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
        WHERE si.tenant_id = ? AND si.status = 'Posted'" . $companyWhere . "
        GROUP BY si.id
        ORDER BY si.sale_date DESC, si.id DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id], $companyParams);
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