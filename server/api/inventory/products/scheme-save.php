<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $product_id = $_POST['product_id'] ?? '';
    $schemes = $_POST['schemes'] ?? [];

    if (empty($product_id) || empty($schemes)) {
        echo json_encode(['success' => false, 'message' => 'Product ID and schemes are required']);
        exit;
    }

    // Verify product belongs to tenant
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$product_id, $tenant_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Collect all unit IDs from schemes
    $unitIds = [];
    foreach ($schemes as $scheme) {
        if (!empty($scheme['unit_id'])) {
            $unitIds[] = $scheme['unit_id'];
        }
    }
    $unitIds = array_unique($unitIds);

    // Delete existing schemes for this product and these units
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $params = array_merge([$product_id, $tenant_id], $unitIds);
    $pdo->prepare("DELETE FROM product_schemes WHERE product_id = ? AND tenant_id = ? AND unit_id IN ($placeholders)")
        ->execute($params);

    // Insert new schemes
    $stmt = $pdo->prepare("
        INSERT INTO product_schemes (tenant_id, product_id, unit_id, promo_qty, bonus_qty, to_qty, to_rs)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $inserted = 0;
    foreach ($schemes as $scheme) {
        if (!empty($scheme['unit_id']) && (!empty($scheme['promo_qty']) || !empty($scheme['to_qty']))) {
            $stmt->execute([
                $tenant_id,
                $product_id,
                $scheme['unit_id'],
                $scheme['promo_qty'] ?? 0,
                $scheme['bonus_qty'] ?? 0,
                $scheme['to_qty'] ?? 0,
                $scheme['to_rs'] ?? 0
            ]);
            $inserted++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Schemes saved successfully ($inserted scheme(s))",
        'count' => $inserted
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
