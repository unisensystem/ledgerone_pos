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

// Turns a raw User-Agent string into a short "Browser on OS" label
function parseDevice($userAgent) {
    if (!$userAgent) return 'Unknown';

    $os = 'Unknown OS';
    if (stripos($userAgent, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($userAgent, 'Mac OS') !== false) $os = 'macOS';
    elseif (stripos($userAgent, 'Android') !== false) $os = 'Android';
    elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) $os = 'iOS';
    elseif (stripos($userAgent, 'Linux') !== false) $os = 'Linux';

    $browser = 'Unknown Browser';
    if (stripos($userAgent, 'Edg/') !== false) $browser = 'Edge';
    elseif (stripos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
    elseif (stripos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
    elseif (stripos($userAgent, 'Safari') !== false) $browser = 'Safari';

    return $browser . ' on ' . $os;
}

try {
    $stmt = $pdo->prepare("
        SELECT la.attempted_at, la.ip_address, la.user_agent, la.attempt_status,
               la.email, la.username, u.full_name
        FROM login_attempts la
        LEFT JOIN users u ON u.id = la.user_id AND u.tenant_id = la.tenant_id
        WHERE la.tenant_id = ?
        ORDER BY la.attempted_at DESC
        LIMIT 200
    ");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'user'       => $row['full_name'] ?? ($row['email'] ?: $row['username']),
            'login_time' => $row['attempted_at'],
            'ip_address' => $row['ip_address'],
            'device'     => parseDevice($row['user_agent']),
            'status'     => $row['attempt_status'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}