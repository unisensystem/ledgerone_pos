<?php
// Resumes a mobile session from an already-issued bearer token — lets the
// app skip straight to Home on relaunch instead of forcing re-login every
// time, as long as the token is still valid (see includes/mobile_auth.php).
// Returns the same user/tenant/companies/permissions shape login.php does,
// just without issuing a new token.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth || $auth['via'] !== 'token') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, tenant_id, email, full_name, language_code, timezone, is_active
        FROM users WHERE id = ?
    ");
    $stmt->execute([$auth['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['is_active']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Account suspended']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT rp.category, rp.form_name, rp.sub_permission, rp.allowed
        FROM role_permissions rp
        JOIN user_roles ur ON ur.role_id = rp.role_id AND ur.tenant_id = rp.tenant_id
        WHERE ur.user_id = ? AND ur.is_active = 1 AND rp.tenant_id = ? AND rp.allowed = 1
    ");
    $stmt->execute([$user['id'], $user['tenant_id']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, company_name AS name, company_code AS code FROM companies WHERE tenant_id = ? AND is_active = 1");
    $stmt->execute([$user['tenant_id']]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'language_code' => $user['language_code'],
            'timezone' => $user['timezone'],
        ],
        'permissions' => $permissions,
        'companies' => $companies,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
