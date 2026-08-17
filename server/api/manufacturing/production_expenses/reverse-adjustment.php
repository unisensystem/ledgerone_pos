<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $period_from = $input['period_from'] ?? null;
    $period_to = $input['period_to'] ?? null;
    
    if (!$period_from || !$period_to) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Period dates required']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Get all adjustments in this period
    $stmt = $pdo->prepare("
        SELECT * FROM cost_adjustments
        WHERE tenant_id = ?
        AND period_from = ?
        AND period_to = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$tenant_id, $period_from, $period_to]);
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($adjustments)) {
        throw new Exception('No adjustments found for this period');
    }
    
    $reversed_count = 0;
    
    foreach ($adjustments as $adj) {
        // Reset completed_products to original cost
        $resetStmt = $pdo->prepare("
            UPDATE completed_products cp
            INNER JOIN production_completions pc ON cp.production_completion_id = pc.id
            SET cp.unit_cost = ?,
                cp.total_cost = ? * cp.completed_qty,
                cp.material_cost = 0,
                cp.overhead_cost = 0,
                cp.cost_adjusted = 0,
                cp.adjusted_at = NULL
            WHERE pc.production_order_id = ?
            AND cp.product_id = ?
        ");
        $resetStmt->execute([
            $adj['old_unit_cost'],
            $adj['old_unit_cost'],
            $adj['production_order_id'],
            $adj['product_id']
        ]);
        
        // Reset stock_ledger
        $resetStockStmt = $pdo->prepare("
            UPDATE stock_ledger sl
            INNER JOIN production_completions pc ON sl.reference_id = pc.id
            SET sl.unit_cost = ?
            WHERE sl.reference_table = 'production_completions'
            AND pc.production_order_id = ?
            AND sl.product_id = ?
            AND sl.transaction_type = 'PRODUCTION_RECEIVE'
        ");
        $resetStockStmt->execute([
            $adj['old_unit_cost'],
            $adj['production_order_id'],
            $adj['product_id']
        ]);
        
        // Reset COGS for sales (if any were adjusted)
        if ($adj['sales_adjusted'] > 0) {
            $resetSalesStmt = $pdo->prepare("
                UPDATE stock_ledger sl
                INNER JOIN production_completions pc ON pc.production_order_id = ?
                SET sl.unit_cost = ?
                WHERE sl.product_id = ?
                AND sl.transaction_type IN ('SALE', 'INVOICE_SALE')
                AND sl.transaction_date >= pc.complete_date
                AND sl.transaction_date <= ?
            ");
            $resetSalesStmt->execute([
                $adj['production_order_id'],
                $adj['old_unit_cost'],
                $adj['product_id'],
                $period_to
            ]);
        }
        
        $reversed_count++;
    }
    
    // Update production_completions total_cost
    foreach ($adjustments as $adj) {
        $updateCompStmt = $pdo->prepare("
            UPDATE production_completions pc
            SET total_cost = (
                SELECT SUM(cp.total_cost)
                FROM completed_products cp
                WHERE cp.production_completion_id = pc.id
            )
            WHERE pc.production_order_id = ?
        ");
        $updateCompStmt->execute([$adj['production_order_id']]);
    }
    
    // Delete adjustment records
    $deleteStmt = $pdo->prepare("
        DELETE FROM cost_adjustments
        WHERE tenant_id = ?
        AND period_from = ?
        AND period_to = ?
    ");
    $deleteStmt->execute([$tenant_id, $period_from, $period_to]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cost adjustments reversed successfully',
        'reversed_count' => $reversed_count
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
