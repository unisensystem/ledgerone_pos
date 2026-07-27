<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['user_id']   ?? null;

if (!$tenant_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Check if user has specific company assignments
    $stmt = $pdo->prepare("SELECT company_id FROM user_companies WHERE tenant_id = ? AND user_id = ?");
    $stmt->execute([$tenant_id, $user_id]);
    $assigned = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($assigned)) {
        $placeholders = implode(',', array_fill(0, count($assigned), '?'));
        $stmt = $pdo->prepare("SELECT id, company_code, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 AND id IN ($placeholders) ORDER BY company_name");
        $stmt->execute(array_merge([$tenant_id], $assigned));
    } else {
        // No specific assignment — show all tenant companies
        $stmt = $pdo->prepare("SELECT id, company_code, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 ORDER BY company_name");
        $stmt->execute([$tenant_id]);
    }

    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'companies' => $companies]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
