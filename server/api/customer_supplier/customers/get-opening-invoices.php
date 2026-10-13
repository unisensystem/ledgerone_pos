<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;

if (!$tenant_id || !$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM opening_balance_invoices WHERE tenant_id = ? AND customer_id = ? ORDER BY invoice_date");
    $stmt->execute([$tenant_id, $customer_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'invoices' => $invoices]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
