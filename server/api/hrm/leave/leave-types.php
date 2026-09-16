<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $activeOnly = ($_GET['active_only'] ?? '1') === '1';
        $sql = "SELECT * FROM leave_types WHERE tenant_id = ?" . ($activeOnly ? " AND is_active = 1" : "") . " ORDER BY display_order, name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'leave_types' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $code = strtoupper(trim($data['code'] ?? ''));
    $isPaid = !empty($data['is_paid']) ? 1 : 0;
    $isActive = array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1;
    $requiresApproval = !empty($data['requires_approval']) ? 1 : 0;
    $allowCarryForward = !empty($data['allow_carry_forward']) ? 1 : 0;
    $maxCarryForward = isset($data['max_carry_forward_days']) && $data['max_carry_forward_days'] !== '' ? (float) $data['max_carry_forward_days'] : null;
    $maxDaysPerYear = isset($data['max_days_per_year']) && $data['max_days_per_year'] !== '' ? (float) $data['max_days_per_year'] : null;
    $trackBalance = !empty($data['track_balance']) ? 1 : 0;
    $notes = $data['notes'] ?? null;

    if (!$name || !$code) throw new Exception('Name and code are required');

    if ($id) {
        // Editing an existing type must not silently corrupt the CODE-based
        // migration/lookup logic used elsewhere (e.g. leave-return.php's
        // hardcoded 'UNPAID' code lookup for the overstay-auto-unpaid flow).
        $stmt = $pdo->prepare("SELECT code FROM leave_types WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $existingCode = $stmt->fetchColumn();
        if ($existingCode === false) throw new Exception('Leave type not found');
        if ($existingCode === 'UNPAID' && $code !== 'UNPAID') {
            throw new Exception("The 'UNPAID' code is relied on by the automatic overstay handling and cannot be renamed");
        }

        $stmt = $pdo->prepare("
            UPDATE leave_types SET name=?, code=?, is_paid=?, is_active=?, requires_approval=?, allow_carry_forward=?,
                max_carry_forward_days=?, max_days_per_year=?, track_balance=?, notes=?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$name, $code, $isPaid, $isActive, $requiresApproval, $allowCarryForward, $maxCarryForward, $maxDaysPerYear, $trackBalance, $notes, $id, $tenant_id]);

        logActivity($pdo, $tenant_id, $user_id, 'update', 'Leave Type', $id, $code, "Updated leave type \"$name\" ($code)", null, $data);
        echo json_encode(['success' => true, 'message' => 'Leave type updated', 'id' => $id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO leave_types (tenant_id, name, code, is_paid, is_active, requires_approval, allow_carry_forward, max_carry_forward_days, max_days_per_year, track_balance, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $name, $code, $isPaid, $isActive, $requiresApproval, $allowCarryForward, $maxCarryForward, $maxDaysPerYear, $trackBalance, $notes, $user_id]);
        $newId = (int) $pdo->lastInsertId();

        logActivity($pdo, $tenant_id, $user_id, 'create', 'Leave Type', $newId, $code, "Created leave type \"$name\" ($code)", null, $data);
        echo json_encode(['success' => true, 'message' => 'Leave type created', 'id' => $newId]);
    }

} catch (PDOException $e) {
    http_response_code(400);
    $msg = str_contains($e->getMessage(), 'Duplicate entry') ? 'A leave type with this code already exists' : $e->getMessage();
    echo json_encode(['success' => false, 'message' => $msg]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
