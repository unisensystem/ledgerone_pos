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
    $product_id = $_GET['id'] ?? '';
    
    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, sc.subcategory_name, u.uom_name, u.unit_scope, u.is_base_unit, ug.group_name as uom_group_name, tr.regime_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
        LEFT JOIN uom u ON p.default_unit_id = u.id
        LEFT JOIN uom_groups ug ON p.uom_group_id = ug.id
        LEFT JOIN tax_regimes tr ON p.tax_regime_id = tr.id
        WHERE p.id = ? AND p.tenant_id = ?
    ");
    $stmt->execute([$product_id, $tenant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'product' => $product]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}