<?php
// Persists the Purchase Invoice "Invoice Settings" modal (client/pages/purchase/
// grocery_purchase_invoice/purchase-add.php) to the database per user, instead of only
// browser localStorage — same pattern/table as
// server/api/sale/pos_invoice/invoice-settings.php (page_type = 'purchase'
// here vs 'pos'/'med' there). See purchase-add.js's localStorage.getItem/
// setItem override at the top of the file — that's where this endpoint is
// called from (GET on load, POST after save).

require_once '../../../../includes/connection.php';

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$page_type = 'purchase';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT settings_json FROM user_invoice_settings WHERE tenant_id = ? AND user_id = ? AND page_type = ?");
        $stmt->execute([$tenant_id, $user_id, $page_type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $settings = [];
        if ($row && !empty($row['settings_json'])) {
            $decoded = json_decode($row['settings_json'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        echo json_encode(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        error_log('Purchase invoice settings load error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $settings = $input['settings'] ?? null;

    if (!is_array($settings)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'settings must be an object']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_invoice_settings (tenant_id, user_id, page_type, settings_json)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json)
        ");
        $stmt->execute([$tenant_id, $user_id, $page_type, json_encode($settings)]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('Purchase invoice settings save error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
