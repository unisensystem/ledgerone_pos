<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $expense_id = $input['expense_id'] ?? null;
    
    if (!$expense_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense ID required']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Get expense details
    $stmt = $pdo->prepare("
        SELECT * FROM production_expenses
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$expense_id, $tenant_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        throw new Exception('Expense not found');
    }
    
    // Check if expense is already used in cost adjustments
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM cost_adjustments
        WHERE production_order_id = ? AND tenant_id = ?
    ");
    $checkStmt->execute([$expense['production_order_id'], $tenant_id]);
    $adjustmentCheck = $checkStmt->fetch();
    
    if ($adjustmentCheck['count'] > 0) {
        throw new Exception('Cannot delete: Expense has been used in cost adjustments. Please reverse the adjustment first.');
    }
    
    // Delete accounting ledger entries
    $deleteGLStmt = $pdo->prepare("
        DELETE FROM accounting_ledger
        WHERE reference_table = 'production_expenses'
        AND reference_id = ?
        AND tenant_id = ?
    ");
    $deleteGLStmt->execute([$expense_id, $tenant_id]);
    
    // Delete post-dated cheques if any
    $deleteChequeStmt = $pdo->prepare("
        DELETE FROM post_dated_cheques
        WHERE reference_table = 'production_expenses'
        AND reference_id = ?
        AND tenant_id = ?
    ");
    $deleteChequeStmt->execute([$expense_id, $tenant_id]);
    
    // Delete expense items
    $deleteItemsStmt = $pdo->prepare("
        DELETE FROM production_expense_items
        WHERE expense_id = ?
    ");
    $deleteItemsStmt->execute([$expense_id]);
    
    // Delete expense header
    $deleteExpenseStmt = $pdo->prepare("
        DELETE FROM production_expenses
        WHERE id = ? AND tenant_id = ?
    ");
    $deleteExpenseStmt->execute([$expense_id, $tenant_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Production expense deleted successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
