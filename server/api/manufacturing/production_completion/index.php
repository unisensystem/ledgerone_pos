<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Load Production Orders (In Progress only, or specific order for edit)
    if ($action === 'production_orders') {
        $edit_po_id = $_GET['edit_po_id'] ?? null;
        
        if ($edit_po_id) {
            // Include the specific production order for editing, regardless of status
            $stmt = $pdo->prepare("
                SELECT 
                    po.id,
                    po.order_no,
                    po.status,
                    p.name as product_name
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.tenant_id = ?
                AND (po.status = 'In Progress' OR po.id = ?)
                ORDER BY po.created_at DESC
            ");
            $stmt->execute([$tenant_id, $edit_po_id]);
        } else {
            // Normal mode: only In Progress orders
            $stmt = $pdo->prepare("
                SELECT 
                    po.id,
                    po.order_no,
                    po.status,
                    p.name as product_name
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.tenant_id = ?
                AND po.status = 'In Progress'
                ORDER BY po.created_at DESC
            ");
            $stmt->execute([$tenant_id]);
        }
        
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Get Next Completion Number
    if ($action === 'next_completion_no') {
        $stmt = $pdo->prepare("SELECT completion_no FROM production_completions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastNo = $stmt->fetchColumn();
        
        if ($lastNo && preg_match('/PC-(\d+)/', $lastNo, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        $completionNo = 'PC-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        echo json_encode(['success' => true, 'completion_no' => $completionNo]);
        exit;
    }
    
    // Load Order Details
    if ($action === 'order_details' && isset($_GET['po_id'])) {
        $stmt = $pdo->prepare("
            SELECT 
                po.*,
                b.branch_name,
                m.machine_name
            FROM production_orders po
            JOIN branches b ON po.branch_id = b.id
            LEFT JOIN machines m ON po.machine_id = m.id
            WHERE po.id = ? AND po.tenant_id = ?
        ");
        $stmt->execute([$_GET['po_id'], $tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Load Products with WIP Cost - Dynamic UOM
    if ($action === 'products' && isset($_GET['po_id'])) {
        ob_start();
        $poId = $_GET['po_id'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    po.product_id,
                    po.order_qty,
                    p.code as product_code,
                    p.name as product_name,
                    p.uom_type,
                    p.default_unit_id,
                    p.uom_group_id
                FROM production_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.id = ? AND po.tenant_id = ?
            ");
            $stmt->execute([$poId, $tenant_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }
            
            // Get total WIP cost (materials)
            $wipStmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_cost), 0) as total_wip_cost
                FROM work_in_progress
                WHERE production_order_id = ?
            ");
            $wipStmt->execute([$poId]);
            $wip = $wipStmt->fetch(PDO::FETCH_ASSOC);
            $totalWipCost = $wip['total_wip_cost'];
            
            // Get costing method configuration
            $configStmt = $pdo->prepare("
                SELECT config_value 
                FROM system_config 
                WHERE tenant_id = ? AND config_key = 'costing_method'
            ");
            $configStmt->execute([$tenant_id]);
            $costingMethod = $configStmt->fetchColumn() ?: 'actual';
            
            // Get total production expenses (actual posted)
            $expenseStmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as total_expenses
                FROM production_expenses
                WHERE production_order_id = ? AND status = 'Posted'
            ");
            $expenseStmt->execute([$poId]);
            $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
            $totalExpenses = $expense['total_expenses'];
            
            // Calculate overhead to apply
            $overheadToApply = 0;
            $costingMethodUsed = 'actual';
            
            if ($costingMethod === 'standard') {
                // STANDARD COSTING: Use predetermined rates
                $rateStmt = $pdo->prepare("
                    SELECT rate_type, rate_value
                    FROM overhead_rates
                    WHERE tenant_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $rateStmt->execute([$tenant_id]);
                $rate = $rateStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($rate) {
                    if ($rate['rate_type'] === 'per_unit') {
                        $overheadToApply = $product['order_qty'] * $rate['rate_value'];
                    } elseif ($rate['rate_type'] === 'percentage_of_material') {
                        $overheadToApply = $totalWipCost * ($rate['rate_value'] / 100);
                    } elseif ($rate['rate_type'] === 'per_hour') {
                        // Estimate hours from production order dates
                        $daysStmt = $pdo->prepare("
                            SELECT DATEDIFF(COALESCE(end_date, CURDATE()), start_date) as days
                            FROM production_orders
                            WHERE id = ?
                        ");
                        $daysStmt->execute([$poId]);
                        $days = $daysStmt->fetchColumn();
                        $hours = max(1, $days * 8); // Assume 8 hours per day
                        $overheadToApply = $hours * $rate['rate_value'];
                    }
                    $costingMethodUsed = 'standard';
                } else {
                    // Fallback to actual if no standard rate defined
                    $overheadToApply = $totalExpenses;
                    $costingMethodUsed = 'actual';
                }
            } else {
                // ACTUAL COSTING: Use posted expenses (may be 0 initially)
                $overheadToApply = $totalExpenses;
                $costingMethodUsed = 'actual';
            }
            
            // Total production cost = Materials + Overhead
            $totalProductionCost = $totalWipCost + $overheadToApply;
            
            // Get units based on uom_type
            $units = [];
            
            if ($product['uom_type'] === 'single' || $product['uom_type'] === 'unit') {
                // Single unit
                $unitStmt = $pdo->prepare("SELECT id, uom_name, conversion_factor, is_base_unit FROM uom WHERE id = ?");
                $unitStmt->execute([$product['default_unit_id']]);
                $unit = $unitStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($unit) {
                    $units[] = [
                        'unit_id' => $unit['id'],
                        'uom_name' => $unit['uom_name'],
                        'conversion_factor' => $unit['conversion_factor'],
                        'is_base_unit' => $unit['is_base_unit']
                    ];
                }
            } else {
                // UOM Group - get all units
                $unitStmt = $pdo->prepare("
                    SELECT u.id, u.uom_name, u.conversion_factor, u.is_base_unit
                    FROM uom_group_units ugu
                    JOIN uom u ON ugu.uom_id = u.id
                    WHERE ugu.uom_group_id = ?
                    ORDER BY u.is_base_unit DESC, u.id
                ");
                $unitStmt->execute([$product['uom_group_id']]);
                $unitsData = $unitStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($unitsData as $unit) {
                    $units[] = [
                        'unit_id' => $unit['id'],
                        'uom_name' => $unit['uom_name'],
                        'conversion_factor' => $unit['conversion_factor'],
                        'is_base_unit' => $unit['is_base_unit']
                    ];
                }
            }
            
            // Calculate unit cost (total production cost / order qty)
            $baseUnitCost = $product['order_qty'] > 0 ? $totalProductionCost / $product['order_qty'] : 0;
            
            // Create product entries for each unit
            $productData = [];
            foreach ($units as $unit) {
                // Get completed qty for this unit
                $compStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(cp.completed_qty), 0) as completed_qty
                    FROM completed_products cp
                    JOIN production_completions pc ON cp.production_completion_id = pc.id
                    WHERE pc.production_order_id = ? AND cp.uom_id = ?
                ");
                $compStmt->execute([$poId, $unit['unit_id']]);
                $comp = $compStmt->fetch(PDO::FETCH_ASSOC);
                
                $productData[] = [
                    'product_id' => $product['product_id'],
                    'product_code' => $product['product_code'],
                    'product_name' => $product['product_name'],
                    'order_qty' => $product['order_qty'],
                    'completed_qty' => $comp['completed_qty'],
                    'uom_id' => $unit['unit_id'],
                    'uom_name' => $unit['uom_name'],
                    'unit_cost' => $baseUnitCost,
                    'total_wip_cost' => $totalWipCost,
                    'total_expenses' => $totalExpenses,
                    'overhead_applied' => $overheadToApply,
                    'total_production_cost' => $totalProductionCost,
                    'costing_method' => $costingMethodUsed
                ];
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $productData]);
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// POST - Complete Production
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $po_id = $data['po_id'] ?? null;
    $branch_id = $data['branch_id'] ?? null;
    $complete_date = $data['complete_date'] ?? null;
    $products = $data['products'] ?? [];
    
    if (!$po_id || !$branch_id || !$complete_date || empty($products)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get next completion number
        $stmt = $pdo->prepare("SELECT completion_no FROM production_completions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastNo = $stmt->fetchColumn();
        
        if ($lastNo && preg_match('/PC-(\d+)/', $lastNo, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        $completionNo = 'PC-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        // Get machine_id from production order
        $stmt = $pdo->prepare("SELECT machine_id, order_qty FROM production_orders WHERE id = ?");
        $stmt->execute([$po_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $machine_id = $order['machine_id'];
        $planned_qty = $order['order_qty'];
        
        // Calculate totals
        $totalProducts = count($products);
        $totalQuantity = 0;
        $totalCost = 0;
        
        foreach ($products as $prod) {
            $totalQuantity += $prod['completed_qty'];
            $totalCost += $prod['completed_qty'] * $prod['unit_cost'];
        }
        
        // Insert production_completions (header)
        $stmt = $pdo->prepare("
            INSERT INTO production_completions
            (tenant_id, completion_no, production_order_id, branch_id, machine_id, 
             complete_date, total_products, total_quantity, total_cost, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id,
            $completionNo,
            $po_id,
            $branch_id,
            $machine_id,
            $complete_date,
            $totalProducts,
            $totalQuantity,
            $totalCost,
            $user_id
        ]);
        $completion_id = $pdo->lastInsertId();
        
        // Get previous completed qty
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(completed_qty), 0) as total_completed FROM completed_products cp JOIN production_completions pc ON cp.production_completion_id = pc.id WHERE pc.production_order_id = ?");
        $stmt->execute([$po_id]);
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);
        $remaining_qty = $planned_qty - $prev['total_completed'];
        
        foreach ($products as $prod) {
            $prodTotalCost = $prod['completed_qty'] * $prod['unit_cost'];
            
            // Insert completed_products (details)
            $stmt = $pdo->prepare("
                INSERT INTO completed_products
                (production_completion_id, product_id, planned_qty, remaining_qty, 
                 completed_qty, uom_id, unit_cost, total_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $completion_id,
                $prod['product_id'],
                $planned_qty,
                $remaining_qty,
                $prod['completed_qty'],
                $prod['uom_id'],
                $prod['unit_cost'],
                $prodTotalCost
            ]);
            
            $remaining_qty -= $prod['completed_qty'];
        }
        
        // Calculate completion ratio for WIP material consumption
        $completion_ratio = $planned_qty > 0 ? $totalQuantity / $planned_qty : 0;
        
        // Get WIP materials from work_in_progress_items
        $wipStmt = $pdo->prepare("
            SELECT wpi.material_id, wpi.issue_qty, wpi.uom_id, wpi.unit_cost
            FROM work_in_progress_items wpi
            JOIN work_in_progress wp ON wpi.work_in_progress_id = wp.id
            WHERE wp.production_order_id = ?
        ");
        $wipStmt->execute([$po_id]);
        $wipMaterials = $wipStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 1. Calculate and store actual WIP materials consumed for this completion
        foreach ($wipMaterials as $material) {
            $consumed_qty = $material['issue_qty'] * $completion_ratio;
            $consumed_cost = $consumed_qty * $material['unit_cost'];
            
            // Store in production_completion_materials
            $stmt = $pdo->prepare("
                INSERT INTO production_completion_materials
                (production_completion_id, material_id, consumed_qty, uom_id, unit_cost, total_cost)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $completion_id,
                $material['material_id'],
                $consumed_qty,
                $material['uom_id'],
                $material['unit_cost'],
                $consumed_cost
            ]);
            
            // 2. Raw Materials OUT from WIP (account_id = 116)
            $stmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                 qty_out, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, 116, ?, ?, 'production_completions', ?, ?, ?, ?, 'WIP_OUT', ?, NOW())
            ");
            $stmt->execute([
                $tenant_id,
                $branch_id,
                $material['material_id'],
                $completion_id,
                $consumed_qty,
                $material['unit_cost'],
                $material['uom_id'],
                $complete_date
            ]);
        }
        
        // 3. Finished Goods IN (account_id = 117)
        foreach ($products as $prod) {
            $stmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                 qty_in, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, 117, ?, ?, 'production_completions', ?, ?, ?, ?, 'PRODUCTION_RECEIVE', ?, NOW())
            ");
            $stmt->execute([
                $tenant_id,
                $branch_id,
                $prod['product_id'],
                $completion_id,
                $prod['completed_qty'],
                $prod['unit_cost'],
                $prod['uom_id'],
                $complete_date
            ]);
        }
        
        // 4. Accounting Ledger Entries
        $glStmt = $pdo->prepare("
            INSERT INTO accounting_ledger
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at)
            VALUES (?, 'production_completion', 'production_completions', ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Debit: Finished Goods Inventory (account 117)
        $glStmt->execute([$tenant_id, $completion_id, 117, $complete_date, 'Production completed: ' . $completionNo, $totalCost, 0]);
        
        // Credit: WIP Inventory (account 116)
        $glStmt->execute([$tenant_id, $completion_id, 116, $complete_date, 'Production completed: ' . $completionNo, 0, $totalCost]);
        
        // Check if fully completed
        $stmt = $pdo->prepare("
            SELECT order_qty, 
                   (SELECT COALESCE(SUM(cp.completed_qty), 0) 
                    FROM completed_products cp 
                    JOIN production_completions pc ON cp.production_completion_id = pc.id 
                    WHERE pc.production_order_id = ?) as total_completed
            FROM production_orders WHERE id = ?
        ");
        $stmt->execute([$po_id, $po_id]);
        $check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check['total_completed'] >= $check['order_qty']) {
            $stmt = $pdo->prepare("UPDATE production_orders SET status = 'Completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$po_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Production completed successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// PUT - Update Production Completion
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $completion_id = $data['completion_id'] ?? null;
    $po_id = $data['po_id'] ?? null;
    $branch_id = $data['branch_id'] ?? null;
    $complete_date = $data['complete_date'] ?? null;
    $products = $data['products'] ?? [];
    
    if (!$completion_id || !$po_id || !$branch_id || !$complete_date || empty($products)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get existing completion
        $stmt = $pdo->prepare("SELECT * FROM production_completions WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$completion_id, $tenant_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            throw new Exception('Completion not found');
        }
        
        // Delete old stock ledger entries
        $stmt = $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'production_completions' AND reference_id = ?");
        $stmt->execute([$completion_id]);
        
        // Delete old accounting ledger entries
        $stmt = $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'production_completions' AND reference_id = ? AND tenant_id = ?");
        $stmt->execute([$completion_id, $tenant_id]);
        
        // Delete old completed products
        $stmt = $pdo->prepare("DELETE FROM completed_products WHERE production_completion_id = ?");
        $stmt->execute([$completion_id]);
        
        // Delete old completion materials
        $stmt = $pdo->prepare("DELETE FROM production_completion_materials WHERE production_completion_id = ?");
        $stmt->execute([$completion_id]);
        
        // Get machine_id and planned_qty from production order
        $stmt = $pdo->prepare("SELECT machine_id, order_qty FROM production_orders WHERE id = ?");
        $stmt->execute([$po_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $machine_id = $order['machine_id'];
        $planned_qty = $order['order_qty'];
        
        // Calculate new totals
        $totalProducts = count($products);
        $totalQuantity = 0;
        $totalCost = 0;
        
        foreach ($products as $prod) {
            $totalQuantity += $prod['completed_qty'];
            $totalCost += $prod['completed_qty'] * $prod['unit_cost'];
        }
        
        // Update production_completions header
        $stmt = $pdo->prepare("
            UPDATE production_completions
            SET branch_id = ?, machine_id = ?, complete_date = ?,
                total_products = ?, total_quantity = ?, total_cost = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $branch_id,
            $machine_id,
            $complete_date,
            $totalProducts,
            $totalQuantity,
            $totalCost,
            $completion_id
        ]);
        
        // Get previous completed qty (excluding current completion)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(cp.completed_qty), 0) as total_completed 
            FROM completed_products cp 
            JOIN production_completions pc ON cp.production_completion_id = pc.id 
            WHERE pc.production_order_id = ? AND pc.id != ?
        ");
        $stmt->execute([$po_id, $completion_id]);
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);
        $remaining_qty = $planned_qty - $prev['total_completed'];
        
        // Insert new completed products
        foreach ($products as $prod) {
            $prodTotalCost = $prod['completed_qty'] * $prod['unit_cost'];
            
            $stmt = $pdo->prepare("
                INSERT INTO completed_products
                (production_completion_id, product_id, planned_qty, remaining_qty, 
                 completed_qty, uom_id, unit_cost, total_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $completion_id,
                $prod['product_id'],
                $planned_qty,
                $remaining_qty,
                $prod['completed_qty'],
                $prod['uom_id'],
                $prod['unit_cost'],
                $prodTotalCost
            ]);
            
            $remaining_qty -= $prod['completed_qty'];
        }
        
        // Calculate new completion ratio for WIP material consumption
        $completion_ratio = $planned_qty > 0 ? $totalQuantity / $planned_qty : 0;
        
        // Get WIP materials
        $wipStmt = $pdo->prepare("
            SELECT wpi.material_id, wpi.issue_qty, wpi.uom_id, wpi.unit_cost
            FROM work_in_progress_items wpi
            JOIN work_in_progress wp ON wpi.work_in_progress_id = wp.id
            WHERE wp.production_order_id = ?
        ");
        $wipStmt->execute([$po_id]);
        $wipMaterials = $wipStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert new completion materials and stock ledger entries - Raw Materials OUT from WIP
        foreach ($wipMaterials as $material) {
            $consumed_qty = $material['issue_qty'] * $completion_ratio;
            $consumed_cost = $consumed_qty * $material['unit_cost'];
            
            // Store in production_completion_materials
            $stmt = $pdo->prepare("
                INSERT INTO production_completion_materials
                (production_completion_id, material_id, consumed_qty, uom_id, unit_cost, total_cost)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $completion_id,
                $material['material_id'],
                $consumed_qty,
                $material['uom_id'],
                $material['unit_cost'],
                $consumed_cost
            ]);
            
            // Insert stock ledger entry
            $stmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                 qty_out, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, 116, ?, ?, 'production_completions', ?, ?, ?, ?, 'WIP_OUT', ?, NOW())
            ");
            $stmt->execute([
                $tenant_id,
                $branch_id,
                $material['material_id'],
                $completion_id,
                $consumed_qty,
                $material['unit_cost'],
                $material['uom_id'],
                $complete_date
            ]);
        }
        
        // Insert new stock ledger entries - Finished Goods IN
        foreach ($products as $prod) {
            $stmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                 qty_in, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, 117, ?, ?, 'production_completions', ?, ?, ?, ?, 'PRODUCTION_RECEIVE', ?, NOW())
            ");
            $stmt->execute([
                $tenant_id,
                $branch_id,
                $prod['product_id'],
                $completion_id,
                $prod['completed_qty'],
                $prod['unit_cost'],
                $prod['uom_id'],
                $complete_date
            ]);
        }
        
        // Insert new accounting ledger entries
        $glStmt = $pdo->prepare("
            INSERT INTO accounting_ledger
            (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at)
            VALUES (?, 'production_completion', 'production_completions', ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Get completion_no for description
        $compNoStmt = $pdo->prepare("SELECT completion_no FROM production_completions WHERE id = ?");
        $compNoStmt->execute([$completion_id]);
        $compNo = $compNoStmt->fetchColumn();
        
        // Debit: Finished Goods Inventory (account 117)
        $glStmt->execute([$tenant_id, $completion_id, 117, $complete_date, 'Production completed: ' . $compNo, $totalCost, 0]);
        
        // Credit: WIP Inventory (account 116)
        $glStmt->execute([$tenant_id, $completion_id, 116, $complete_date, 'Production completed: ' . $compNo, 0, $totalCost]);
        
        // Check if production order should be marked as completed
        $stmt = $pdo->prepare("
            SELECT order_qty, 
                   (SELECT COALESCE(SUM(cp.completed_qty), 0) 
                    FROM completed_products cp 
                    JOIN production_completions pc ON cp.production_completion_id = pc.id 
                    WHERE pc.production_order_id = ?) as total_completed
            FROM production_orders WHERE id = ?
        ");
        $stmt->execute([$po_id, $po_id]);
        $check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($check['total_completed'] >= $check['order_qty']) {
            $stmt = $pdo->prepare("UPDATE production_orders SET status = 'Completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$po_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE production_orders SET status = 'In Progress', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$po_id]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Production completion updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
