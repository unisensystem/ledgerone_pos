<?php
require_once '../../../../includes/connection.php';
require_once 'formula-calculator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customer_id = $_GET['customer_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;
$sale_price_setting = $_GET['sale_price_setting'] ?? 'trade_price'; // 'trade_price' or 'mrp'

if (!$customer_id || !$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Customer ID and Product ID are required']);
    exit;
}

try {
    // Step 1: Get product's tax_regime_id and price values
    $stmt = $pdo->prepare("
        SELECT id, tax_regime_id, trade_price, mrp 
        FROM products 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$product_id, $tenant_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Step 2: Get customer information
    $stmt = $pdo->prepare("
        SELECT id, customer_type_id, is_sales_tax_registered, is_filer 
        FROM customers 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$customer_id, $tenant_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    // Step 3: Get tax regime details
    $stmt = $pdo->prepare("
        SELECT id, tax_base, formula_template, application_level, is_tax_inclusive
        FROM tax_regimes
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$product['tax_regime_id']]);
    $taxRegime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taxRegime) {
        echo json_encode(['success' => false, 'message' => 'Tax regime not found', 'tax_rate' => 0, 'tax_amount' => 0]);
        exit;
    }
    
    $isTaxInclusive = (int)($taxRegime['is_tax_inclusive'] ?? 0);

    // Determine the base price to use for tax calculation based on tax_base from regime
    $basePrice = 0;
    $taxBase = $taxRegime['tax_base'];

    if ($taxBase === 'mrp') {
        $basePrice = floatval($product['mrp']);
    } else {
        $basePrice = floatval($product['trade_price']);
    }

    if (!$basePrice) {
        echo json_encode(['success' => false, 'message' => 'Base price (' . $taxBase . ') is not set for this product', 'tax_rate' => 0, 'tax_amount' => 0]);
        http_response_code(400);
        exit;
    }

    // For inclusive-tax regimes the base price is always derived from tax_base (e.g. MRP),
    // independent of the invoice's sale_price_setting, so no mismatch is possible.
    $salePriceSetting = $_GET['sale_price_setting'] ?? 'trade_price';
    if (!$isTaxInclusive && $taxBase !== $salePriceSetting) {
        echo json_encode([
            'success' => false,
            'message' => 'Tax regime requires ' . $taxBase . ' but invoice is using ' . $salePriceSetting,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'mismatch' => true
        ]);
        http_response_code(400);
        exit;
    }
    
    // Determine party type and registration status
    $is_registered = $customer['is_sales_tax_registered'] == 1 ? 'registered_company' : 'unregistered';
    
    // Step 4: Query tax_rates with filters
    // For item-level taxes, ignore is_filer - only check customer_type_id and party_type
    $stmt = $pdo->prepare("
        SELECT 
            tr.id,
            tr.rate_percentage,
            tr.tax_name,
            tr.tax_type
        FROM tax_rates tr
        WHERE 
            tr.tax_regime_id = ? 
            AND tr.is_active = 1
            AND tr.transaction_type = 'sale'
            AND (
                -- Match specific customer_type_id with specific party_type
                (tr.customer_type_id = ? AND tr.party_type = ?)
                OR
                -- Match specific customer_type_id with 'all' party_type
                (tr.customer_type_id = ? AND tr.party_type = 'all')
                OR
                -- Match NULL customer_type_id (all customers) with specific party_type
                (tr.customer_type_id IS NULL AND tr.party_type = ?)
                OR
                -- Match NULL customer_type_id (all customers) with 'all' party_type
                (tr.customer_type_id IS NULL AND tr.party_type = 'all')
            )
        ORDER BY 
            CASE 
                WHEN tr.customer_type_id = ? AND tr.party_type = ? THEN 0
                WHEN tr.customer_type_id = ? AND tr.party_type = 'all' THEN 1
                WHEN tr.customer_type_id IS NULL AND tr.party_type = ? THEN 2
                ELSE 3
            END,
            tr.rate_percentage DESC
        LIMIT 1
    ");
    
    $stmt->execute([
        $product['tax_regime_id'],
        $customer['customer_type_id'],
        $is_registered,
        $customer['customer_type_id'],
        $is_registered,
        $customer['customer_type_id'],
        $is_registered,
        $customer['customer_type_id'],
        $is_registered
    ]);
    
    $taxRate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taxRate) {
        $applicationLevel = $taxRegime['application_level'] ?? 'item';
        echo json_encode([
            'success' => true,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'tax_name' => 'No applicable tax',
            'base_price' => $basePrice,
            'application_level' => $applicationLevel,
            'message' => 'No tax rate found for this customer'
        ]);
        exit;
    }
    
    $ratePercentage = floatval($taxRate['rate_percentage']);
    $formulaTemplate = $taxRegime['formula_template'];
    $applicationLevel = $taxRegime['application_level'] ?? 'item';

    // Step 5: Calculate tax amount
    // Inclusive: tax is embedded in the price — extract it via back-calculation
    // Exclusive: tax is added on top — evaluate the formula template
    if ($isTaxInclusive) {
        $taxAmount = $ratePercentage > 0
            ? $basePrice * $ratePercentage / (100 + $ratePercentage)
            : 0;
    } else {
        $taxAmount = calculateTaxFromFormula($formulaTemplate, $basePrice, $ratePercentage);
    }

    echo json_encode([
        'success' => true,
        'tax_rate' => $ratePercentage,
        'tax_amount' => round($taxAmount, 2),
        'tax_name' => $taxRate['tax_name'],
        'tax_type' => $taxRate['tax_type'],
        'base_price' => $basePrice,
        'is_registered' => $is_registered,
        'formula_template' => $formulaTemplate,
        'tax_base' => $taxBase,
        'application_level' => $applicationLevel,
        'is_tax_inclusive' => $isTaxInclusive
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'tax_rate' => 0, 'tax_amount' => 0]);
}
?>
