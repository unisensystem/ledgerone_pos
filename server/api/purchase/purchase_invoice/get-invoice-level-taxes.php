<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;

if (!$tenant_id || !$supplier_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
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
    
    // Get supplier party_type
    $supplierStmt = $pdo->prepare("SELECT party_type FROM suppliers WHERE id = ? AND tenant_id = ?");
    $supplierStmt->execute([$supplier_id, $tenant_id]);
    $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
    $supplier_party_type = $supplier['party_type'] ?? null;

    // Get invoice-level tax regimes with party_type filtering
    $baseQuery = "
        SELECT 
            tr.id,
            tr.regime_name,
            COALESCE(
                (SELECT tr_rate.rate_percentage 
                 FROM tax_rates tr_rate 
                 WHERE tr_rate.tax_regime_id = tr.id
                   AND tr_rate.tenant_id = ?
                   AND tr_rate.transaction_type = 'purchase'
                   AND tr_rate.is_active = 1
                   AND (tr_rate.effective_from IS NULL OR tr_rate.effective_from <= NOW())
                   AND (tr_rate.effective_to IS NULL OR tr_rate.effective_to >= NOW())
                   AND (
                       tr_rate.party_type IS NULL
                       OR tr_rate.party_type = 'all'
                       OR tr_rate.party_type = ?
                   )
                 ORDER BY
                   CASE WHEN tr_rate.party_type = ? THEN 0
                        WHEN tr_rate.party_type = 'all' THEN 1
                        ELSE 2 END
                 LIMIT 1),
                0
            ) as rate_percentage
        FROM tax_regimes tr
        WHERE tr.application_level = 'invoice'
            AND tr.tenant_id = ?
            AND tr.is_active = 1
            AND (tr.effective_from IS NULL OR tr.effective_from <= NOW())
            AND (tr.effective_to IS NULL OR tr.effective_to >= NOW())
            AND EXISTS (
                SELECT 1 FROM tax_rates tr_check
                WHERE tr_check.tax_regime_id = tr.id
                  AND tr_check.tenant_id = ?
                  AND tr_check.transaction_type = 'purchase'
                  AND tr_check.is_active = 1
                  AND (
                      tr_check.party_type IS NULL
                      OR tr_check.party_type = 'all'
                      OR tr_check.party_type = ?
                  )
            )
    ";

    // Add country filter if company_id is provided
    if ($company_country_id) {
        $baseQuery .= " AND tr.country_id = ?";
    }

    $baseQuery .= " ORDER BY tr.regime_name";

    $stmt = $pdo->prepare($baseQuery);

    $params = [
        $tenant_id, $supplier_party_type, $supplier_party_type,  // subquery params
        $tenant_id,                                               // main WHERE tenant_id
        $tenant_id, $supplier_party_type                         // EXISTS check params
    ];
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
