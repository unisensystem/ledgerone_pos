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
            p.project_name,
            GROUP_CONCAT(a.name ORDER BY evl.id SEPARATOR ',') AS expense_accounts,
            GROUP_CONCAT(cc.name ORDER BY evl.id SEPARATOR ',') AS cost_centers,
            GROUP_CONCAT(evl.amount ORDER BY evl.id SEPARATOR ',') AS expense_amounts,
            GROUP_CONCAT(evl.account_id ORDER BY evl.id SEPARATOR ',') AS expense_account_ids,
            GROUP_CONCAT(IFNULL(evl.cost_center_id, '') ORDER BY evl.id SEPARATOR ',') AS cost_center_ids
        FROM expense_voucher ev
        LEFT JOIN projects p ON ev.project_id = p.id
        LEFT JOIN expense_voucher_line evl ON evl.voucher_id = ev.id
        LEFT JOIN accounts a ON evl.account_id = a.id
        LEFT JOIN cost_centers cc ON evl.cost_center_id = cc.id
        WHERE ev.tenant_id = ?
        " . ($project_id ? "AND ev.project_id = ?" : "") . "
        GROUP BY ev.id
        ORDER BY ev.id DESC
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
