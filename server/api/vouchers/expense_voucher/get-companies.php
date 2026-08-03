<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
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
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id, company_name
        FROM companies
        WHERE tenant_id = ? AND is_active = 1 AND id IN ($placeholders)
        ORDER BY company_name
    ");
    $stmt->execute(array_merge([$tenant_id], $companyIds));
    $companies = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $companies]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}