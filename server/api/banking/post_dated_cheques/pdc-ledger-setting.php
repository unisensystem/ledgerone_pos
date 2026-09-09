<?php
session_start();
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT pending_pdc_affects_ledger FROM pdc_ledger_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'pending_pdc_affects_ledger' => $row ? (bool)$row['pending_pdc_affects_ledger'] : false
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $enabled = !empty($data['pending_pdc_affects_ledger']) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO pdc_ledger_settings (tenant_id, pending_pdc_affects_ledger, updated_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE pending_pdc_affects_ledger = VALUES(pending_pdc_affects_ledger), updated_by = VALUES(updated_by)
        ");
        $stmt->execute([$tenant_id, $enabled, $user_id]);

        echo json_encode(['success' => true, 'pending_pdc_affects_ledger' => (bool)$enabled]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
