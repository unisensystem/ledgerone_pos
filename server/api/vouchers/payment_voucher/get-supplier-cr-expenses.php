<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }

$supplier_code = $_GET['supplier_code'] ?? null;
if (!$supplier_code) { echo json_encode(['success' => false, 'error' => 'Supplier code required']); exit(); }

try {
    // Get supplier id from code
    $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE tenant_id = ? AND supplier_code = ?");
    $stmt->execute([$tenant_id, $supplier_code]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) { echo json_encode(['success' => false, 'error' => 'Supplier not found']); exit(); }

    // Get CR expense vouchers for this supplier
    $stmt = $pdo->prepare("
        SELECT ev.id, ev.voucher_no, ev.date, ev.total_amount, ev.description
        FROM expense_voucher ev
        WHERE ev.tenant_id = ? AND ev.vendor_id = ? AND ev.type = 'CR'
        ORDER BY ev.date DESC, ev.id DESC
    ");
    $stmt->execute([$tenant_id, $supplier['id']]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $vouchers]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch expenses']);
}
