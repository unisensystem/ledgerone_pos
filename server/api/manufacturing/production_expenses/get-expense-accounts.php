<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Recursive CTE to get all sub_accounts under Manufacturing Expenses (113)
    $stmt = $pdo->prepare("
        WITH RECURSIVE sub_account_tree AS (
            -- Base case: Start with Manufacturing Expenses (113)
            SELECT id, name, parent_id, account_head_id
            FROM sub_accounts
            WHERE id = 113
            
            UNION ALL
            
            -- Recursive case: Get all children at any level
            SELECT sa.id, sa.name, sa.parent_id, sa.account_head_id
            FROM sub_accounts sa
            INNER JOIN sub_account_tree sat ON sa.parent_id = sat.id
        )
        SELECT 
            a.id,
            a.name as account_name,
            sa.id as sub_account_id,
            sa.name as sub_account_name,
            parent_sa.name as parent_sub_account_name,
            ah.name as account_head_name
        FROM accounts a
        INNER JOIN sub_account_tree sat ON a.sub_account_id = sat.id
        INNER JOIN sub_accounts sa ON a.sub_account_id = sa.id
        LEFT JOIN sub_accounts parent_sa ON sa.parent_id = parent_sa.id
        INNER JOIN accounts_head ah ON sa.account_head_id = ah.id
        WHERE (a.tenant_id = ? OR a.tenant_id = 0)
        ORDER BY COALESCE(parent_sa.name, sa.name), sa.name, a.name
    ");
    
    $stmt->execute([$tenant_id]);
    $accounts = [];
    
    while ($row = $stmt->fetch()) {
        // Build hierarchical label
        if ($row['parent_sub_account_name']) {
            $label = $row['parent_sub_account_name'] . ' → ' . $row['sub_account_name'] . ' → ' . $row['account_name'];
        } else {
            $label = $row['sub_account_name'] . ' → ' . $row['account_name'];
        }
        
        $accounts[] = [
            'id' => $row['id'],
            'account_name' => $row['account_name'],
            'sub_account_id' => $row['sub_account_id'],
            'sub_account_name' => $row['sub_account_name'],
            'parent_sub_account_name' => $row['parent_sub_account_name'],
            'account_head_name' => $row['account_head_name'],
            'label' => $label
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $accounts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
