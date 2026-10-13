<?php
/*
|--------------------------------------------------------------------------
| Biometric Employee Mapping CRUD API
|--------------------------------------------------------------------------
| GET    -> list all mappings for the tenant
| POST   -> create a mapping (machine_employee_id -> employee_id)
| PUT    -> update a mapping
| DELETE -> remove a mapping
*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {

    // ── GET ──────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $stmt = $pdo->prepare("
            SELECT m.id, m.machine_employee_id, m.employee_id,
                   e.full_name, d.department_name, m.created_at
            FROM biometric_employee_mapping m
            LEFT JOIN employees e
                ON e.employee_id = m.employee_id AND e.tenant_id = ?
            LEFT JOIN departments d
                ON d.id = e.department_id
            WHERE m.tenant_id = ?
            ORDER BY CAST(m.machine_employee_id AS UNSIGNED), m.machine_employee_id
        ");
        $stmt->execute([$tenant_id, $tenant_id]);

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // ── POST ─────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $data          = json_decode(file_get_contents('php://input'), true) ?? [];
        $machineEmpId  = trim($data['machine_employee_id'] ?? '');
        $employeeId    = trim($data['employee_id']         ?? '');

        if ($machineEmpId === '' || $employeeId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'machine_employee_id and employee_id are required']);
            exit;
        }

        // Check machine ID not already mapped
        $chk = $pdo->prepare("
            SELECT id FROM biometric_employee_mapping
            WHERE tenant_id = ? AND machine_employee_id = ?
            LIMIT 1
        ");
        $chk->execute([$tenant_id, $machineEmpId]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Machine employee ID \"$machineEmpId\" is already mapped"]);
            exit;
        }

        // Verify employee exists
        $empChk = $pdo->prepare("SELECT full_name FROM employees WHERE employee_id = ? AND tenant_id = ? LIMIT 1");
        $empChk->execute([$employeeId, $tenant_id]);
        $emp = $empChk->fetch(PDO::FETCH_ASSOC);
        if (!$emp) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => "Employee \"$employeeId\" not found"]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO biometric_employee_mapping (tenant_id, machine_employee_id, employee_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $machineEmpId, $employeeId]);
        $newId = (int)$pdo->lastInsertId();

        logActivity($pdo, $tenant_id, $user_id, 'create', 'Biometric Mapping', $newId,
            "$machineEmpId → $employeeId",
            "Mapped machine employee ID \"$machineEmpId\" to \"{$emp['full_name']}\" ($employeeId)",
            null, ['machine_employee_id' => $machineEmpId, 'employee_id' => $employeeId]
        );

        echo json_encode(['success' => true, 'message' => 'Mapping created', 'data' => ['id' => $newId]]);
        exit;
    }

    // ── PUT ──────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

        $data         = json_decode(file_get_contents('php://input'), true) ?? [];
        $id           = (int)($data['id'] ?? 0);
        $machineEmpId = trim($data['machine_employee_id'] ?? '');
        $employeeId   = trim($data['employee_id']         ?? '');

        if ($id <= 0 || $machineEmpId === '' || $employeeId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id, machine_employee_id and employee_id are required']);
            exit;
        }

        // Ownership check
        $chk = $pdo->prepare("SELECT id FROM biometric_employee_mapping WHERE id = ? AND tenant_id = ? LIMIT 1");
        $chk->execute([$id, $tenant_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Mapping not found']);
            exit;
        }

        // Uniqueness check (exclude current row)
        $uniq = $pdo->prepare("
            SELECT id FROM biometric_employee_mapping
            WHERE tenant_id = ? AND machine_employee_id = ? AND id != ?
            LIMIT 1
        ");
        $uniq->execute([$tenant_id, $machineEmpId, $id]);
        if ($uniq->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Machine employee ID \"$machineEmpId\" is already mapped to another employee"]);
            exit;
        }

        $pdo->prepare("
            UPDATE biometric_employee_mapping
            SET machine_employee_id = ?, employee_id = ?
            WHERE id = ? AND tenant_id = ?
        ")->execute([$machineEmpId, $employeeId, $id, $tenant_id]);

        logActivity($pdo, $tenant_id, $user_id, 'update', 'Biometric Mapping', $id,
            "$machineEmpId → $employeeId", "Updated biometric mapping", null,
            ['machine_employee_id' => $machineEmpId, 'employee_id' => $employeeId]
        );

        echo json_encode(['success' => true, 'message' => 'Mapping updated']);
        exit;
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $chk = $pdo->prepare("SELECT machine_employee_id, employee_id FROM biometric_employee_mapping WHERE id = ? AND tenant_id = ? LIMIT 1");
        $chk->execute([$id, $tenant_id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Mapping not found']);
            exit;
        }

        $pdo->prepare("DELETE FROM biometric_employee_mapping WHERE id = ? AND tenant_id = ?")
            ->execute([$id, $tenant_id]);

        logActivity($pdo, $tenant_id, $user_id, 'delete', 'Biometric Mapping', $id,
            "{$row['machine_employee_id']} → {$row['employee_id']}",
            "Removed biometric mapping for machine ID \"{$row['machine_employee_id']}\"",
            null, null
        );

        echo json_encode(['success' => true, 'message' => 'Mapping deleted']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Throwable $e) {
    error_log('Biometric Mappings API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
