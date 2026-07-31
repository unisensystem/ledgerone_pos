<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$tenant_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $ucStmt = $pdo->prepare("SELECT company_id FROM user_companies WHERE user_id = ?");
    $ucStmt->execute([$user_id]);
    $userCompanyIds = $ucStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($userCompanyIds)) {
        $placeholders = implode(',', array_fill(0, count($userCompanyIds), '?'));
        $stmt = $pdo->prepare("SELECT id, company_code, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 AND id IN ($placeholders) ORDER BY company_name");
        $stmt->execute(array_merge([$tenant_id], $userCompanyIds));
    } else {
        $stmt = $pdo->prepare("SELECT id, company_code, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 ORDER BY company_name");
        $stmt->execute([$tenant_id]);
    }
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'companies' => $companies]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
