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
    
    // Get all production orders completed in this period
    $stmt = $pdo->prepare("
        SELECT DISTINCT pc.production_order_id, po.order_qty
        FROM production_completions pc
        INNER JOIN production_orders po ON pc.production_order_id = po.id
        WHERE pc.tenant_id = ?
        AND pc.complete_date >= ?
        AND pc.complete_date <= ?
    ");
    $stmt->execute([$tenant_id, $period_from, $period_to]);
    $production_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $adjustments = [];
    
    foreach ($production_orders as $po) {
        $po_id = $po['production_order_id'];
        $order_qty = $po['order_qty'];
        
        // Get material cost (WIP)
        $wipStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_cost), 0) as material_cost
            FROM work_in_progress
            WHERE production_order_id = ?
        ");
        $wipStmt->execute([$po_id]);
        $material_cost = $wipStmt->fetchColumn();
        
        // Get actual expenses posted (including period overhead)
        $expenseStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total_expenses
            FROM production_expenses
            WHERE production_order_id = ? AND status = 'Posted'
        ");
        $expenseStmt->execute([$po_id]);
        $actual_expenses = $expenseStmt->fetchColumn();
        
        // Calculate new unit cost
        $total_cost = $material_cost + $actual_expenses;
        $new_unit_cost = $order_qty > 0 ? $total_cost / $order_qty : 0;
        
        // Get old unit cost from completed_products
        $oldCostStmt = $pdo->prepare("
            SELECT cp.unit_cost, cp.completed_qty, cp.product_id, cp.uom_id, pc.id as completion_id
            FROM completed_products cp
            INNER JOIN production_completions pc ON cp.production_completion_id = pc.id
            WHERE pc.production_order_id = ?
        ");
        $oldCostStmt->execute([$po_id]);
        $completions = $oldCostStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($completions as $comp) {
            $old_unit_cost = $comp['unit_cost'];
            $cost_variance = $new_unit_cost - $old_unit_cost;
            
            if (abs($cost_variance) > 0.01) { // Only adjust if significant difference
                // Update completed_products
                $updateStmt = $pdo->prepare("
                    UPDATE completed_products
                    SET unit_cost = ?, 
                        total_cost = ? * completed_qty,
                        material_cost = ?,
                        overhead_cost = ?,
                        cost_adjusted = 1,
                        adjusted_at = NOW()
                    WHERE production_completion_id = ? AND product_id = ?
                ");
                $material_cost_per_unit = $order_qty > 0 ? $material_cost / $order_qty : 0;
                $overhead_cost_per_unit = $new_unit_cost - $material_cost_per_unit;
                $updateStmt->execute([
                    $new_unit_cost, 
                    $new_unit_cost, 
                    $material_cost_per_unit,
                    $overhead_cost_per_unit,
                    $comp['completion_id'], 
                    $comp['product_id']
                ]);
                
                // Update stock_ledger for finished goods
                $updateStockStmt = $pdo->prepare("
                    UPDATE stock_ledger
                    SET unit_cost = ?
                    WHERE reference_table = 'production_completions'
                    AND reference_id = ?
                    AND product_id = ?
                    AND unit_id = ?
                    AND account_id = 117
                    AND transaction_type = 'PRODUCTION_RECEIVE'
                ");
                $updateStockStmt->execute([$new_unit_cost, $comp['completion_id'], $comp['product_id'], $comp['uom_id']]);
                
                // CRITICAL: Adjust COGS for already sold items
                // Find all sales of this product from this completion batch
                $salesStmt = $pdo->prepare("
                    SELECT sl.id, sl.qty_out, sl.reference_id, sl.reference_table
                    FROM stock_ledger sl
                    WHERE sl.product_id = ?
                    AND sl.unit_id = ?
                    AND sl.account_id = 117
                    AND sl.transaction_type IN ('SALE', 'INVOICE_SALE')
                    AND sl.transaction_date >= (
                        SELECT complete_date FROM production_completions WHERE id = ?
                    )
                    AND sl.transaction_date <= ?
                ");
                $salesStmt->execute([$comp['product_id'], $comp['uom_id'], $comp['completion_id'], $period_to]);
                $sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($sales as $sale) {
                    // Update stock ledger COGS entry
                    $updateSaleStmt = $pdo->prepare("
                        UPDATE stock_ledger
                        SET unit_cost = ?
                        WHERE id = ?
                    ");
                    $updateSaleStmt->execute([$new_unit_cost, $sale['id']]);
                    
                    // Update invoice/sale item COGS if exists
                    if ($sale['reference_table'] === 'invoices') {
                        $updateInvoiceStmt = $pdo->prepare("
                            UPDATE invoice_items
                            SET cost_price = ?
                            WHERE invoice_id = ? AND product_id = ?
                        ");
                        $updateInvoiceStmt->execute([$new_unit_cost, $sale['reference_id'], $comp['product_id']]);
                    }
                }
                
                $adjustments[] = [
                    'po_id' => $po_id,
                    'product_id' => $comp['product_id'],
                    'old_cost' => $old_unit_cost,
                    'new_cost' => $new_unit_cost,
                    'variance' => $cost_variance,
                    'qty' => $comp['completed_qty'],
                    'total_adjustment' => $cost_variance * $comp['completed_qty'],
                    'sales_adjusted' => count($sales)
                ];
                
                // Log adjustment
                $logStmt = $pdo->prepare("
                    INSERT INTO cost_adjustments
                    (tenant_id, adjustment_date, period_from, period_to, production_order_id, 
                     product_id, old_unit_cost, new_unit_cost, cost_variance, qty_affected, 
                     total_adjustment, sales_adjusted, adjusted_by)
                    VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $logStmt->execute([
                    $tenant_id,
                    $period_from,
                    $period_to,
                    $po_id,
                    $comp['product_id'],
                    $old_unit_cost,
                    $new_unit_cost,
                    $cost_variance,
                    $comp['completed_qty'],
                    $cost_variance * $comp['completed_qty'],
                    count($sales),
                    $user_id
                ]);
            }
        }
        
        // Update production_completions total_cost
        $updateCompStmt = $pdo->prepare("
            UPDATE production_completions pc
            SET total_cost = (
                SELECT SUM(cp.total_cost)
                FROM completed_products cp
                WHERE cp.production_completion_id = pc.id
            )
            WHERE pc.production_order_id = ?
        ");
        $updateCompStmt->execute([$po_id]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cost adjustments completed',
        'adjustments' => $adjustments,
        'total_batches' => count($production_orders)
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
