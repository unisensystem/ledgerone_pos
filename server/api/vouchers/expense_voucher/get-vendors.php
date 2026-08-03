<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit(); }

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

    $stmt = $pdo->prepare("
        SELECT id, supplier_name 
        FROM suppliers 
        WHERE tenant_id = ? AND company_id IN ($placeholders)
        ORDER BY supplier_name
    ");
    $stmt->execute(array_merge([$tenant_id], $companyIds));
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch vendors']);
}