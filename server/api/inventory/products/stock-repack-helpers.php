<?php
// Shared helpers for Stock Repack (parent loose-kg stock -> child bag stock)

// Hard limit: ek entry mein is se zyada kg repack nahi ho sakta
const REPACK_MAX_KG = 100;

// stock_ledger.transaction_type value used for repack rows
// NOTE: agar transaction_type ENUM hai to is value ko ENUM mein add karna hoga
const REPACK_TRANSACTION_TYPE = 'Stock Repack';

/**
 * Conversion factor of a unit to its base unit (e.g. "10 Kg Bag" -> 10).
 * Returns null when a per-product / universal factor is not known.
 */
function repackFactor($pdo, $unit_id, $product_id, $product_conversion_factor = null) {
    if (empty($unit_id)) return 1.0;

    $stmt = $pdo->prepare("SELECT unit_scope, is_base_unit, conversion_factor FROM uom WHERE id = ?");
    $stmt->execute([$unit_id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$unit) return 1.0;
    if ($unit['is_base_unit'] == 1) return 1.0;

    if ($unit['unit_scope'] === 'per_product') {
        // UOM group products keep their factors in product_uom_conversions
        $convStmt = $pdo->prepare("SELECT conversion_factor FROM product_uom_conversions WHERE product_id = ? AND uom_id = ?");
        $convStmt->execute([$product_id, $unit_id]);
        $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
        if ($conv && !empty($conv['conversion_factor'])) {
            return (float) $conv['conversion_factor'];
        }
        // Default-unit products keep it on the product row
        if (!empty($product_conversion_factor) && $product_conversion_factor > 0) {
            return (float) $product_conversion_factor;
        }
        return null;
    }

    if (!empty($unit['conversion_factor'])) {
        return (float) $unit['conversion_factor'];
    }
    return null;
}

/**
 * Current stock of a product in one branch, expressed in BASE units (kg).
 * Sums every stock_ledger row (qty_in - qty_out) after converting its unit.
 */
function repackStockInBase($pdo, $tenant_id, $product_id, $branch_id, $product_conversion_factor = null) {
    $stmt = $pdo->prepare("
        SELECT unit_id, COALESCE(SUM(qty_in), 0) AS total_in, COALESCE(SUM(qty_out), 0) AS total_out
        FROM stock_ledger
        WHERE tenant_id = ? AND product_id = ? AND branch_id = ?
        GROUP BY unit_id
    ");
    $stmt->execute([$tenant_id, $product_id, $branch_id]);

    $total = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $factor = repackFactor($pdo, $row['unit_id'], $product_id, $product_conversion_factor);
        if ($factor === null) $factor = 1.0;
        $total += ((float) $row['total_in'] - (float) $row['total_out']) * $factor;
    }
    return $total;
}

/**
 * Branch is not asked from the user. If none is given, use the branch where the
 * parent product currently has the most stock.
 */
function repackResolveBranch($pdo, $tenant_id, $parent, $branch_id = 0) {
    if (!empty($branch_id)) return (int) $branch_id;

    $stmt = $pdo->prepare("
        SELECT DISTINCT branch_id FROM stock_ledger
        WHERE tenant_id = ? AND product_id = ? AND branch_id IS NOT NULL
    ");
    $stmt->execute([$tenant_id, $parent['id']]);

    $bestBranch = 0;
    $bestStock = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
        $stock = repackStockInBase($pdo, $tenant_id, $parent['id'], $candidate, $parent['product_conversion_factor']);
        if ($stock > $bestStock) {
            $bestStock = $stock;
            $bestBranch = (int) $candidate;
        }
    }

    if (!$bestBranch) {
        throw new Exception('No stock found for parent product ' . $parent['name']);
    }
    return $bestBranch;
}

/**
 * Loads child + parent product rows and the kg-per-bag of the child.
 * Throws Exception with a user-friendly message when the product cannot be repacked.
 */
function repackLoadContext($pdo, $tenant_id, $child_id) {
    $cols = "p.id, p.code, p.name, p.parent_product_id, p.uom_type, p.default_unit_id,
             p.product_conversion_factor, p.inventory_account_id, p.purchase_price,
             p.is_active, u.uom_name AS unit_name";

    $stmt = $pdo->prepare("
        SELECT $cols FROM products p
        LEFT JOIN uom u ON u.id = p.default_unit_id
        WHERE p.id = ? AND p.tenant_id = ?
    ");
    $stmt->execute([$child_id, $tenant_id]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$child) throw new Exception('Product not found');
    if (empty($child['parent_product_id'])) throw new Exception('This product has no parent product');

    $stmt->execute([$child['parent_product_id'], $tenant_id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent) throw new Exception('Parent product not found');

    if (empty($child['default_unit_id']) || $child['uom_type'] === 'group') {
        throw new Exception('Only products with a Default Unit (e.g. 10 Kg Bag) can be repacked');
    }
    if (empty($parent['default_unit_id']) || $parent['uom_type'] === 'group') {
        throw new Exception('Parent product must use a Default Unit (e.g. Kilogram)');
    }

    $kgPerBag = repackFactor($pdo, $child['default_unit_id'], $child['id'], $child['product_conversion_factor']);
    if ($kgPerBag === null || $kgPerBag <= 0 || $child['default_unit_id'] == $parent['default_unit_id']) {
        throw new Exception('Conversion factor (kg per bag) is not set for ' . $child['name']);
    }

    $parentFactor = repackFactor($pdo, $parent['default_unit_id'], $parent['id'], $parent['product_conversion_factor']);
    if ($parentFactor === null || $parentFactor <= 0) $parentFactor = 1.0;

    return [
        'child' => $child,
        'parent' => $parent,
        'kg_per_bag' => $kgPerBag,
        'parent_factor' => $parentFactor
    ];
}