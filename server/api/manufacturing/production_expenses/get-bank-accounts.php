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
    $stmt = $pdo->prepare("
        SELECT 
            id,
            bank_name,
            account_number,
            account_type,
            branch_name,
            account_title
        FROM bank_accounts
        WHERE tenant_id = ? 
        AND is_active = 1
        ORDER BY bank_name, account_number
    ");
    
    $stmt->execute([$tenant_id]);
    $accounts = [];
    
    while ($row = $stmt->fetch()) {
        $accounts[] = [
            'id' => $row['id'],
            'bank_name' => $row['bank_name'],
            'account_number' => $row['account_number'],
            'account_type' => $row['account_type'],
            'branch_name' => $row['branch_name'],
            'account_title' => $row['account_title'],
            'label' => $row['bank_name'] . ' — ' . $row['account_number'] . ' (' . ucfirst($row['account_type']) . ')'
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $accounts]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
