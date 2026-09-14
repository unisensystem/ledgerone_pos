<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
$invoice_id = $_GET['invoice_id'] ?? null;

if (!$tenant_id || !$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
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
    $taxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $taxes]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
