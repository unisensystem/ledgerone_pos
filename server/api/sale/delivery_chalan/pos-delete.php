<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Delivery Chalan ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify it exists (grab full row for the Activity Log)
    $checkStmt = $pdo->prepare("SELECT * FROM delivery_chalan WHERE id = ? AND tenant_id = ?");
    $checkStmt->execute([$invoice_id, $tenant_id]);
    $chalanRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$chalanRow) {
        throw new Exception('Delivery Chalan not found');
    }

    // Delete items first
    $pdo->prepare("DELETE FROM delivery_chalan_items WHERE delivery_chalan_id = ? AND tenant_id = ?")
        ->execute([$invoice_id, $tenant_id]);

    // Delete the chalan
    $stmt = $pdo->prepare("DELETE FROM delivery_chalan WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$invoice_id, $tenant_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Delivery Chalan not found or already deleted');
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Delivery Chalan', $invoice_id, $chalanRow['chalan_no'],
        "Deleted delivery chalan \"{$chalanRow['chalan_no']}\"",
        $chalanRow, null
    );

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Delivery Chalan deleted successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
