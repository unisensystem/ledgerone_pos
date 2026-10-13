<?php
require_once '../../../../includes/connection.php';
require_once 'stock-repack-helpers.php';

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$product_id = (int) ($_GET['product_id'] ?? 0);

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Please select a product from the list']);
    exit;
}

try {
    $ctx = repackLoadContext($pdo, $tenant_id, $product_id);
    $child = $ctx['child'];
    $parent = $ctx['parent'];
    $kgPerBag = $ctx['kg_per_bag'];
    $branch_id = repackResolveBranch($pdo, $tenant_id, $parent);

    $parentAvailableKg = max(0, repackStockInBase($pdo, $tenant_id, $parent['id'], $branch_id, $parent['product_conversion_factor']));
    $childKg = repackStockInBase($pdo, $tenant_id, $child['id'], $branch_id, $child['product_conversion_factor']);

    // Max bags = limited by parent's available kg AND by the 100 kg hard limit
    $allowedKg = min($parentAvailableKg, REPACK_MAX_KG);
    $maxBags = (int) floor(($allowedKg / $kgPerBag) + 0.0000001);

    echo json_encode([
        'success' => true,
        'data' => [
            'child' => ['id' => $child['id'], 'code' => $child['code'], 'name' => $child['name'], 'unit_name' => $child['unit_name']],
            'parent' => ['id' => $parent['id'], 'code' => $parent['code'], 'name' => $parent['name'], 'unit_name' => $parent['unit_name']],
            'kg_per_bag' => $kgPerBag,
            'parent_available_kg' => round($parentAvailableKg, 3),
            'child_bags' => round($childKg / $kgPerBag, 3),
            'max_kg' => REPACK_MAX_KG,
            'max_bags' => $maxBags
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}