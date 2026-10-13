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
$tenant_id = $auth['tenant_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            tc.currency_id,
            c.code,
            c.name,
            c.symbol,
            tc.is_base_currency
        FROM tenant_currencies tc
        JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
        WHERE tc.tenant_id = ? AND tc.is_active = 1 AND c.is_active = 1
        ORDER BY tc.is_base_currency DESC, c.name
    ");
    $stmt->execute([$tenant_id]);
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'currencies' => $currencies]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}