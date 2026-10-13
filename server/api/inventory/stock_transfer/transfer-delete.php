<?php
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

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $transfer_id = $input['transfer_id'];
    
    if (!$transfer_id) {
        echo json_encode(['success' => false, 'message' => 'Transfer ID is required']);
        exit;
    }
    
    // Grab values before deletion, for the Activity Log (record is gone afterward)
    $stmt = $pdo->prepare("SELECT transfer_code, company_id, date, from_location_id, to_location_id, container_id, remarks, total_amount, total_items FROM stock_transfer WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$tenant_id, $transfer_id]);
    $transferRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transferRow) {
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Delete stock ledger entries
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE tenant_id = ? AND reference_table = 'stock_transfer' AND reference_id = ?");
    $stmt->execute([$tenant_id, $transfer_id]);
    
    // Delete stock transfer items
    $stmt = $pdo->prepare("DELETE FROM stock_transfer_items WHERE tenant_id = ? AND transfer_id = ?");
    $stmt->execute([$tenant_id, $transfer_id]);
    
    // Delete stock transfer
    $stmt = $pdo->prepare("DELETE FROM stock_transfer WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$tenant_id, $transfer_id]);
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Stock Transfer', $transfer_id, $transferRow['transfer_code'],
        "Deleted stock transfer \"{$transferRow['transfer_code']}\"",
        $transferRow, null
    );

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Transfer deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
