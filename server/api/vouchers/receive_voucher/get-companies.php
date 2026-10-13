<?php
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
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, company_name
        FROM companies
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY company_name
    ");
    $stmt->execute([$tenant_id]);
    $companies = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $companies
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
