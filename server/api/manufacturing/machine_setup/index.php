<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET - Fetch machines or branches
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.code,
                m.machine_name,
                m.branch_id,
                m.capacity_speed,
                m.notes,
                m.is_active,
                m.created_at,
                m.updated_at,
                b.branch_name
            FROM machines m
            LEFT JOIN branches b ON m.branch_id = b.id
            WHERE m.tenant_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'view' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.code,
                m.machine_name,
                m.branch_id,
                m.capacity_speed,
                m.notes,
                m.is_active,
                b.branch_name
            FROM machines m
            LEFT JOIN branches b ON m.branch_id = b.id
            WHERE m.id = ? AND m.tenant_id = ?
        ");
        $stmt->execute([$_GET['id'], $tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        exit;
    }
    
    if ($action === 'branches') {
        $stmt = $pdo->prepare("SELECT id, branch_name FROM branches WHERE tenant_id = ? AND is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'next_code') {
        $stmt = $pdo->prepare("SELECT code FROM machines WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastCode = $stmt->fetchColumn();
        
        if ($lastCode && preg_match('/MCH-(\d+)/', $lastCode, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        
        $newCode = 'MCH-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        echo json_encode(['success' => true, 'code' => $newCode]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// POST - Create or Update machine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $code = $data['code'] ?? null;
    $machine_name = $data['machine_name'] ?? null;
    $branch_id = $data['branch_id'] ?? null;
    $capacity_speed = $data['capacity_speed'] ?? null;
    $notes = $data['notes'] ?? null;
    $is_active = $data['is_active'] ?? 1;
    
    if (!$code || !$machine_name || !$branch_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        if ($id) {
            // Update
            $stmt = $pdo->prepare("SELECT code, machine_name, branch_id, capacity_speed, notes, is_active FROM machines WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $oldMachine = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldMachine) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Machine not found']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE machines SET code = ?, machine_name = ?, branch_id = ?, capacity_speed = ?, notes = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$code, $machine_name, $branch_id, $capacity_speed, $notes, $is_active, $id, $tenant_id]);

            $newMachine = ['code' => $code, 'machine_name' => $machine_name, 'branch_id' => $branch_id, 'capacity_speed' => $capacity_speed, 'notes' => $notes, 'is_active' => $is_active];
            $changeDescriptions = buildChangeDescription($oldMachine, $newMachine, [
                'code' => 'Code', 'machine_name' => 'Machine Name', 'branch_id' => 'Branch', 'capacity_speed' => 'Capacity/Speed', 'notes' => 'Notes', 'is_active' => 'Active'
            ]);
            $editDescription = !empty($changeDescriptions)
                ? "Updated machine \"$machine_name\" ($code): " . implode('; ', $changeDescriptions)
                : "Updated machine \"$machine_name\" ($code) - no tracked field changes";
            logActivity($pdo, $tenant_id, $user_id, 'update', 'Machine', $id, $code, $editDescription, $oldMachine, $newMachine);

            echo json_encode(['success' => true, 'message' => 'Machine updated successfully']);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO machines (tenant_id, code, machine_name, branch_id, capacity_speed, notes, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$tenant_id, $code, $machine_name, $branch_id, $capacity_speed, $notes, $is_active, $user_id]);
            $newMachineId = $pdo->lastInsertId();

            logActivity(
                $pdo, $tenant_id, $user_id, 'create', 'Machine', $newMachineId, $code,
                "Created machine \"$machine_name\" ($code)",
                null,
                ['code' => $code, 'machine_name' => $machine_name, 'branch_id' => $branch_id, 'capacity_speed' => $capacity_speed, 'is_active' => $is_active]
            );

            echo json_encode(['success' => true, 'message' => 'Machine added successfully', 'id' => $newMachineId]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE - Delete machine
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing machine ID']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $machineRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);

        if ($machineRow) {
            logActivity(
                $pdo, $tenant_id, $user_id, 'delete', 'Machine', $id, $machineRow['code'],
                "Deleted machine \"{$machineRow['machine_name']}\" ({$machineRow['code']})",
                $machineRow, null
            );
        }

        echo json_encode(['success' => true, 'message' => 'Machine deleted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
