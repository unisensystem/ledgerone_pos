<?php
// Issues a short-lived (60s), single-use nonce for print-bridge.php, so the
// long-lived mobile bearer token never has to be placed in a URL (server
// logs, browser history, Referer headers to any subresource the print page
// loads). The token is presented the normal way here — Authorization
// header, on an authenticated API call — and only the one-time nonce goes
// into the subsequent browser navigation.
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = resolveMobileAuth($pdo);
if (!$auth || $auth['via'] !== 'token') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['orderId'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'orderId is required']);
    exit;
}

try {
    $nonce = bin2hex(random_bytes(32));
    $nonceHash = hash('sha256', $nonce);

    $stmt = $pdo->prepare("
        INSERT INTO mobile_print_nonces (nonce_hash, tenant_id, user_id, order_id, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND))
    ");
    $stmt->execute([$nonceHash, $auth['tenant_id'], $auth['user_id'], $orderId]);

    echo json_encode(['success' => true, 'nonce' => $nonce]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
