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

$reasonLabels = [
    'invalid_password' => 'Invalid Password',
    'user_not_found'   => 'User Not Found',
    'account_locked'   => 'Account Locked',
    '2fa_failed'       => '2FA Failed',
    'ip_blocked'       => 'IP Blocked',
];

try {
    $stmt = $pdo->prepare("
        SELECT attempted_at, ip_address, email, username, failure_reason, attempt_status
        FROM login_attempts
        WHERE tenant_id = ? AND attempt_status IN ('failed', 'locked')
        ORDER BY attempted_at DESC
        LIMIT 200
    ");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) use ($reasonLabels) {
        $reason = $reasonLabels[$row['failure_reason']] ?? ($row['attempt_status'] === 'locked' ? 'Account Locked' : 'Unknown');
        return [
            'email'        => $row['email'] ?: $row['username'],
            'attempt_time' => $row['attempted_at'],
            'ip_address'   => $row['ip_address'],
            'reason'       => $reason,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}