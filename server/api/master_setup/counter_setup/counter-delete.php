<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        throw new Exception('Counter ID is required');
    }

    $id = intval($input['id']);

    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM counters WHERE id = ? AND tenant_id = ?");
    $check->execute([$id, $tenant_id]);
    if (!$check->fetchColumn()) {
        throw new Exception('Counter not found');
    }

    // Block delete if invoices reference this counter
    $used = $pdo->prepare("SELECT COUNT(*) FROM sale_invoice WHERE counter_id = ? AND tenant_id = ?");
    $used->execute([$id, $tenant_id]);
    if ($used->fetchColumn() > 0) {
        throw new Exception('Cannot delete: this counter has posted invoices. Deactivate it instead.');
    }

    $pdo->prepare("DELETE FROM counters WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);

    echo json_encode(['success' => true, 'message' => 'Counter deleted successfully']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
