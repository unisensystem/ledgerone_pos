<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;

if (!$tenant_id || !$customer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Get customer info
    $custStmt = $pdo->prepare("
        SELECT 
            c.customer_type_id,
            c.is_sales_tax_registered,
            c.is_filer
        FROM customers c
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $custStmt->execute([$customer_id, $tenant_id]);
    $customer = $custStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    $customer_type_id = $customer['customer_type_id'];
    $is_registered = $customer['is_sales_tax_registered'] ?? 0;
    $is_filer = $customer['is_filer'] ?? 0;
    
    // Determine party_type based on registration status
    $party_type = $is_registered ? 'registered_company' : 'unregistered';
    
    // Get company country_id if company_id is provided
    $company_country_id = null;
    if ($company_id) {
        $companyStmt = $pdo->prepare("
            SELECT country_id 
            FROM companies 
            WHERE id = ? AND tenant_id = ?
        ");
        $companyStmt->execute([$company_id, $tenant_id]);
        $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
        $company_country_id = $company['country_id'] ?? null;
    }
    
    // Get invoice-level tax regimes with proper filtering
    // Use subquery to get the best matching tax rate for each regime
    $baseQuery = "
        SELECT
            tr.id,
            tr.regime_code,
            tr.regime_name,
            tr.tax_base,
            tr.formula_template,
            tr.is_tax_inclusive,
            COALESCE(
                (SELECT tr_rate.rate_percentage 
                 FROM tax_rates tr_rate 
                 WHERE tr_rate.tax_regime_id = tr.id
                   AND tr_rate.transaction_type = 'sale'
                   AND tr_rate.is_active = 1
                   AND (tr_rate.effective_from IS NULL OR tr_rate.effective_from <= NOW())
                   AND (tr_rate.effective_to IS NULL OR tr_rate.effective_to >= NOW())
                   AND (
                       (tr_rate.customer_type_id = ? AND tr_rate.party_type = ? AND tr_rate.is_filer = ?)
                       OR (tr_rate.customer_type_id = ? AND tr_rate.party_type = 'all')
                       OR (tr_rate.party_type = 'all' AND tr_rate.customer_type_id IS NULL)
                   )
                 LIMIT 1),
                0
            ) as rate_percentage
        FROM tax_regimes tr
        WHERE tr.application_level = 'invoice'
            AND tr.is_active = 1
            AND (tr.effective_from IS NULL OR tr.effective_from <= NOW())
            AND (tr.effective_to IS NULL OR tr.effective_to >= NOW())
    ";
    
    // Add country filter if company_id is provided
    if ($company_country_id) {
        $baseQuery .= " AND tr.country_id = ?";
    }
    
    $baseQuery .= " ORDER BY tr.regime_name";
    
    $stmt = $pdo->prepare($baseQuery);
    
    $params = [$customer_type_id, $party_type, $is_filer, $customer_type_id];
    if ($company_country_id) {
        $params[] = $company_country_id;
    }
    
    $stmt->execute($params);
    $taxRegimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $taxRegimes]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
