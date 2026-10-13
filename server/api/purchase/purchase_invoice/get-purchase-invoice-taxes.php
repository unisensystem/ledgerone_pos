<?php
require_once '../../../../includes/connection.php';
require_once 'purchase-invoice-tax-helper.php';

header('Content-Type: application/json');
session_start();

$tenant_id  = $_SESSION['tenant_id'] ?? null;
$invoice_id = $_GET['invoice_id'] ?? null;

if (!$tenant_id || !$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $taxes = getPurchaseInvoiceTaxes($pdo, $invoice_id, $tenant_id);
    echo json_encode(['success' => true, 'data' => $taxes]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
