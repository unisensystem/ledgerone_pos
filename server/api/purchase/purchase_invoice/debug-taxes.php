<?php
require_once '../../../../includes/connection.php';

echo "Checking Tax Rates in Database\n";
echo "================================\n\n";

// Check tax_regimes
echo "1. Tax Regimes with application_level = 'invoice':\n";
$stmt = $pdo->prepare("
    SELECT id, regime_name, application_level, is_active 
    FROM tax_regimes 
    WHERE application_level = 'invoice' AND is_active = 1
");
$stmt->execute();
$regimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found: " . count($regimes) . " regimes\n";
foreach ($regimes as $r) {
    echo "  - ID: {$r['id']}, Name: {$r['regime_name']}\n";
}

echo "\n2. Tax Rates for Purchase Transactions:\n";
$stmt = $pdo->prepare("
    SELECT id, tax_regime_id, transaction_type, party_type, rate_percentage, is_active
    FROM tax_rates 
    WHERE transaction_type = 'purchase' AND is_active = 1
");
$stmt->execute();
$rates = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found: " . count($rates) . " rates\n";
foreach ($rates as $r) {
    echo "  - Regime: {$r['tax_regime_id']}, Type: {$r['transaction_type']}, Party: {$r['party_type']}, Rate: {$r['rate_percentage']}%\n";
}

echo "\n3. Testing Query for Supplier 152 (registered):\n";
$stmt = $pdo->prepare("
    SELECT 
        tr.id,
        tr.regime_name,
        COALESCE(
            (SELECT tax_rates.rate_percentage 
             FROM tax_rates 
             WHERE tax_rates.tax_regime_id = tr.id
               AND tax_rates.transaction_type = 'purchase'
               AND tax_rates.is_active = 1
               AND tax_rates.effective_from <= CURDATE()
               AND (tax_rates.effective_to IS NULL OR tax_rates.effective_to >= CURDATE())
               AND (
                   (tax_rates.party_type = 'all')
                   OR (tax_rates.party_type = ?)
               )
             ORDER BY tax_rates.party_type DESC
             LIMIT 1),
            0
        ) as rate_percentage
    FROM tax_regimes tr
    WHERE tr.application_level = 'invoice'
        AND tr.is_active = 1
        AND (tr.effective_from IS NULL OR tr.effective_from <= CURDATE())
        AND (tr.effective_to IS NULL OR tr.effective_to >= CURDATE())
    ORDER BY tr.regime_name
");
$stmt->execute(['registered_company']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Results: " . count($results) . " regimes\n";
foreach ($results as $r) {
    echo "  - {$r['regime_name']}: {$r['rate_percentage']}%\n";
}

echo "\n4. Supplier 152 Details:\n";
$stmt = $pdo->prepare("
    SELECT id, supplier_code, supplier_name, is_sales_tax_registered
    FROM suppliers
    WHERE id = 152
");
$stmt->execute();
$supp = $stmt->fetch(PDO::FETCH_ASSOC);
if ($supp) {
    echo "  - Code: {$supp['supplier_code']}\n";
    echo "  - Name: {$supp['supplier_name']}\n";
    echo "  - Registered: " . ($supp['is_sales_tax_registered'] ? 'Yes' : 'No') . "\n";
}
?>
