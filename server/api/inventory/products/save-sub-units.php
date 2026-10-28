<?php
// Shared helper: saves loose sub-units (Half Kg, Pao, ...) for a product.
// Used by product-add.php and product-edit.php.
// Place in: server/api/inventory/products/save-sub-units.php

/**
 * Replaces all sub-units of a product with the ones in $json.
 * $json = JSON array of {label, qty_in_base, price, barcode, is_custom}
 * Rows with empty label, qty_in_base <= 0 are skipped.
 * If $json is null (field not posted) nothing is changed.
 */
function saveProductSubUnits(PDO $pdo, $tenant_id, $product_id, $json) {
    if ($json === null) {
        return 0;
    }

    $rows = json_decode($json, true);
    if (!is_array($rows)) {
        $rows = [];
    }

    $del = $pdo->prepare("DELETE FROM product_sub_units WHERE tenant_id = ? AND product_id = ?");
    $del->execute([$tenant_id, $product_id]);

    $ins = $pdo->prepare("
        INSERT INTO product_sub_units (tenant_id, product_id, label, qty_in_base, price, barcode, is_custom, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $count = 0;
    foreach ($rows as $i => $row) {
        $label = trim($row['label'] ?? '');
        $qty = floatval($row['qty_in_base'] ?? 0);
        $price = floatval($row['price'] ?? 0);
        $barcode = trim($row['barcode'] ?? '');
        $barcode = $barcode !== '' ? mb_substr($barcode, 0, 100) : null;
        $isCustom = !empty($row['is_custom']) ? 1 : 0;

        if ($label === '' || $qty <= 0) {
            continue;
        }

        $ins->execute([$tenant_id, $product_id, mb_substr($label, 0, 100), $qty, $price, $barcode, $isCustom, $i]);
        $count++;
    }

    return $count;
}