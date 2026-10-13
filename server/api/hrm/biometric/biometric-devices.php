<?php
/*
|--------------------------------------------------------------------------
| Biometric Devices CRUD API
|--------------------------------------------------------------------------
| GET    -> list all devices for the tenant
| POST   -> create a new device (generates api_token automatically)
| PUT    -> update device name / location / is_active, or regenerate token
| DELETE -> delete a device
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
            SELECT id, device_name, device_location, api_token, is_active,
                   last_sync, created_at
            FROM biometric_devices
            WHERE tenant_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tenant_id]);

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // ── POST — create device ─────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $name     = trim($data['device_name']     ?? '');
        $location = trim($data['device_location'] ?? '');

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Device name is required']);
            exit;
        }

        $token = bin2hex(random_bytes(32)); // 64-char hex token

        $stmt = $pdo->prepare("
            INSERT INTO biometric_devices (tenant_id, device_name, device_location, api_token)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $name, $location ?: null, $token]);
        $newId = (int)$pdo->lastInsertId();

        logActivity($pdo, $tenant_id, $user_id, 'create', 'Biometric Device', $newId,
            $name, "Registered biometric device \"$name\"" . ($location ? " at $location" : ''),
            null, ['device_name' => $name, 'device_location' => $location]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully',
            'data'    => ['id' => $newId, 'api_token' => $token],
        ]);
        exit;
    }

    // ── PUT — update or regenerate token ─────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $id        = (int)($data['id'] ?? 0);
        $regen     = (bool)($data['regenerate_token'] ?? false);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid device ID']);
            exit;
        }

        // Verify ownership
        $chk = $pdo->prepare("SELECT id, device_name FROM biometric_devices WHERE id = ? AND tenant_id = ? LIMIT 1");
        $chk->execute([$id, $tenant_id]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Device not found']);
            exit;
        }

        $name      = trim($data['device_name']     ?? $existing['device_name']);
        $location  = trim($data['device_location'] ?? '');
        $is_active = isset($data['is_active']) ? (int)(bool)$data['is_active'] : null;

        $responseData = [];

        if ($regen) {
            $newToken = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE biometric_devices SET api_token = ? WHERE id = ? AND tenant_id = ?")
                ->execute([$newToken, $id, $tenant_id]);
            $responseData['api_token'] = $newToken;

            logActivity($pdo, $tenant_id, $user_id, 'update', 'Biometric Device', $id,
                $name, "Regenerated API token for device \"$name\"", null, null
            );
        }

        $sets = ['device_name = ?', 'device_location = ?'];
        $params = [$name, $location ?: null];

        if ($is_active !== null) {
            $sets[]   = 'is_active = ?';
            $params[] = $is_active;
        }

        $params[] = $id;
        $params[] = $tenant_id;

        $pdo->prepare("UPDATE biometric_devices SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?")
            ->execute($params);

        logActivity($pdo, $tenant_id, $user_id, 'update', 'Biometric Device', $id,
            $name, "Updated biometric device \"$name\"", null,
            ['device_name' => $name, 'device_location' => $location, 'is_active' => $is_active]
        );

        echo json_encode(['success' => true, 'message' => 'Device updated', 'data' => $responseData]);
        exit;
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid device ID']);
            exit;
        }

        $chk = $pdo->prepare("SELECT device_name FROM biometric_devices WHERE id = ? AND tenant_id = ? LIMIT 1");
        $chk->execute([$id, $tenant_id]);
        $dev = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$dev) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Device not found']);
            exit;
        }

        $pdo->prepare("DELETE FROM biometric_devices WHERE id = ? AND tenant_id = ?")
            ->execute([$id, $tenant_id]);

        logActivity($pdo, $tenant_id, $user_id, 'delete', 'Biometric Device', $id,
            $dev['device_name'], "Deleted biometric device \"{$dev['device_name']}\"", null, null
        );

        echo json_encode(['success' => true, 'message' => 'Device deleted']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (Throwable $e) {
    error_log('Biometric Devices API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
