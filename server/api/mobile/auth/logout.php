<?php
// Revokes the bearer token this request authenticates with. Does not touch
// $_SESSION / the web logout path.
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

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches);
$tokenHash = hash('sha256', trim($matches[1] ?? ''));

$stmt = $pdo->prepare("UPDATE mobile_auth_tokens SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL");
$stmt->execute([$tokenHash]);

echo json_encode(['success' => true, 'message' => 'Logged out']);
