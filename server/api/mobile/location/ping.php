<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$tenantId = $auth['tenant_id'];
$userId = $auth['user_id'];

$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    // Make sure a settings row exists (first ping ever from this user) —
    // defaults both switches to on, matching "everyone tracked unless
    // someone turns it off" from the admin's side.
    $pdo->prepare(
        "INSERT INTO officer_tracking_settings (tenant_id, user_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE id = id"
    )->execute([$tenantId, $userId]);

    $settingsStmt = $pdo->prepare(
        "SELECT admin_enabled, officer_enabled FROM officer_tracking_settings WHERE tenant_id = ? AND user_id = ?"
    );
    $settingsStmt->execute([$tenantId, $userId]);
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings['admin_enabled']) {
        echo json_encode(['success' => false, 'message' => 'Location tracking has been turned off by your admin', 'reason' => 'disabled_by_admin']);
        exit;
    }
    if (!$settings['officer_enabled']) {
        echo json_encode(['success' => false, 'message' => 'Location sharing is paused', 'reason' => 'paused_by_officer']);
        exit;
    }

    // Status-only report: the app couldn't get a real location (GPS off /
    // permission denied) but still wants the web side to know why, instead
    // of just going stale-and-silent. No location row is inserted.
    if (!empty($input['status']) && !isset($input['latitude'])) {
        $status = in_array($input['status'], ['gps_off', 'permission_denied'], true) ? $input['status'] : null;
        if (!$status) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        $pdo->prepare(
            "UPDATE officer_tracking_settings SET last_status = ?, last_status_at = NOW() WHERE tenant_id = ? AND user_id = ?"
        )->execute([$status, $tenantId, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    $latitude = isset($input['latitude']) ? (float) $input['latitude'] : null;
    $longitude = isset($input['longitude']) ? (float) $input['longitude'] : null;
    $accuracy = isset($input['accuracy']) ? (float) $input['accuracy'] : null;

    if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid latitude/longitude required']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO officer_location_pings (tenant_id, user_id, latitude, longitude, accuracy_meters, recorded_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$tenantId, $userId, $latitude, $longitude, $accuracy]);

    // A real location came through — any previously reported GPS/permission
    // problem no longer applies.
    $pdo->prepare(
        "UPDATE officer_tracking_settings SET last_status = NULL, last_status_at = NULL WHERE tenant_id = ? AND user_id = ?"
    )->execute([$tenantId, $userId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save location']);
}
