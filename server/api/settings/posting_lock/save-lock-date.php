<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$module_key = $input['module_key'] ?? '';
$lock_date = $input['lock_date'] ?? null;

if (!$module_key) {
    echo json_encode(['success' => false, 'error' => 'Missing module']);
    exit();
}

// lock_date null/empty aaye to lock hata do (row delete)
if (empty($lock_date)) {
    $stmt = $pdo->prepare("DELETE FROM posting_lock_dates WHERE tenant_id = ? AND module_key = ?");
    $stmt->execute([$tenant_id, $module_key]);
    echo json_encode(['success' => true, 'message' => 'Lock removed']);
    exit();
}

// warna normal save/update
$stmt = $pdo->prepare("
    INSERT INTO posting_lock_dates (tenant_id, module_key, lock_date, updated_by)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE lock_date = VALUES(lock_date), updated_by = VALUES(updated_by), updated_at = NOW()
");
$stmt->execute([$tenant_id, $module_key, $lock_date, $user_id]);
echo json_encode(['success' => true, 'message' => 'Lock saved']);