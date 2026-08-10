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
    // Fetch users with their roles
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.is_active, u.last_login_at, u.employee_id, r.name as role_name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id AND ur.is_active = 1 AND (ur.tenant_id = ? OR ur.tenant_id = 0)
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.tenant_id = ?
    ");
    $stmt->execute([$tenant_id, $tenant_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all roles for this tenant
    $stmt = $pdo->prepare("SELECT id, name FROM roles WHERE (tenant_id = ? OR tenant_id = 0) AND is_active = 1");
    $stmt->execute([$tenant_id]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all employees for this tenant
    $stmt = $pdo->prepare("SELECT id, employee_id, full_name FROM employees WHERE tenant_id = ? AND is_active = 1 ORDER BY full_name");
    $stmt->execute([$tenant_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [
        'total' => count($users),
        'active' => count(array_filter($users, fn($u) => $u['is_active'] == 1)),
        'blocked' => count(array_filter($users, fn($u) => $u['is_active'] == 0))
    ];
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'roles' => $roles,
        'employees' => $employees,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Admin Panel Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error', 'debug' => $e->getMessage()]);
}