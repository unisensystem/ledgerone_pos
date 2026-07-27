<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$product_id = $_GET['product_id'] ?? null;
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

if (!$product_id) {
    echo json_encode(['success' => true, 'history' => []]);
    exit;
}

try {
    $sql = "
        SELECT 
            pi.purchase_date,
            pii.purchase_price,
            pii.quantity,
            cur.symbol as currency_symbol
        FROM purchase_invoice_items pii
        JOIN purchase_invoice pi ON pii.purchase_invoice_id = pi.id
        LEFT JOIN ledgerone_public.currencies cur ON pi.currency_id = cur.id
        WHERE pi.tenant_id = ?
        AND pii.product_id = ?
    ";
    $params = [$tenant_id, $product_id];

    if ($company_id) {
        $sql .= " AND pi.company_id = ?";
        $params[] = $company_id;
    }

    $sql .= " ORDER BY pi.purchase_date DESC, pi.id DESC LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'history' => $history]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
