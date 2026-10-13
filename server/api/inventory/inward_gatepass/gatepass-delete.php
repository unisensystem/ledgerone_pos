<?php
session_start();
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        throw new Exception('Gatepass ID is required');
    }
    
    // Grab values before deletion, for the Activity Log (record is gone afterward)
    $stmt = $pdo->prepare("SELECT gatepass_code, date, company_id, supplier_id, branch_id, container_id FROM inward_gatepass WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    $gatepassRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$gatepassRow) {
        throw new Exception('Gatepass not found or already deleted');
    }

    $pdo->beginTransaction();

    // Delete items first
    $stmt = $pdo->prepare("DELETE FROM inward_gatepass_items WHERE gatepass_id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);

    // Delete gatepass
    $stmt = $pdo->prepare("DELETE FROM inward_gatepass WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Gatepass not found or already deleted');
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Inward Gatepass', $input['id'], $gatepassRow['gatepass_code'],
        "Deleted inward gatepass \"{$gatepassRow['gatepass_code']}\"",
        $gatepassRow, null
    );

    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Gatepass deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}