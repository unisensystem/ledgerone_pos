<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing record ID']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Verify record exists and belongs to tenant (grab full row for the Activity Log)
    $stmt = $pdo->prepare("SELECT id, as_of_date, opening_amount FROM cash_opening WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Record not found']);
        exit();
    }

    // Delete accounting ledger entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'cash_opening' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);

    // Delete cash opening record
    $stmt = $pdo->prepare("DELETE FROM cash_opening WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Cash Opening', $id, $record['as_of_date'],
        "Deleted cash opening (as of {$record['as_of_date']}): Amount " . number_format($record['opening_amount'], 2),
        $record, null
    );

    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete record', 'message' => $e->getMessage()]);
}
