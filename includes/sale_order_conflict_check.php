<?php
/**
 * Shared by order-add.php and order-edit.php's mobile-token paths — a
 * mobile client only knows the price/stock it last cached, which may have
 * drifted while it was offline (or, for stock, simply because several
 * other field bookers' orders landed on the server first). Rather than
 * silently posting at a stale price or an oversold quantity, both
 * conflicts route the order to Draft for office review instead.
 *
 * $excludeOrderId lets an edit exclude its OWN existing committed quantity
 * from the "already committed to other orders" stock calculation — without
 * it, editing an order would count its own current rows as competing
 * demand against itself.
 *
 * Returns ['needsReview' => bool, 'priceChanges' => [...], 'stockWarnings' => [...]].
 */
function checkSaleOrderConflicts(PDO $pdo, int $tenant_id, array $items, ?int $excludeOrderId = null): array
{
    $needsReview = false;
    $priceChanges = [];
    $stockWarnings = [];

    // --- Price ---
    $productIds = array_values(array_unique(array_map(fn($it) => (int) $it['productId'], $items)));
    if ($productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $priceStmt = $pdo->prepare("SELECT id, name, trade_price FROM products WHERE tenant_id = ? AND id IN ($placeholders)");
        $priceStmt->execute([$tenant_id, ...$productIds]);
        $currentProducts = [];
        foreach ($priceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $currentProducts[$row['id']] = $row;
        }
        foreach ($items as $item) {
            $pid = (int) $item['productId'];
            $current = $currentProducts[$pid] ?? null;
            if ($current === null) continue;
            $sentPrice = (float) $item['salePrice'];
            $currentPrice = (float) $current['trade_price'];
            if (abs($currentPrice - $sentPrice) > 0.01) {
                $needsReview = true;
                $priceChanges[] = [
                    'productId' => $pid,
                    'productName' => $current['name'],
                    'submittedPrice' => $sentPrice,
                    'currentPrice' => $currentPrice,
                ];
            }
        }
    }

    // --- Stock ---
    // "Available to promise" = physical stock (stock_ledger, same formula as
    // server/api/sale/pos_invoice/get-product-stock.php) minus whatever is
    // already committed to OTHER pending sale orders for that product
    // (sale_order_items not yet consumed by a Posted sale_invoice). All
    // quantities are converted to base-unit terms first — otherwise
    // comparing "5 Carton" against "5 Piece" would silently say there's
    // enough stock when there isn't.
    $conversionFactorCache = [];
    $resolveFactor = function (int $productId, int $uomId) use ($pdo, &$conversionFactorCache) {
        $key = "$productId:$uomId";
        if (isset($conversionFactorCache[$key])) return $conversionFactorCache[$key];
        $stmt = $pdo->prepare("
            SELECT COALESCE(
                CASE
                    WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor
                    WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor
                    WHEN u.id = p.default_unit_id AND p.product_conversion_factor > 0 THEN p.product_conversion_factor
                    ELSE 1
                END, 1) as factor
            FROM uom u
            LEFT JOIN products p ON p.id = ?
            LEFT JOIN product_uom_conversions puc ON puc.product_id = ? AND puc.uom_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$productId, $productId, $uomId]);
        $factor = (float) ($stmt->fetchColumn() ?: 1);
        $conversionFactorCache[$key] = $factor;
        return $factor;
    };

    // This order's own requested quantity per product, in base units.
    $requestedBaseQty = [];
    foreach ($items as $item) {
        $pid = (int) $item['productId'];
        foreach ($item['unitEntries'] as $ue) {
            $qty = (float) $ue['quantity'];
            if ($qty <= 0) continue;
            $factor = $resolveFactor($pid, (int) $ue['uomId']);
            $requestedBaseQty[$pid] = ($requestedBaseQty[$pid] ?? 0) + ($qty * $factor);
        }
    }

    foreach ($requestedBaseQty as $pid => $requestedQty) {
        // Physical stock — identical formula to get-product-stock.php.
        $stockStmt = $pdo->prepare("
            SELECT p.inventory_account_id,
                COALESCE(SUM(
                    (sl.qty_in * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor WHEN sl.unit_id = p.default_unit_id AND p.product_conversion_factor > 0 THEN p.product_conversion_factor ELSE 1 END, 1)) -
                    (sl.qty_out * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor WHEN sl.unit_id = p.default_unit_id AND p.product_conversion_factor > 0 THEN p.product_conversion_factor ELSE 1 END, 1))
                ), 0) as stock
            FROM products p
            LEFT JOIN stock_ledger sl ON sl.tenant_id = p.tenant_id AND sl.product_id = p.id AND sl.account_id = p.inventory_account_id
            LEFT JOIN uom u ON sl.unit_id = u.id
            LEFT JOIN product_uom_conversions puc ON puc.product_id = sl.product_id AND puc.uom_id = sl.unit_id
            WHERE p.tenant_id = ? AND p.id = ?
            GROUP BY p.id
        ");
        $stockStmt->execute([$tenant_id, $pid]);
        $stockRow = $stockStmt->fetch(PDO::FETCH_ASSOC);
        $physicalStock = $stockRow ? (float) $stockRow['stock'] : 0.0;

        // Already committed to other pending (not-yet-invoiced) sale orders —
        // excludes this order's own current rows when editing, so it isn't
        // counted as competing demand against itself.
        $orderedSql = "
            SELECT COALESCE(SUM(
                soi.quantity * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor WHEN soi.uom_id = p.default_unit_id AND p.product_conversion_factor > 0 THEN p.product_conversion_factor ELSE 1 END, 1)
            ), 0) as qty
            FROM sale_order_items soi
            JOIN sale_order so ON so.id = soi.sale_invoice_id
            LEFT JOIN uom u ON soi.uom_id = u.id
            LEFT JOIN products p ON p.id = soi.product_id
            LEFT JOIN product_uom_conversions puc ON puc.product_id = soi.product_id AND puc.uom_id = soi.uom_id
            WHERE so.tenant_id = ? AND so.status = 'Posted' AND soi.product_id = ? AND soi.parent_row_id IS NULL
        ";
        $orderedParams = [$tenant_id, $pid];
        if ($excludeOrderId !== null) {
            $orderedSql .= " AND so.id <> ?";
            $orderedParams[] = $excludeOrderId;
        }
        $orderedStmt = $pdo->prepare($orderedSql);
        $orderedStmt->execute($orderedParams);
        $outstandingOrdered = (float) ($orderedStmt->fetchColumn() ?: 0);

        $servedSql = "
            SELECT COALESCE(SUM(
                sii.quantity * COALESCE(CASE WHEN u.base_unit_id IS NOT NULL AND u.unit_scope = 'universal' THEN u.conversion_factor WHEN puc.conversion_factor IS NOT NULL THEN puc.conversion_factor WHEN sii.uom_id = p.default_unit_id AND p.product_conversion_factor > 0 THEN p.product_conversion_factor ELSE 1 END, 1)
            ), 0) as qty
            FROM sale_invoice_items sii
            JOIN sale_invoice si ON si.id = sii.sale_invoice_id
            JOIN sale_order so ON so.id = si.sale_order_id
            LEFT JOIN uom u ON sii.uom_id = u.id
            LEFT JOIN products p ON p.id = sii.product_id
            LEFT JOIN product_uom_conversions puc ON puc.product_id = sii.product_id AND puc.uom_id = sii.uom_id
            WHERE so.tenant_id = ? AND si.status = 'Posted' AND sii.product_id = ?
        ";
        $servedParams = [$tenant_id, $pid];
        if ($excludeOrderId !== null) {
            $servedSql .= " AND so.id <> ?";
            $servedParams[] = $excludeOrderId;
        }
        $servedStmt = $pdo->prepare($servedSql);
        $servedStmt->execute($servedParams);
        $alreadyServed = (float) ($servedStmt->fetchColumn() ?: 0);

        $committed = max(0.0, $outstandingOrdered - $alreadyServed);
        $availableToPromise = $physicalStock - $committed;

        if ($requestedQty > $availableToPromise + 0.001) {
            $needsReview = true;
            $stockWarnings[] = [
                'productId' => $pid,
                'requestedQty' => $requestedQty,
                'availableToPromise' => $availableToPromise,
                'physicalStock' => $physicalStock,
                'committedToOtherOrders' => $committed,
            ];
        }
    }

    return ['needsReview' => $needsReview, 'priceChanges' => $priceChanges, 'stockWarnings' => $stockWarnings];
}
