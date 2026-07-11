<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

if (!$tenant_id || !$supplier_id || !$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Get product tax_regime_id and trade_price
    $productStmt = $pdo->prepare("
        SELECT tax_regime_id, trade_price 
        FROM products 
        WHERE id = ? AND tenant_id = ?
    ");
    $productStmt->execute([$product_id, $tenant_id]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'message' => 'Product not found'
        ]);
        exit;
    }
    
    if (!$product['tax_regime_id']) {
        echo json_encode([
            'success' => false,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'message' => 'No tax regime configured for this product'
        ]);
        exit;
    }
    
    // Get tax regime details
    $regimeStmt = $pdo->prepare("
        SELECT 
            id,
            regime_code,
            regime_name,
            tax_base,
            formula_template,
            application_level
        FROM tax_regimes 
        WHERE id = ? 
            AND tenant_id = ?
            AND is_active = 1
    ");
    $regimeStmt->execute([$product['tax_regime_id'], $tenant_id]);
    $regime = $regimeStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$regime) {
        echo json_encode([
            'success' => false,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'message' => 'Tax regime not found or inactive'
        ]);
        exit;
    }
    
    // Get applicable tax rate
    $rateStmt = $pdo->prepare("
        SELECT rate_percentage 
        FROM tax_rates 
        WHERE tax_regime_id = ?
            AND tenant_id = ?
            AND transaction_type = 'purchase'
            AND is_active = 1
        LIMIT 1
    ");
    $rateStmt->execute([$regime['id'], $tenant_id]);
    $taxRate = $rateStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taxRate) {
        echo json_encode([
            'success' => false,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'message' => 'No applicable tax rate found for this product'
        ]);
        exit;
    }
    
    $ratePercentage = floatval($taxRate['rate_percentage']);
    $basePrice = floatval($product['trade_price']);
    
    echo json_encode([
        'success' => true,
        'tax_rate' => $ratePercentage,
        'tax_amount' => 0,
        'formula_template' => $regime['formula_template'],
        'base_price' => $basePrice,
        'tax_base' => $regime['tax_base'],
        'regime_name' => $regime['regime_name'],
        'application_level' => $regime['application_level'],
        'message' => 'Tax calculated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
