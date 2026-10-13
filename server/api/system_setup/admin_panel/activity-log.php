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
    $module = $_GET['module'] ?? '';
    $action = $_GET['action'] ?? '';
    $search = $_GET['search'] ?? '';
    $from_date = $_GET['from_date'] ?? '';
    $to_date = $_GET['to_date'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $where = ['tenant_id = ?'];
    $params = [$tenant_id];

    if ($module) {
        $where[] = 'module = ?';
        $params[] = $module;
    }
    if ($action) {
        $where[] = 'action = ?';
        $params[] = $action;
    }
    if ($search) {
        $where[] = '(user_name LIKE ? OR record_reference LIKE ? OR description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($from_date && $to_date) {
        $where[] = 'DATE(created_at) BETWEEN ? AND ?';
        $params[] = $from_date;
        $params[] = $to_date;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id, user_name, action, module, record_id, record_reference, description, created_at
        FROM activity_logs
        WHERE $whereSql
        ORDER BY created_at DESC, id DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Distinct module list for the filter dropdown
    $moduleStmt = $pdo->prepare("SELECT DISTINCT module FROM activity_logs WHERE tenant_id = ? ORDER BY module");
    $moduleStmt->execute([$tenant_id]);
    $modules = $moduleStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'modules' => $modules,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => max(1, ceil($total / $limit)),
            'total_records' => $total,
            'limit' => $limit
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
