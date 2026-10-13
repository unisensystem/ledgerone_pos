<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/role-helper.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only a Super Admin can delete log entries']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$logType = $input['log_type'] ?? '';
$module = trim($input['module'] ?? '');
$action = trim($input['action'] ?? '');
$toDate = trim($input['to_date'] ?? '');
$confirmAll = !empty($input['confirm_all']);
$backedUp = !empty($input['backed_up']);

if (!in_array($logType, ['audit', 'login'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'log_type must be audit or login']);
    exit;
}

if (!$backedUp) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A backup must be downloaded before deleting']);
    exit;
}

// Deleting with no module filter (audit) / no status filter (login) is the broadest,
// most destructive scope - require the extra "type DELETE ALL" confirmation for it.
$isAllScope = $logType === 'audit' ? ($module === '') : ($action === '');
if ($isAllScope && !$confirmAll) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Deleting all records requires the extra "DELETE ALL" confirmation']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($logType === 'audit') {
        $where = ['tenant_id = ?'];
        $params = [$tenant_id];
        if ($module !== '') { $where[] = 'module = ?'; $params[] = $module; }
        if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
        if ($toDate !== '') { $where[] = 'DATE(created_at) <= ?'; $params[] = $toDate; }

        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE $whereSql");
        $countStmt->execute($params);
        $deletedCount = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE $whereSql");
        $stmt->execute($params);
    } else {
        $where = ['tenant_id = ?'];
        $params = [$tenant_id];
        if ($action !== '') { $where[] = 'attempt_status = ?'; $params[] = $action; }
        if ($toDate !== '') { $where[] = 'DATE(attempted_at) <= ?'; $params[] = $toDate; }

        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE $whereSql");
        $countStmt->execute($params);
        $deletedCount = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE $whereSql");
        $stmt->execute($params);
    }

    $pdo->commit();

    // Record the purge itself, after it completes, so there is still a paper trail of who
    // deleted what - even when log_type is 'audit' and the purge wiped audit_logs rows.
    $filterDesc = ($module !== '' ? "module=$module" : 'module=All')
        . ', ' . ($action !== '' ? "action=$action" : 'action=All')
        . ($toDate !== '' ? ", up to $toDate" : ', no date limit');
    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'System',
        0, null,
        "Bulk deleted $deletedCount " . ($logType === 'audit' ? 'Audit Trail' : 'Login Activity') . " entries ($filterDesc)",
        null, null
    );

    echo json_encode(['success' => true, 'deleted_count' => $deletedCount]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
