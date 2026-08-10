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
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE tenant_id = ?");
    $countStmt->execute([$tenant_id]);
    $totalRecords = $countStmt->fetchColumn();
    
    // Get paginated data
    $stmt = $pdo->prepare("
        SELECT 
            pi.id,
            pi.bill_no,
            pi.purchase_date,
            pi.company_id,
            pi.supplier_id,
            s.supplier_name,
            COUNT(pii.id) as item_count,
            pi.net_amount,
            c.symbol as currency_symbol,
            COALESCE(SUM(pii.quantity), 0) as total_ordered_qty,
            COALESCE((
                SELECT SUM(pi_items.quantity)
                FROM purchase_invoice pinv
                JOIN purchase_invoice_items pi_items ON pinv.id = pi_items.purchase_invoice_id
                WHERE pinv.purchase_order_id = pi.id
            ), 0) as total_served_qty,
            CASE
                WHEN COALESCE(SUM(pii.quantity), 0) = 0 THEN 'Pending'
                WHEN COALESCE((
                    SELECT SUM(pi_items.quantity)
                    FROM purchase_invoice pinv
                    JOIN purchase_invoice_items pi_items ON pinv.id = pi_items.purchase_invoice_id
                    WHERE pinv.purchase_order_id = pi.id
                ), 0) >= COALESCE(SUM(pii.quantity), 0) THEN 'Fulfilled'
                WHEN COALESCE((
                    SELECT SUM(pi_items.quantity)
                    FROM purchase_invoice pinv
                    JOIN purchase_invoice_items pi_items ON pinv.id = pi_items.purchase_invoice_id
                    WHERE pinv.purchase_order_id = pi.id
                ), 0) > 0 THEN 'Partially Fulfilled'
                ELSE 'Pending'
            END as fulfillment_status
        FROM purchase_order pi
        LEFT JOIN suppliers s ON pi.supplier_id = s.id
        LEFT JOIN purchase_order_items pii ON pi.id = pii.purchase_invoice_id
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        WHERE pi.tenant_id = ?
        GROUP BY pi.id
        ORDER BY pi.purchase_date DESC, pi.id DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
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