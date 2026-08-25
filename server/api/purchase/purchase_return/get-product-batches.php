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
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$product_id = $_GET['product_id'] ?? null;
$branch_id  = $_GET['branch_id'] ?? null;

if (!$product_id || !$branch_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'product_id and branch_id are required']);
    exit;
}

try {
    // Group by batch_no, sum qty_in - qty_out to get real available quantity per batch.
    // Only batches with a non-empty batch_no and available qty > 0 are returned —
    // this is what makes "no stock = no batch to return" work automatically.
    $stmt = $pdo->prepare("
        SELECT 
            batch_no,
            MAX(expiry_date) AS batch_expiry_date,
            COALESCE(SUM(qty_in), 0) - COALESCE(SUM(qty_out), 0) AS available_qty
        FROM stock_ledger
        WHERE tenant_id = ?
          AND product_id = ?
          AND branch_id = ?
          AND batch_no IS NOT NULL
          AND batch_no != ''
        GROUP BY batch_no
        HAVING available_qty > 0
        ORDER BY batch_expiry_date ASC
    ");
    $stmt->execute([$tenant_id, $product_id, $branch_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'batches' => $batches
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ALTER TABLE purchase_return_items
// ADD COLUMN batch_no VARCHAR(100) NULL AFTER uom_id,
// ADD COLUMN batch_expiry_date DATE NULL AFTER batch_no;
