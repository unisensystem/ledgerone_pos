<?php
ob_start();
session_start();
require_once '../../includes/connection.php';
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT company_name, legal_name, logo_url, timezone FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$_SESSION['tenant_id']]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $userStmt = $pdo->prepare("SELECT full_name, profile_picture FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    $roleStmt = $pdo->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? AND ur.is_active = 1 LIMIT 1");
    $roleStmt->execute([$_SESSION['user_id']]);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    
    $result = $company ?: ['company_name' => 'FuelingSys ERP', 'legal_name' => 'Fuel Management System'];
    $result['user_name'] = $user['full_name'] ?? 'User';
    $result['profile_picture'] = $user['profile_picture'] ?? null;
    $result['user_role'] = $role['name'] ?? 'User';
    $result['subscription_status'] = $_SESSION['subscription_status'] ?? 'active';
    $result['tenant_id'] = $_SESSION['tenant_id'] ?? null;
    
    error_log('Subscription status from session: ' . ($result['subscription_status']));
    
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>