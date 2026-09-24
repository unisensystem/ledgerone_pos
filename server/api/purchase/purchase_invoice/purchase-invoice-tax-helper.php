<?php
/**
 * Purchase Invoice Tax Helper
 */

function savePurchaseInvoiceTaxes($pdo, $tenant_id, $invoice_id, $taxes, $user_id) {
    if (!is_array($taxes)) $taxes = [];

    // Always clear existing taxes first, even when $taxes is empty — otherwise
    // removing/zeroing a previously-saved invoice-level tax leaves the old row
    // in place and print/edit keep showing the stale rate.
    $pdo->prepare("DELETE FROM purchase_invoice_taxes WHERE purchase_invoice_id = ? AND tenant_id = ?")
        ->execute([$invoice_id, $tenant_id]);

    if (empty($taxes)) return true;

    $stmt = $pdo->prepare("
        INSERT INTO purchase_invoice_taxes
            (tenant_id, purchase_invoice_id, tax_regime_id, tax_name, rate_percentage, base_amount, tax_amount, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($taxes as $tax) {
        $rate   = floatval($tax['ratePercentage'] ?? 0);
        $amount = floatval($tax['taxAmount'] ?? 0);
        if ($rate > 0 || $amount > 0) {
            $stmt->execute([
                $tenant_id,
                $invoice_id,
                $tax['taxRegimeId'] ?? null,
                $tax['taxName']     ?? 'Tax',
                $rate,
                floatval($tax['baseAmount'] ?? 0),
                $amount,
                $user_id,
                $user_id
            ]);
        }
    }
    return true;
}

function getPurchaseInvoiceTaxes($pdo, $invoice_id, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT id, tax_regime_id, tax_name, rate_percentage, base_amount, tax_amount
        FROM purchase_invoice_taxes
        WHERE purchase_invoice_id = ? AND tenant_id = ?
        ORDER BY id
    ");
    $stmt->execute([$invoice_id, $tenant_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
