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
$unit_id = $_GET['unit_id'] ?? null;

if (!$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    if ($unit_id) {
        // Get scheme for specific unit
        $stmt = $pdo->prepare("
            SELECT id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs
            FROM product_schemes
            WHERE tenant_id = ? AND product_id = ? AND unit_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $product_id, $unit_id]);
        $scheme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'scheme' => $scheme ?: null
        ]);
    } else {
        // Get all schemes for product
        $stmt = $pdo->prepare("
            SELECT id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs
            FROM product_schemes
            WHERE tenant_id = ? AND product_id = ?
            ORDER BY unit_id
        ");
        $stmt->execute([$tenant_id, $product_id]);
        $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'schemes' => $schemes
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
