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
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(200, intval($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT 1 FROM login_attempts WHERE tenant_id = ? AND attempt_status = 'success'
            GROUP BY user_id, ip_address
        ) grouped
    ");
    $countStmt->execute([$tenant_id]);
    $total = (int)$countStmt->fetchColumn();

    // Group successful logins per user/IP, and enrich with geolocation from ip_security
    $stmt = $pdo->prepare("
        SELECT
            la.user_id,
            u.full_name,
            la.email,
            la.ip_address,
            COUNT(*) AS login_count,
            MAX(la.attempted_at) AS last_login,
            ips.city,
            ips.region,
            ips.country_name,
            ips.country_code
        FROM login_attempts la
        LEFT JOIN users u ON u.id = la.user_id AND u.tenant_id = la.tenant_id
        LEFT JOIN ip_security ips ON ips.ip_address = la.ip_address AND ips.tenant_id = la.tenant_id
        WHERE la.tenant_id = ? AND la.attempt_status = 'success'
        GROUP BY la.user_id, la.ip_address
        ORDER BY last_login DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute([$tenant_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        $locationParts = array_filter([$row['city'], $row['region'], $row['country_name'] ?: $row['country_code']]);
        $location = $locationParts ? implode(', ', $locationParts) : 'Unknown';

        return [
            'user'        => $row['full_name'] ?? $row['email'],
            'location'    => $location,
            'ip_address'  => $row['ip_address'],
            'last_login'  => $row['last_login'],
            'login_count' => (int) $row['login_count'],
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => max(1, ceil($total / $limit)),
            'total_records' => $total,
            'limit' => $limit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
