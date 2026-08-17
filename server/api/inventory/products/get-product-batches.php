<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$product_id = $_GET['product_id'] ?? null;
$branch_id  = $_GET['branch_id'] ?? null;

if (empty($product_id)) {
    echo json_encode(['success' => false, 'message' => 'product_id is required']);
    exit;
}

try {
    // Remaining qty per batch = SUM(qty_in) - SUM(qty_out), FIFO ordered by
    // earliest transaction date/id first (oldest batch consumed first).
    // Only batches with remaining qty > 0 are returned, so once a batch
    // (e.g. P1) is fully consumed it stops appearing and the next one
    // (e.g. P2) becomes the first option automatically.
    $sql = "
        SELECT
            batch_no,
            batch_expiry_date,
            SUM(COALESCE(qty_in, 0)) - SUM(COALESCE(qty_out, 0)) AS remaining_qty,
            MIN(transaction_date) AS first_transaction_date,
            MIN(id) AS first_id
        FROM stock_ledger
        WHERE tenant_id = ?
          AND product_id = ?
          AND batch_no IS NOT NULL
          AND batch_no != ''
    ";
    $params = [$tenant_id, $product_id];

    if (!empty($branch_id)) {
        $sql .= " AND branch_id = ?";
        $params[] = $branch_id;
    }

    $sql .= "
        GROUP BY batch_no, batch_expiry_date
        HAVING remaining_qty > 0
        ORDER BY first_transaction_date ASC, first_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'batches' => array_map(function ($b) {
            return [
                'batch_no' => $b['batch_no'],
                'batch_expiry_date' => $b['batch_expiry_date'],
                'remaining_qty' => (float) $b['remaining_qty']
            ];
        }, $batches)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}