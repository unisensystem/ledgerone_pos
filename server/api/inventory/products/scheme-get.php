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
    $product_id = $_GET['product_id'] ?? '';

    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }

    // Verify product belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$product_id, $tenant_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Get all schemes for this product
    $stmt = $pdo->prepare("
        SELECT ps.id, ps.unit_id, ps.promo_qty, ps.bonus_qty, ps.to_qty, ps.to_rs, u.uom_name
        FROM product_schemes ps
        JOIN uom u ON ps.unit_id = u.id
        WHERE ps.product_id = ? AND ps.tenant_id = ?
        ORDER BY ps.unit_id, ps.id
    ");
    $stmt->execute([$product_id, $tenant_id]);
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'schemes' => $schemes,
        'count' => count($schemes)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
