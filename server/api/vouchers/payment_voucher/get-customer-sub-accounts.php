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

$customer_code = $_GET['customer_code'] ?? '';

if (!$customer_code) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

try {
    // Get company IDs this user has access to (within current tenant)
    $companyStmt = $pdo->prepare("
        SELECT company_id
        FROM user_companies
        WHERE user_id = ? AND tenant_id = ?
    ");
    $companyStmt->execute([$user_id, $tenant_id]);
    $companyIds = $companyStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($companyIds)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    // Get customer ID from customer_code, restricted to user's assigned companies
    $stmt = $pdo->prepare("
        SELECT id FROM customers 
        WHERE tenant_id = ? AND customer_code = ? AND company_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$tenant_id, $customer_code], $companyIds));
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }
    
    // Get sub-accounts for that customer
    $stmt = $pdo->prepare("
        SELECT id, sub_account_name 
        FROM customer_sub_accounts 
        WHERE tenant_id = ? AND customer_id = ?
        ORDER BY sub_account_name
    ");
    $stmt->execute([$tenant_id, $customer['id']]);
    $subAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $subAccounts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>