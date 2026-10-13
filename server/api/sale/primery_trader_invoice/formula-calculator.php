<?php
/**
 * Generic Formula Calculator for Tax Regimes
 * Evaluates formula_template with dynamic variables
 */

/**
 * $extraVars: optional array for invoice-level tokens, e.g.:
 *   ['total_bill' => 5000, 'value_excl_sales_tax' => 4800, 'net_amount' => 4900]
 * Item-level callers omit it; invoice-level callers pass it.
 */
function calculateTaxFromFormula($formulaTemplate, $basePrice, $ratePercentage, $extraVars = []) {
    if (!$formulaTemplate || $formulaTemplate === '0') {
        return 0;
    }

    $formula = $formulaTemplate;

    // Invoice-level tokens
    $formula = str_replace('{total_bill}',          $extraVars['total_bill']          ?? $basePrice, $formula);
    $formula = str_replace('{value_excl_sales_tax}', $extraVars['value_excl_sales_tax'] ?? $basePrice, $formula);
    $formula = str_replace('{net_amount}',           $extraVars['net_amount']           ?? $basePrice, $formula);

    // Item-level tokens
    $formula = str_replace('{trade_price}', $basePrice,      $formula);
    $formula = str_replace('{mrp}',         $basePrice,      $formula);
    $formula = str_replace('{import_value}',$basePrice,      $formula);
    $formula = str_replace('{rate}',        $ratePercentage, $formula);

    return evaluateFormula($formula);
}

function evaluateFormula($formula) {
    // Remove whitespace
    $formula = str_replace(' ', '', $formula);
    
    // Validate formula contains only allowed characters
    if (!preg_match('/^[0-9+\-*\/().]+$/', $formula)) {
        return 0;
    }
    
    try {
        // Use eval with strict validation
        $result = @eval('return ' . $formula . ';');
        return is_numeric($result) ? floatval($result) : 0;
    } catch (Exception $e) {
        return 0;
    }
}
?>
