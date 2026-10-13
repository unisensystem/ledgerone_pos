<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$assoc_company_id = $_GET['assoc_company_id'] ?? null;
if (!$assoc_company_id) {
    echo json_encode(['success' => false, 'message' => 'assoc_company_id is required']);
    exit;
}

try {
    // Get suppliers that have this company in their associated companies
    $stmt = $pdo->prepare("
        SELECT s.id, s.supplier_code, s.supplier_name, s.primary_phone, s.current_balance
        FROM suppliers s
        JOIN supplier_associated_companies sac ON sac.supplier_id = s.id
        WHERE s.tenant_id = ?
          AND sac.tenant_id = ?
          AND sac.company_id = ?
          AND s.status = 'ACTIVE'
        ORDER BY s.supplier_name
    ");
    $stmt->execute([$tenant_id, $tenant_id, $assoc_company_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
