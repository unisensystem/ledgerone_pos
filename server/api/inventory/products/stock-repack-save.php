<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once 'stock-repack-helpers.php';

header('Content-Type: application/json');

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
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$product_id = (int) ($_POST['productId'] ?? 0);
$qty_raw = $_POST['qtyBags'] ?? '';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Please select a product from the list']);
    exit;
}

// Whole bags only (1, 2, 3 ...)
if (!ctype_digit((string) $qty_raw) || (int) $qty_raw < 1) {
    echo json_encode(['success' => false, 'message' => 'Please enter a whole number of bags (minimum 1)']);
    exit;
}
$bags = (int) $qty_raw;

try {
    $ctx = repackLoadContext($pdo, $tenant_id, $product_id);
    $child = $ctx['child'];
    $parent = $ctx['parent'];
    $kgPerBag = $ctx['kg_per_bag'];
    $parentFactor = $ctx['parent_factor'];
    $branch_id = repackResolveBranch($pdo, $tenant_id, $parent);

    $kgToUse = $bags * $kgPerBag;

    if ($kgToUse > REPACK_MAX_KG) {
        echo json_encode(['success' => false, 'message' => 'Maximum ' . REPACK_MAX_KG . ' kg can be added in one entry (you entered ' . $kgToUse . ' kg)']);
        exit;
    }

    $pdo->beginTransaction();

    // Lock the parent row so two users cannot over-draw the same parent stock
    $lockStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ? FOR UPDATE");
    $lockStmt->execute([$parent['id'], $tenant_id]);

    $parentAvailableKg = max(0, repackStockInBase($pdo, $tenant_id, $parent['id'], $branch_id, $parent['product_conversion_factor']));

    if ($kgToUse > $parentAvailableKg + 0.0000001) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Not enough parent stock. ' . $parent['name'] . ' has only ' . round($parentAvailableKg, 3) . ' kg available, but ' . $kgToUse . ' kg is needed.']);
        exit;
    }

    $childBagsBefore = repackStockInBase($pdo, $tenant_id, $child['id'], $branch_id, $child['product_conversion_factor']) / $kgPerBag;

    $account_id = $child['inventory_account_id'] ?: $parent['inventory_account_id'];
    $parentUnitCost = (float) $parent['purchase_price'];          // per parent unit (kg)
    $childUnitCost = $parentUnitCost * $kgPerBag;                  // per bag

    $ledgerStmt = $pdo->prepare("
        INSERT INTO stock_ledger (tenant_id, account_id, branch_id, product_id, reference_table, reference_id, qty_in, qty_out, unit_cost, unit_id, transaction_type, transaction_date)
        VALUES (?, ?, ?, ?, 'products', ?, ?, ?, ?, ?, ?, CURDATE())
    ");

    // 1) Parent: loose kg goes OUT (stored in parent's own default unit)
    $ledgerStmt->execute([
        $tenant_id, $account_id, $branch_id, $parent['id'], $child['id'],
        0, $kgToUse / $parentFactor, $parentUnitCost, $parent['default_unit_id'], REPACK_TRANSACTION_TYPE
    ]);

    // 2) Child: bags come IN (stored in child's own default unit, e.g. "10 Kg Bag")
    $ledgerStmt->execute([
        $tenant_id, $account_id, $branch_id, $child['id'], $parent['id'],
        $bags, 0, $childUnitCost, $child['default_unit_id'], REPACK_TRANSACTION_TYPE
    ]);

    $pdo->commit();

    $parentAfterKg = $parentAvailableKg - $kgToUse;
    $childBagsAfter = $childBagsBefore + $bags;

    logActivity(
        $pdo, $tenant_id, $user_id, 'update', 'Product', $child['id'], $child['code'],
        "Repacked $bags x \"{$child['name']}\" ({$kgToUse} kg) from parent \"{$parent['name']}\"",
        ['parent_kg' => round($parentAvailableKg, 3), 'child_bags' => round($childBagsBefore, 3)],
        ['parent_kg' => round($parentAfterKg, 3), 'child_bags' => round($childBagsAfter, 3), 'branch_id' => $branch_id]
    );

    echo json_encode([
        'success' => true,
        'message' => "$bags bag(s) added to {$child['name']}. {$kgToUse} kg deducted from {$parent['name']}.",
        'parent_after_kg' => round($parentAfterKg, 3),
        'child_bags_after' => round($childBagsAfter, 3)
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}