<?php
ob_start();

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'permissions' => [], 'error' => 'server_error']);
    }
    ob_end_flush();
});

require_once '../../../includes/connection.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo json_encode(['success' => false, 'permissions' => []]);
    exit;
}

$category = $_GET['category'] ?? '';
$form_name = $_GET['form_name'] ?? '';

if (!$category || !$form_name) {
    echo json_encode(['success' => false, 'permissions' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT rp.sub_permission, rp.allowed
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id AND ur.tenant_id = rp.tenant_id
        WHERE ur.user_id = ?
        AND ur.tenant_id = ?
        AND ur.is_active = 1
        AND rp.category = ?
        AND rp.form_name = ?
        AND rp.allowed = 1
    ");
    $stmt->execute([$user_id, $tenant_id, $category, $form_name]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($permissions)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'redirect' => true]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'permissions' => $permissions
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'permissions' => []]);
}
