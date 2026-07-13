<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || $tenant_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher ID is required']);
    exit();
}

try {
    // Get voucher header
    $stmt = $pdo->prepare("
        SELECT 
            ev.id, 
            ev.voucher_no, 
            ev.date, 
            ev.description, 
            ev.total_amount, 
            ev.total_items, 
            ev.company_id,
            ev.project_id,
            ev.container_id,
            ev.vendor_id,
            ev.type,
            ev.is_cash_flow,
            c.company_name,
            s.supplier_name as vendor_name
        FROM expense_voucher ev
        LEFT JOIN companies c ON ev.company_id = c.id
        LEFT JOIN suppliers s ON ev.vendor_id = s.id
        WHERE ev.id = ? AND ev.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        http_response_code(404);
        echo json_encode(['error' => 'Voucher not found']);
        exit();
    }
    
    // Get voucher lines
    $stmt = $pdo->prepare("
        SELECT 
            evl.id,
            evl.account_id,
            a.name as account_name,
            evl.cost_center_id,
            cc.name as cost_center_name,
            evl.amount,
            evl.payment_method_id,
            pm.name as payment_method_name,
            evl.bank_account_id,
            ba.bank_name,
            ba.account_number,
            evl.cheque_no,
            evl.cheque_date
        FROM expense_voucher_line evl
        INNER JOIN accounts a ON evl.account_id = a.id
        LEFT JOIN cost_centers cc ON evl.cost_center_id = cc.id
        INNER JOIN accounts pm ON evl.payment_method_id = pm.id
        LEFT JOIN bank_accounts ba ON evl.bank_account_id = ba.id
        WHERE evl.voucher_id = ? AND evl.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $voucher['lines'] = $lines;
    
    echo json_encode(['success' => true, 'data' => $voucher]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch voucher details']);
}
