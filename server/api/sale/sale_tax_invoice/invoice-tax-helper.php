<?php
/**
 * Invoice Tax Helper Functions
 * Handles saving, retrieving, and updating invoice-level taxes
 */

/**
 * Save invoice-level taxes to database
 * @param PDO $pdo
 * @param int $tenant_id
 * @param int $invoice_id
 * @param array $taxes Array of tax data
 * @param int $user_id
 * @return bool
 * @throws Exception
 */
function saveInvoiceTaxes($pdo, $tenant_id, $invoice_id, $taxes, $user_id) {
    if (empty($taxes) || !is_array($taxes)) {
        return true; // No taxes to save
    }

    try {
        // Delete existing taxes for this invoice
        $deleteStmt = $pdo->prepare("
            DELETE FROM sale_invoice_taxes 
            WHERE sale_invoice_id = ? AND tenant_id = ?
        ");
        $deleteStmt->execute([$invoice_id, $tenant_id]);

        // Insert new taxes
        $insertStmt = $pdo->prepare("
            INSERT INTO sale_invoice_taxes (
                tenant_id, sale_invoice_id, tax_regime_id, tax_rate_id,
                tax_name, rate_percentage, base_amount, tax_amount,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $saveCount = 0;
        foreach ($taxes as $tax) {
            $taxRegimeId = $tax['taxRegimeId'] ?? null;
            $taxRateId = $tax['taxRateId'] ?? null;
            $taxName = $tax['taxName'] ?? 'Unknown Tax';
            $ratePercentage = floatval($tax['ratePercentage'] ?? 0);
            $baseAmount = floatval($tax['baseAmount'] ?? 0);
            $taxAmount = floatval($tax['taxAmount'] ?? 0);

            // Only save if there's a tax amount or rate
            if ($taxAmount > 0 || $ratePercentage > 0) {
                $insertStmt->execute([
                    $tenant_id,
                    $invoice_id,
                    $taxRegimeId,
                    $taxRateId,
                    $taxName,
                    $ratePercentage,
                    $baseAmount,
                    $taxAmount,
                    $user_id,
                    $user_id
                ]);
                $saveCount++;
            }
        }

        error_log("Saved {$saveCount} taxes for invoice {$invoice_id}");
        return true;

    } catch (Exception $e) {
        error_log("Error saving invoice taxes: " . $e->getMessage());
        throw new Exception("Failed to save invoice taxes: " . $e->getMessage());
    }
}

/**
 * Get invoice taxes from database
 * @param PDO $pdo
 * @param int $invoice_id
 * @param int $tenant_id
 * @return array
 */
function getInvoiceTaxes($pdo, $invoice_id, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            tax_regime_id,
            tax_rate_id,
            tax_name,
            rate_percentage,
            base_amount,
            tax_amount
        FROM sale_invoice_taxes
        WHERE sale_invoice_id = ? AND tenant_id = ?
        ORDER BY id
    ");
    $stmt->execute([$invoice_id, $tenant_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate total invoice taxes
 * @param array $taxes Array of tax data
 * @return float
 */
function calculateTotalInvoiceTaxes($taxes) {
    if (empty($taxes)) {
        return 0.00;
    }

    $total = 0;
    foreach ($taxes as $tax) {
        $total += floatval($tax['taxAmount'] ?? $tax['tax_amount'] ?? 0);
    }

    return round($total, 2);
}

/**
 * Update total invoice tax amount in sale_invoice table
 * @param PDO $pdo
 * @param int $invoice_id
 * @param int $tenant_id
 * @param float $total_tax_amount
 * @return bool
 */
function updateInvoiceTotalTax($pdo, $invoice_id, $tenant_id, $total_tax_amount) {
    $stmt = $pdo->prepare("
        UPDATE sale_invoice 
        SET total_invoice_tax_amount = ? 
        WHERE id = ? AND tenant_id = ?
    ");
    return $stmt->execute([$total_tax_amount, $invoice_id, $tenant_id]);
}

?>
