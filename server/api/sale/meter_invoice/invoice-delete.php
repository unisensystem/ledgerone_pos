<?php
require_once '../../../../includes/connection.php';

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
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['invoice_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing invoice ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Check if invoice exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT * FROM station_daily_usage WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    // Delete related stock_ledger entries
    $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'station_daily_usage' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    
    // Delete related accounting_ledger entries
    $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'station_daily_usage' AND reference_id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    
    // Delete related revenue_split entries
    $stmt = $pdo->prepare("DELETE FROM revenue_split WHERE station_daily_usage_id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    
    // Delete auto-generated next invoice if exists and not completed
    if ($invoice['closing_reading'] !== null) {
        $nextDate = date('Y-m-d', strtotime($invoice['usage_date'] . ' +1 day'));
        $stmt = $pdo->prepare("DELETE FROM station_daily_usage WHERE tenant_id = ? AND station_id = ? AND branch_id = ? AND product_id = ? AND unit_id = ? AND usage_date = ? AND closing_reading IS NULL");
        $stmt->execute([$tenant_id, $invoice['station_id'], $invoice['branch_id'], $invoice['product_id'], $invoice['unit_id'], $nextDate]);
    }
    
    // Delete the invoice
    $stmt = $pdo->prepare("DELETE FROM station_daily_usage WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['invoice_id'], $tenant_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error deleting invoice: ' . $e->getMessage()]);
}
?>