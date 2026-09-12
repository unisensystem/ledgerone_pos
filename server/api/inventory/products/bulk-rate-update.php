<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['products']) || !is_array($input['products'])) {
        echo json_encode(['success' => false, 'message' => 'No product data provided']);
        exit;
    }

    // Products table: prices only. batch_no / expiry_date columns on `products`
    // are not read anywhere in the product-add/edit UI (that UI reads/writes
    // stock_opening instead), so we stop writing them here to avoid a second,
    // unused source of truth.
    $productStmt = $pdo->prepare("
        UPDATE products SET
            purchase_price  = ?,
            trade_price     = ?,
            wholesale_price = ?,
            mrp             = ?,
            updated_at      = CURRENT_TIMESTAMP
        WHERE id = ? AND tenant_id = ?
    ");

    // stock_opening: this is what the product edit page actually reads batch_no /
    // expiry_date from (see stock-opening-get.php). A product can have multiple
    // stock_opening rows (per branch, and per unit for UOM-group products) — the
    // bulk-rate screen only exposes ONE batch/expiry per product, so when the user
    // fills it in we apply it to every stock_opening row for that product/tenant.
    $stockOpeningUpdateStmt = $pdo->prepare("
        UPDATE stock_opening SET
            batch_no    = ?,
            expiry_date = ?
        WHERE product_id = ? AND tenant_id = ?
    ");

    // Fallback for a product that has NO stock_opening row at all yet (never had a
    // branch/opening qty entered). The UPDATE above matches 0 rows in that case and
    // silently does nothing, so batch/expiry entered here would be lost. Instead we
    // create a bare stock_opening row carrying just the batch/expiry so it has
    // somewhere to live and shows up next time the product is opened for edit.
    // branch_id/unit_id are left NULL and opening_qty/opening_price at 0 since the
    // bulk-rate screen doesn't collect those.
    $stockOpeningInsertStmt = $pdo->prepare("
        INSERT INTO stock_opening
            (product_id, tenant_id, branch_id, opening_qty, opening_price, batch_no, expiry_date, unit_id)
        VALUES
            (?, ?, NULL, 0, 0, ?, ?, NULL)
    ");

    // Snapshot old prices for the Activity Log (before mutation)
    $productIds = array_values(array_unique(array_filter(array_map(fn($r) => intval($r['id'] ?? 0), $input['products']))));
    $oldProductsById = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $snapStmt = $pdo->prepare("SELECT id, code, name, purchase_price, trade_price, wholesale_price, mrp FROM products WHERE id IN ($placeholders) AND tenant_id = ?");
        $snapStmt->execute(array_merge($productIds, [$tenant_id]));
        foreach ($snapStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $oldProductsById[$row['id']] = $row;
        }
    }

    $pdo->beginTransaction();

    $updated = 0;
    foreach ($input['products'] as $row) {
        $id = intval($row['id'] ?? 0);
        if (!$id) continue;

        $batchNo    = !empty($row['batch_no']) ? $row['batch_no'] : null;
        $expiryDate = !empty($row['expiry_date']) ? $row['expiry_date'] : null;

        $productStmt->execute([
            $row['purchase_price']  ?? 0,
            $row['trade_price']     ?? 0,
            $row['wholesale_price'] ?? 0,
            $row['mrp']             ?? 0,
            $id,
            $tenant_id
        ]);

        // Only touch stock_opening's batch/expiry when the user actually entered
        // something in the bulk screen — an empty batch/expiry here means "wasn't
        // filled in", not "clear the existing batch on every branch row".
        if ($batchNo !== null || $expiryDate !== null) {
            $stockOpeningUpdateStmt->execute([
                $batchNo,
                $expiryDate,
                $id,
                $tenant_id
            ]);

            // No existing stock_opening row matched -> create one so the batch/expiry
            // actually gets saved instead of silently disappearing.
            if ($stockOpeningUpdateStmt->rowCount() === 0) {
                $stockOpeningInsertStmt->execute([
                    $id,
                    $tenant_id,
                    $batchNo,
                    $expiryDate
                ]);
            }
        }

        // Activity Log — per-product diff against the pre-update snapshot
        $oldProduct = $oldProductsById[$id] ?? null;
        if ($oldProduct) {
            $newProductValues = [
                'purchase_price' => $row['purchase_price'] ?? 0,
                'trade_price' => $row['trade_price'] ?? 0,
                'wholesale_price' => $row['wholesale_price'] ?? 0,
                'mrp' => $row['mrp'] ?? 0,
            ];
            $changeDescriptions = buildChangeDescription($oldProduct, $newProductValues, [
                'purchase_price' => 'Purchase Price', 'trade_price' => 'Trade Price',
                'wholesale_price' => 'Wholesale Price', 'mrp' => 'MRP'
            ]);
            if ($batchNo !== null || $expiryDate !== null) {
                $changeDescriptions[] = 'Batch/Expiry updated';
            }
            if (!empty($changeDescriptions)) {
                $productLabel = trim(($oldProduct['code'] ?? '') . ' ' . ($oldProduct['name'] ?? ''));
                logActivity(
                    $pdo, $tenant_id, $user_id, 'update', 'Product', $id, $productLabel ?: $oldProduct['code'],
                    "Bulk rate update on \"$productLabel\": " . implode('; ', $changeDescriptions),
                    $oldProduct, array_merge($newProductValues, ['batch_no' => $batchNo, 'expiry_date' => $expiryDate])
                );
            }
        }

        $updated++;
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => "$updated product(s) updated successfully"]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}