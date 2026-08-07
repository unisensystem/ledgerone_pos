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
    // Get company IDs this user has access to (within current tenant)
    $companyStmt = $pdo->prepare("
        SELECT company_id
        FROM user_companies
        WHERE user_id = ? AND tenant_id = ?
    ");
    $companyStmt->execute([$user_id, $tenant_id]);
    $companyIds = $companyStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($companyIds)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'debug' => ['user_id' => $user_id, 'tenant_id' => $tenant_id, 'companyIds' => $companyIds]
        ]);
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id, employee_id, full_name 
        FROM employees 
        WHERE tenant_id = ? AND is_active = 1 AND current_status = 'active' AND company_id IN ($placeholders)
        ORDER BY full_name
    ");
    $stmt->execute(array_merge([$tenant_id], $companyIds));
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'debug' => ['user_id' => $user_id, 'tenant_id' => $tenant_id, 'companyIds' => $companyIds]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
?>
