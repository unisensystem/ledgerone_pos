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
    $stmt = $pdo->prepare("
        WITH RECURSIVE sub_account_hierarchy AS (
            SELECT id, parent_id, account_head_id
            FROM sub_accounts
            WHERE id IN (94, 95, 97)
            
            UNION ALL
            
            SELECT sa.id, sa.parent_id, sa.account_head_id
            FROM sub_accounts sa
            INNER JOIN sub_account_hierarchy sah ON sa.parent_id = sah.id
        )
        SELECT DISTINCT a.id, a.name 
        FROM accounts a
        INNER JOIN sub_account_hierarchy sah ON a.sub_account_id = sah.id
        WHERE (a.tenant_id = ? OR a.tenant_id = 0)
    ");
    $stmt->execute([$tenant_id]);
    $expenseAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $expenseAccounts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch expense accounts']);
}