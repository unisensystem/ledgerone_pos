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
    // Support both a single id (backward compatible) and a bulk ids[] array
    $product_ids = [];
    if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $product_ids = array_values(array_unique(array_map('intval', $_POST['ids'])));
    } elseif (!empty($_POST['id'])) {
        $product_ids = [(int)$_POST['id']];
    }

    if (empty($product_ids)) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }

    $deletedCount = 0;
    $notFoundIds = [];

    $pdo->beginTransaction();

    foreach ($product_ids as $product_id) {
        // Verify product belongs to tenant
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$product_id, $tenant_id]);
        if (!$stmt->fetch()) {
            $notFoundIds[] = $product_id;
            continue;
        }

        // Delete related records first
        $pdo->prepare("DELETE FROM stock_opening WHERE product_id = ? AND tenant_id = ?")->execute([$product_id, $tenant_id]);
        $pdo->prepare("DELETE FROM stock_ledger WHERE product_id = ? AND tenant_id = ?")->execute([$product_id, $tenant_id]);

        // Delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$product_id, $tenant_id]);

        $deletedCount++;
    }

    if ($deletedCount === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $pdo->commit();

    $message = $deletedCount === 1
        ? 'Product deleted successfully'
        : $deletedCount . ' products deleted successfully';

    if (!empty($notFoundIds)) {
        $message .= ' (' . count($notFoundIds) . ' not found, skipped)';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_count' => $deletedCount,
        'not_found_ids' => $notFoundIds
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}