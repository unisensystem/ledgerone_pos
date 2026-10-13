<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$supplier_code = $_GET['supplier_code'] ?? '';

if (!$supplier_code) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    // First get supplier ID from supplier_code
    $stmt = $pdo->prepare("
        SELECT id FROM suppliers
        WHERE tenant_id = ? AND supplier_code = ?
    ");
    $stmt->execute([$tenant_id, $supplier_code]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }
    
    // Then get bills for that supplier ID
    $stmt = $pdo->prepare("
        SELECT pi.bill_no, pi.net_amount, pi.purchase_date, c.symbol as currency_symbol
        FROM purchase_invoice pi
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        WHERE pi.tenant_id = ? AND pi.supplier_id = ?
        ORDER BY pi.purchase_date DESC
    ");
    $stmt->execute([$tenant_id, $supplier['id']]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $bills]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>