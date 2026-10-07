<?php
/*
|--------------------------------------------------------------------------
| Biometric Push Log — read-only API
|--------------------------------------------------------------------------
| GET  ?date=YYYY-MM-DD&status=processed|unmapped|error
|      Returns the 200 most recent matching log entries for this tenant.
*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {

    $date   = $_GET['date']   ?? date('Y-m-d');
    $status = $_GET['status'] ?? '';

    $allowed = ['processed', 'unmapped', 'error', ''];
    if (!in_array($status, $allowed, true)) $status = '';

    $where  = ['l.tenant_id = ?'];
    $params = [$tenant_id];

    if ($date) {
        $where[]  = 'DATE(l.punch_time) = ?';
        $params[] = $date;
    }

    if ($status !== '') {
        $where[]  = 'l.status = ?';
        $params[] = $status;
    }

    $stmt = $pdo->prepare("
        SELECT l.id, l.machine_employee_id, l.punch_time, l.punch_type_raw,
               l.status, l.note, l.created_at,
               d.device_name,
               m.employee_id
        FROM biometric_push_log l
        LEFT JOIN biometric_devices d
            ON d.id = l.device_id
        LEFT JOIN biometric_employee_mapping m
            ON m.tenant_id = l.tenant_id AND m.machine_employee_id = l.machine_employee_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

} catch (Throwable $e) {
    error_log('Biometric Logs API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
