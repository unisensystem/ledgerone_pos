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

try {
    $project_id = $_GET['project_id'] ?? null;
    
    // ✅ CHANGE: Remove GROUP_CONCAT—har line ek separate row return karega
    $stmt = $pdo->prepare("
        SELECT 
            ev.id as voucher_id,
            ev.voucher_no,
            ev.date,
            ev.description,
            ev.total_amount,
            ev.total_items,
            ev.company_id,
            ev.project_id,
            ev.is_cash_flow,
            p.project_name,
            a.name as expense_accounts,
            cc.name as cost_centers,
            evl.amount as expense_amounts,
            evl.account_id as expense_account_ids,
            evl.cost_center_id as cost_center_ids
        FROM expense_voucher ev
        LEFT JOIN projects p ON ev.project_id = p.id
        LEFT JOIN expense_voucher_line evl ON evl.voucher_id = ev.id
        LEFT JOIN accounts a ON evl.account_id = a.id
        LEFT JOIN cost_centers cc ON evl.cost_center_id = cc.id
        WHERE ev.tenant_id = ?
        " . ($project_id ? "AND ev.project_id = ?" : "") . "
        ORDER BY ev.id DESC, evl.id ASC
    ");
    
    if ($project_id) {
        $stmt->execute([$tenant_id, $project_id]);
    } else {
        $stmt->execute([$tenant_id]);
    }
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $vouchers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch expense vouchers']);
}
?>