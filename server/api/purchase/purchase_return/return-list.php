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
    $company_id = isset($_GET['company_id']) && $_GET['company_id'] !== '' ? (int)$_GET['company_id'] : null;

    // Get user_companies restriction
    $ucStmt = $pdo->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
    $ucStmt->execute([$user_id]);
    $userCompanyIds = $ucStmt->fetchAll(PDO::FETCH_COLUMN);

    $companyWhere = '';
    $companyParams = [];
    if (!empty($userCompanyIds)) {
        $placeholders = implode(',', array_fill(0, count($userCompanyIds), '?'));
        $companyWhere = " AND pi.company_id IN ($placeholders)";
        $companyParams = $userCompanyIds;
    }
    if ($company_id) {
        $companyWhere .= " AND pi.company_id = ?";
        $companyParams[] = $company_id;
    }

    // Get total count
    $countSql = "SELECT COUNT(*) FROM purchase_return pi WHERE pi.tenant_id = ?" . $companyWhere;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(array_merge([$tenant_id], $companyParams));
    $totalRecords = $countStmt->fetchColumn();

    // Get paginated data
    $sql = "
        SELECT 
            pi.id,
            pi.bill_no,
            pi.purchase_date,
            pi.supplier_id,
            s.supplier_name,
            COUNT(pii.id) as item_count,
            pi.net_amount,
            c.symbol as currency_symbol,
            pi.company_id
        FROM purchase_return pi
        LEFT JOIN suppliers s ON pi.supplier_id = s.id
        LEFT JOIN purchase_return_items pii ON pi.id = pii.purchase_invoice_id
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        WHERE pi.tenant_id = ?" . $companyWhere . "
        GROUP BY pi.id
        ORDER BY pi.purchase_date DESC, pi.id DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$tenant_id], $companyParams));
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