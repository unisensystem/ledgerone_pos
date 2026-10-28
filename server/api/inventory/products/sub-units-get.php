<?php
// Returns the loose sub-units (Half Kg, Pao, ...) of a product.
// Place in: server/api/inventory/products/sub-units-get.php
// Used by product edit page, and can be reused by sale invoice / POS pages.
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'product_id is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, label, qty_in_base, price, barcode, is_custom, sort_order
        FROM product_sub_units
        WHERE tenant_id = ? AND product_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$tenant_id, $product_id]);

    echo json_encode(['success' => true, 'sub_units' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}