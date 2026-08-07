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

try {
    // Restrict to companies this user is assigned to, if they have any assignments
    $companyStmt = $pdo->prepare("SELECT company_id FROM user_companies WHERE user_id = ? AND tenant_id = ?");
    $companyStmt->execute([$user_id, $tenant_id]);
    $companyIds = $companyStmt->fetchAll(PDO::FETCH_COLUMN);

    $where = 'tenant_id = ? AND is_active = 1';
    $params = [$tenant_id];
    if (!empty($companyIds)) {
        // Bank accounts with no company_id are shared/tenant-wide accounts, visible to everyone
        $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
        $where .= " AND (company_id IS NULL OR company_id IN ($placeholders))";
        $params = array_merge($params, $companyIds);
    }

    $stmt = $pdo->prepare("
        SELECT id, bank_name, account_number
        FROM bank_accounts
        WHERE $where
        ORDER BY bank_name
    ");
    $stmt->execute($params);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $accounts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>