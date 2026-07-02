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

$expense_account_id = $_GET['expense_account_id'] ?? null;
$cost_center_id = $_GET['cost_center_id'] ?? null;

try {
    $sql = "";
    $params = [$tenant_id];
    
    if ($expense_account_id || $cost_center_id) {
        $sql = "SELECT 
            ev.id,
            ev.voucher_no,
            ev.date,
            ev.description,
            ev.company_id,
            ev.project_id,
            p.project_name,
            SUM(evl.amount) as total_amount,
            COUNT(evl.id) as total_items,
            GROUP_CONCAT(a.name ORDER BY evl.id SEPARATOR ',') as expense_accounts,
            GROUP_CONCAT(cc.name ORDER BY evl.id SEPARATOR ',') as cost_centers,
            GROUP_CONCAT(evl.amount ORDER BY evl.id SEPARATOR ',') as expense_amounts
        FROM expense_voucher ev
        LEFT JOIN projects p ON ev.project_id = p.id
        INNER JOIN expense_voucher_line evl ON ev.id = evl.voucher_id
        INNER JOIN accounts a ON evl.account_id = a.id
        LEFT JOIN cost_centers cc ON evl.cost_center_id = cc.id
        WHERE ev.tenant_id = ?";
        
        if ($expense_account_id) {
            $sql .= " AND evl.account_id = ?";
            $params[] = $expense_account_id;
        }
        
        if ($cost_center_id) {
            $sql .= " AND evl.cost_center_id = ?";
            $params[] = $cost_center_id;
        }
        
        $sql .= " GROUP BY ev.id ORDER BY ev.id DESC";
    } else {
        $sql = "SELECT 
            ev.id,
            ev.voucher_no,
            ev.date,
            ev.description,
            ev.total_amount,
            ev.total_items,
            ev.company_id,
            ev.project_id,
            p.project_name,
            GROUP_CONCAT(a.name ORDER BY evl.id SEPARATOR ',') as expense_accounts,
            GROUP_CONCAT(cc.name ORDER BY evl.id SEPARATOR ',') as cost_centers,
            GROUP_CONCAT(evl.amount ORDER BY evl.id SEPARATOR ',') as expense_amounts
        FROM expense_voucher ev
        LEFT JOIN projects p ON ev.project_id = p.id
        LEFT JOIN expense_voucher_line evl ON evl.voucher_id = ev.id
        LEFT JOIN accounts a ON evl.account_id = a.id
        LEFT JOIN cost_centers cc ON evl.cost_center_id = cc.id
        WHERE ev.tenant_id = ?
        GROUP BY ev.id
        ORDER BY ev.id DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $vouchers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch expense vouchers']);
}
