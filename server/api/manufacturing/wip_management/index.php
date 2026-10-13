<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

ob_end_clean();

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    // Load Production Orders (Planned or In Progress only)
    if ($action === 'production_orders') {
        $stmt = $pdo->prepare("
            SELECT 
                po.id,
                po.order_no,
                po.status,
                po.branch_id,
                p.name as product_name
            FROM production_orders po
            JOIN products p ON po.product_id = p.id
            WHERE po.tenant_id = ?
            AND po.status IN ('Planned', 'In Progress')
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Get Next WIP Number
    if ($action === 'next_wip_number') {
        $stmt = $pdo->prepare("SELECT wip_number FROM work_in_progress WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $lastNumber = (int)str_replace('WIP-', '', $result['wip_number']);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        echo json_encode(['success' => true, 'next_wip_number' => 'WIP-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT)]);
        exit;
    }
    
    // Load Order Details
    if ($action === 'order_details' && isset($_GET['po_id'])) {
        $stmt = $pdo->prepare("
            SELECT 
                po.*,
                b.branch_name,
                m.machine_name,
                po.product_id as wip_product_id
            FROM production_orders po
            JOIN branches b ON po.branch_id = b.id
            LEFT JOIN machines m ON po.machine_id = m.id
            WHERE po.id = ? AND po.tenant_id = ?
        ");
        $stmt->execute([$_GET['po_id'], $tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Load WIP List
    if ($action === 'wip_list') {
        $stmt = $pdo->prepare("
            SELECT 
                wip.id,
                wip.wip_number,
                wip.issue_date,
                wip.total_items,
                wip.total_cost,
                po.order_no,
                b.branch_name
            FROM work_in_progress wip
            JOIN production_orders po ON wip.production_order_id = po.id
            JOIN branches b ON wip.branch_id = b.id
            WHERE wip.tenant_id = ?
            ORDER BY wip.id DESC
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Load WIP Details
    if ($action === 'wip_details' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT 
                wip.*,
                po.order_no,
                po.product_id as wip_product_id,
                b.branch_name,
                m.machine_name
            FROM work_in_progress wip
            JOIN production_orders po ON wip.production_order_id = po.id
            JOIN branches b ON wip.branch_id = b.id
            LEFT JOIN machines m ON wip.machine_id = m.id
            WHERE wip.id = ? AND wip.tenant_id = ?
        ");
        $stmt->execute([$_GET['id'], $tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Load WIP Items
    if ($action === 'wip_items' && isset($_GET['id'])) {
        ob_start();
        $stmt = $pdo->prepare("
            SELECT 
                wi.*,
                p.code as material_code,
                p.name as material_name,
                u.uom_name
            FROM work_in_progress_items wi
            JOIN products p ON wi.material_id = p.id
            LEFT JOIN uom u ON wi.uom_id = u.id
            WHERE wi.work_in_progress_id = ?
            ORDER BY p.code, u.id
        ");
        $stmt->execute([$_GET['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get WIP details for branch_id
        $wipStmt = $pdo->prepare("SELECT branch_id FROM work_in_progress WHERE id = ?");
        $wipStmt->execute([$_GET['id']]);
        $wipData = $wipStmt->fetch(PDO::FETCH_ASSOC);
        $branchId = $wipData['branch_id'];
        
        // Get real-time issued and available for each material
        foreach ($items as &$item) {
            // Cumulative issued qty - sum all issue_qty for this material+unit in all WIPs
            $issuedStmt = $pdo->prepare("
                SELECT COALESCE(SUM(wi.issue_qty), 0) as total_issued
                FROM work_in_progress_items wi
                JOIN work_in_progress wip ON wi.work_in_progress_id = wip.id
                WHERE wip.production_order_id = (
                    SELECT production_order_id FROM work_in_progress WHERE id = ?
                ) AND wi.material_id = ? AND wi.uom_id = ?
            ");
            $issuedStmt->execute([$_GET['id'], $item['material_id'], $item['uom_id']]);
            $issuedData = $issuedStmt->fetch(PDO::FETCH_ASSOC);
            $item['issued_qty'] = $issuedData['total_issued'] ?? 0;
            
            // Real-time available stock per unit
            $stockStmt = $pdo->prepare("
                SELECT COALESCE(SUM(qty_in - qty_out), 0) as available_stock
                FROM stock_ledger
                WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND unit_id = ?
            ");
            $stockStmt->execute([$tenant_id, $item['material_id'], $branchId, $item['uom_id']]);
            $stockData = $stockStmt->fetch(PDO::FETCH_ASSOC);
            $item['available_qty'] = $stockData['available_stock'];
        }
        
        ob_end_clean();
        echo json_encode(['success' => true, 'data' => $items]);
        exit;
    }
    
    // Load Materials with Stock and Cost
    if ($action === 'materials' && isset($_GET['po_id']) && isset($_GET['branch_id'])) {
        ob_start();
        $poId = $_GET['po_id'];
        $branchId = $_GET['branch_id'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    pom.id as pom_id,
                    pom.material_id,
                    pom.required_qty,
                    pom.issued_qty,
                    pom.uom_id,
                    pom.status,
                    p.code as material_code,
                    p.name as material_name,
                    u.uom_name
                FROM production_order_materials pom
                JOIN products p ON pom.material_id = p.id
                LEFT JOIN uom u ON pom.uom_id = u.id
                WHERE pom.production_order_id = ?
                ORDER BY p.code, u.id
            ");
            $stmt->execute([$poId]);
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get available stock and unit cost for each material
            foreach ($materials as &$mat) {
                // Available Stock per unit
                $stockStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(qty_in - qty_out), 0) as available_stock
                    FROM stock_ledger
                    WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND unit_id = ?
                ");
                $stockStmt->execute([$tenant_id, $mat['material_id'], $branchId, $mat['uom_id']]);
                $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
                $mat['available_stock'] = $stock['available_stock'];
                
                // Unit Cost (Last purchase cost)
                $costStmt = $pdo->prepare("
                    SELECT unit_cost
                    FROM stock_ledger
                    WHERE tenant_id = ? AND product_id = ? AND unit_id = ? AND qty_in > 0
                    ORDER BY transaction_date DESC
                    LIMIT 1
                ");
                $costStmt->execute([$tenant_id, $mat['material_id'], $mat['uom_id']]);
                $cost = $costStmt->fetch(PDO::FETCH_ASSOC);
                
                // Fallback to purchase price
                if (!$cost) {
                    $priceStmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
                    $priceStmt->execute([$mat['material_id']]);
                    $price = $priceStmt->fetch(PDO::FETCH_ASSOC);
                    $mat['unit_cost'] = $price['purchase_price'] ?? 0;
                } else {
                    $mat['unit_cost'] = $cost['unit_cost'];
                }
            }
            
            ob_end_clean();
            echo json_encode(['success' => true, 'data' => $materials]);
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Load All Materials (for Add Material dropdown)
    if ($action === 'all_materials') {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.code,
                p.name,
                u.uom_name
            FROM products p
            LEFT JOIN uom u ON p.default_unit_id = u.id
            WHERE p.tenant_id = ?
            AND p.inventory_account_id = 115
            AND p.is_active = 1
            ORDER BY p.name
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    // Load Material Details (for modal)
    if ($action === 'material_details' && isset($_GET['material_id']) && isset($_GET['branch_id'])) {
        $materialId = $_GET['material_id'];
        $branchId = $_GET['branch_id'];
        
        // Available Stock
        $stockStmt = $pdo->prepare("
            SELECT COALESCE(SUM(qty_in - qty_out), 0) as available_stock
            FROM stock_ledger
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ?
        ");
        $stockStmt->execute([$tenant_id, $materialId, $branchId]);
        $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
        
        // Unit Cost
        $costStmt = $pdo->prepare("
            SELECT unit_cost
            FROM stock_ledger
            WHERE tenant_id = ? AND product_id = ? AND qty_in > 0
            ORDER BY transaction_date DESC
            LIMIT 1
        ");
        $costStmt->execute([$tenant_id, $materialId]);
        $cost = $costStmt->fetch(PDO::FETCH_ASSOC);
        
        // UOM
        $uomStmt = $pdo->prepare("
            SELECT u.uom_name
            FROM products p
            LEFT JOIN uom u ON p.default_unit_id = u.id
            WHERE p.id = ?
        ");
        $uomStmt->execute([$materialId]);
        $uom = $uomStmt->fetch(PDO::FETCH_ASSOC);
        
        // Fallback
        if (!$cost) {
            $priceStmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
            $priceStmt->execute([$materialId]);
            $price = $priceStmt->fetch(PDO::FETCH_ASSOC);
            $unitCost = $price['purchase_price'] ?? 0;
        } else {
            $unitCost = $cost['unit_cost'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'available_stock' => $stock['available_stock'],
                'unit_cost' => $unitCost,
                'uom_name' => $uom['uom_name'] ?? 'N/A'
            ]
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// POST - Issue Materials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $_GET['action'] ?? '';
    
    // Add Material (New or Existing)
    if ($action === 'add_material') {
        $po_id = $data['po_id'] ?? null;
        $branch_id = $data['branch_id'] ?? null;
        $material_id = $data['material_id'] ?? null;
        $issue_qty = $data['issue_qty'] ?? null;
        $unit_cost = $data['unit_cost'] ?? null;
        $issue_date = $data['issue_date'] ?? null;
        $wip_product_id = $data['wip_product_id'] ?? null;
        
        if (!$po_id || !$branch_id || !$material_id || !$issue_qty || !$issue_date || !$wip_product_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Check stock availability
            $stockStmt = $pdo->prepare("
                SELECT COALESCE(SUM(qty_in - qty_out), 0) as available_stock
                FROM stock_ledger
                WHERE tenant_id = ? AND product_id = ? AND branch_id = ?
            ");
            $stockStmt->execute([$tenant_id, $material_id, $branch_id]);
            $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stock['available_stock'] < $issue_qty) {
                throw new Exception('Insufficient stock');
            }
            
            // Check if material already exists in production_order_materials
            $checkStmt = $pdo->prepare("
                SELECT id, issued_qty, required_qty
                FROM production_order_materials
                WHERE production_order_id = ? AND material_id = ?
            ");
            $checkStmt->execute([$po_id, $material_id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get UOM
            $uomStmt = $pdo->prepare("SELECT default_unit_id FROM products WHERE id = ?");
            $uomStmt->execute([$material_id]);
            $uom = $uomStmt->fetch(PDO::FETCH_ASSOC);
            $uom_id = $uom['default_unit_id'];
            
            if ($existing) {
                // Update issued_qty
                $newIssued = $existing['issued_qty'] + $issue_qty;
                $updateStmt = $pdo->prepare("
                    UPDATE production_order_materials
                    SET issued_qty = ?, status = 'Issued'
                    WHERE id = ?
                ");
                $updateStmt->execute([$newIssued, $existing['id']]);
            } else {
                // Insert as Adhoc
                $insertStmt = $pdo->prepare("
                    INSERT INTO production_order_materials
                    (tenant_id, production_order_id, material_id, required_qty, issued_qty, uom_id, status)
                    VALUES (?, ?, ?, 0, ?, ?, 'Adhoc')
                ");
                $insertStmt->execute([$tenant_id, $po_id, $material_id, $issue_qty, $uom_id]);
            }
            
            // Generate WIP Number
            $wipStmt = $pdo->prepare("SELECT wip_number FROM work_in_progress WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
            $wipStmt->execute([$tenant_id]);
            $wipResult = $wipStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($wipResult) {
                $lastNumber = (int)preg_replace('/[^0-9]/', '', $wipResult['wip_number']);
                $wipNumber = 'WIP-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $wipNumber = 'WIP-0001';
            }
            
            // Get machine_id from production order
            $poStmt = $pdo->prepare("SELECT machine_id FROM production_orders WHERE id = ?");
            $poStmt->execute([$po_id]);
            $poData = $poStmt->fetch(PDO::FETCH_ASSOC);
            $machine_id = $poData['machine_id'];
            
            // Insert WIP Header
            $headerStmt = $pdo->prepare("
                INSERT INTO work_in_progress 
                (wip_number, tenant_id, production_order_id, branch_id, machine_id, issue_date, total_items, total_cost, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
            ");
            $totalCost = $issue_qty * $unit_cost;
            $headerStmt->execute([
                $wipNumber,
                $tenant_id,
                $po_id,
                $branch_id,
                $machine_id,
                $issue_date,
                $totalCost,
                $user_id
            ]);
            $wipHeaderId = $pdo->lastInsertId();
            
            // Insert WIP Line
            $lineStmt = $pdo->prepare("
                INSERT INTO work_in_progress_items
                (tenant_id, work_in_progress_id, material_id, required_qty, issued_qty, available_qty, issue_qty, uom_id, unit_cost, total_cost)
                VALUES (?, ?, ?, 0, 0, ?, ?, ?, ?, ?)
            ");
            $lineStmt->execute([
                $tenant_id,
                $wipHeaderId,
                $material_id,
                $stock['available_stock'],
                $issue_qty,
                $uom_id,
                $unit_cost,
                $totalCost
            ]);
            
            // 1. Raw Material Issue (qty_out) - account_id 115
            $stockOutStmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, branch_id, product_id, account_id, reference_table, reference_id,
                 qty_out, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, ?, ?, 115, 'work_in_progress', ?, ?, ?, ?, 'WIP_ISSUE', ?, NOW())
            ");
            $stockOutStmt->execute([
                $tenant_id,
                $branch_id,
                $material_id,
                $wipHeaderId,
                $issue_qty,
                $unit_cost,
                $uom_id,
                $issue_date
            ]);
            
            // 2. WIP Inventory Receive (qty_in) - account_id 116 (same material)
            $stockInStmt = $pdo->prepare("
                INSERT INTO stock_ledger
                (tenant_id, branch_id, product_id, account_id, reference_table, reference_id,
                 qty_in, unit_cost, unit_id, transaction_type, transaction_date, created_at)
                VALUES (?, ?, ?, 116, 'work_in_progress', ?, ?, ?, ?, 'WIP_RECEIVE', ?, NOW())
            ");
            $stockInStmt->execute([
                $tenant_id,
                $branch_id,
                $material_id,
                $wipHeaderId,
                $issue_qty,
                $unit_cost,
                $uom_id,
                $issue_date
            ]);
            
            // 3. Accounting Ledger Entry: Dr. WIP Inventory, Cr. Raw Materials Inventory
            $glStmt = $pdo->prepare("
                INSERT INTO accounting_ledger
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at)
                VALUES (?, 'wip_issue', 'work_in_progress', ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Debit: WIP Inventory (account_id = 116)
            $glStmt->execute([
                $tenant_id,
                $wipHeaderId,
                116,
                $issue_date,
                'Material issued to WIP: ' . $wipNumber,
                $totalCost,
                0
            ]);
            
            // Credit: Raw Materials Inventory (account_id = 115)
            $glStmt->execute([
                $tenant_id,
                $wipHeaderId,
                115,
                $issue_date,
                'Material issued to WIP: ' . $wipNumber,
                0,
                $totalCost
            ]);
            
            // Update production order status
            $poUpdateStmt = $pdo->prepare("
                UPDATE production_orders
                SET status = 'In Progress', updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $poUpdateStmt->execute([$po_id, $tenant_id]);

            logActivity(
                $pdo, $tenant_id, $user_id, 'create', 'WIP Management', $wipHeaderId, $wipNumber,
                "Issued material #$material_id to WIP \"$wipNumber\": qty $issue_qty @ " . number_format($unit_cost, 2),
                null,
                ['wip_number' => $wipNumber, 'production_order_id' => $po_id, 'branch_id' => $branch_id, 'material_id' => $material_id, 'issue_qty' => $issue_qty, 'unit_cost' => $unit_cost]
            );

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Material added successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Update WIP
    if ($action === 'update_wip') {
        $wip_id = $data['wip_id'] ?? null;
        $issue_date = $data['issue_date'] ?? null;
        $materials = $data['materials'] ?? [];
        
        if (!$wip_id || !$issue_date) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get WIP details
            $wipStmt = $pdo->prepare("
                SELECT wip.branch_id, wip.production_order_id, po.product_id as wip_product_id
                FROM work_in_progress wip
                JOIN production_orders po ON wip.production_order_id = po.id
                WHERE wip.id = ?
            ");
            $wipStmt->execute([$wip_id]);
            $wipData = $wipStmt->fetch(PDO::FETCH_ASSOC);
            $branchId = $wipData['branch_id'];
            $wipProductId = $wipData['wip_product_id'];
            
            $totalCost = 0;
            $totalItems = 0;
            
            foreach ($materials as $mat) {
                if ($mat['issue_qty'] > 0) {
                    $totalItems++;
                    $totalCost += $mat['total_cost'];
                    
                    // Get old issue_qty and material details
                    $oldStmt = $pdo->prepare("SELECT issue_qty, unit_cost, material_id, uom_id FROM work_in_progress_items WHERE id = ?");
                    $oldStmt->execute([$mat['id']]);
                    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    $oldQty = $oldData['issue_qty'];
                    $qtyDiff = $mat['issue_qty'] - $oldQty;
                    
                    // Update WIP line
                    $updateStmt = $pdo->prepare("
                        UPDATE work_in_progress_items
                        SET issue_qty = ?, total_cost = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$mat['issue_qty'], $mat['total_cost'], $mat['id']]);
                    
                    // Update production_order_materials issued_qty per unit
                    if ($qtyDiff != 0) {
                        $pomUpdateStmt = $pdo->prepare("
                            UPDATE production_order_materials
                            SET issued_qty = issued_qty + ?
                            WHERE production_order_id = ? AND material_id = ? AND uom_id = ?
                        ");
                        $pomUpdateStmt->execute([$qtyDiff, $wipData['production_order_id'], $oldData['material_id'], $oldData['uom_id']]);
                    }
                    
                    // Update stock ledger if qty changed
                    if ($qtyDiff != 0) {
                        // Update raw material stock ledger entry
                        $updateRawStmt = $pdo->prepare("
                            UPDATE stock_ledger
                            SET qty_out = ?
                            WHERE reference_table = 'work_in_progress'
                            AND reference_id = ?
                            AND product_id = ?
                            AND unit_id = ?
                            AND account_id = 115
                            AND transaction_type = 'WIP_ISSUE'
                        ");
                        $updateRawStmt->execute([$mat['issue_qty'], $wip_id, $oldData['material_id'], $oldData['uom_id']]);
                        
                        // Update WIP inventory stock ledger entry (same material)
                        $updateWIPStmt = $pdo->prepare("
                            UPDATE stock_ledger
                            SET qty_in = ?
                            WHERE reference_table = 'work_in_progress'
                            AND reference_id = ?
                            AND product_id = ?
                            AND unit_id = ?
                            AND account_id = 116
                            AND transaction_type = 'WIP_RECEIVE'
                        ");
                        $updateWIPStmt->execute([$mat['issue_qty'], $wip_id, $oldData['material_id'], $oldData['uom_id']]);
                    }
                }
            }
            
            $wipNumStmt = $pdo->prepare("SELECT wip_number, issue_date as old_issue_date, total_cost as old_total_cost FROM work_in_progress WHERE id = ? AND tenant_id = ?");
            $wipNumStmt->execute([$wip_id, $tenant_id]);
            $wipMeta = $wipNumStmt->fetch(PDO::FETCH_ASSOC);

            $headerStmt = $pdo->prepare("
                UPDATE work_in_progress
                SET issue_date = ?, total_items = ?, total_cost = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $headerStmt->execute([$issue_date, $totalItems, $totalCost, $wip_id, $tenant_id]);

            if ($wipMeta) {
                $changeDescriptions = buildChangeDescription(
                    ['issue_date' => $wipMeta['old_issue_date'], 'total_cost' => $wipMeta['old_total_cost']],
                    ['issue_date' => $issue_date, 'total_cost' => $totalCost],
                    ['issue_date' => 'Issue Date', 'total_cost' => 'Total Cost']
                );
                $editDescription = !empty($changeDescriptions)
                    ? "Updated WIP \"{$wipMeta['wip_number']}\": " . implode('; ', $changeDescriptions)
                    : "Updated WIP \"{$wipMeta['wip_number']}\" - no tracked field changes";
                logActivity($pdo, $tenant_id, $user_id, 'update', 'WIP Management', $wip_id, $wipMeta['wip_number'], $editDescription,
                    ['issue_date' => $wipMeta['old_issue_date'], 'total_cost' => $wipMeta['old_total_cost']],
                    ['issue_date' => $issue_date, 'total_cost' => $totalCost]
                );
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'WIP updated successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Delete WIP Item
    if ($action === 'delete_wip_item') {
        $item_id = $data['item_id'] ?? null;
        $wip_id = $data['wip_id'] ?? null;
        
        if (!$item_id || !$wip_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get item details
            $itemStmt = $pdo->prepare("SELECT * FROM work_in_progress_items WHERE id = ?");
            $itemStmt->execute([$item_id]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get WIP details
            $wipStmt = $pdo->prepare("
                SELECT wip.branch_id, wip.production_order_id, po.product_id as wip_product_id, wip.issue_date
                FROM work_in_progress wip
                JOIN production_orders po ON wip.production_order_id = po.id
                WHERE wip.id = ?
            ");
            $wipStmt->execute([$wip_id]);
            $wipData = $wipStmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete stock ledger entries for raw material
            $deleteRawStmt = $pdo->prepare("
                DELETE FROM stock_ledger
                WHERE reference_table = 'work_in_progress'
                AND reference_id = ?
                AND product_id = ?
                AND unit_id = ?
                AND account_id = 115
            ");
            $deleteRawStmt->execute([$wip_id, $item['material_id'], $item['uom_id']]);
            
            // Delete stock ledger entries for WIP inventory (same material)
            $deleteWIPStmt = $pdo->prepare("
                DELETE FROM stock_ledger
                WHERE reference_table = 'work_in_progress'
                AND reference_id = ?
                AND product_id = ?
                AND unit_id = ?
                AND account_id = 116
            ");
            $deleteWIPStmt->execute([$wip_id, $item['material_id'], $item['uom_id']]);
            
            // Update production_order_materials - subtract issued_qty per unit
            $pomStmt = $pdo->prepare("
                UPDATE production_order_materials
                SET issued_qty = GREATEST(0, issued_qty - ?)
                WHERE production_order_id = ? AND material_id = ? AND uom_id = ?
            ");
            $pomStmt->execute([$item['issue_qty'], $wipData['production_order_id'], $item['material_id'], $item['uom_id']]);
            
            // Delete item
            $deleteStmt = $pdo->prepare("DELETE FROM work_in_progress_items WHERE id = ?");
            $deleteStmt->execute([$item_id]);
            
            // Update WIP header totals
            $totalStmt = $pdo->prepare("
                SELECT COUNT(*) as total_items, COALESCE(SUM(total_cost), 0) as total_cost
                FROM work_in_progress_items
                WHERE work_in_progress_id = ?
            ");
            $totalStmt->execute([$wip_id]);
            $totals = $totalStmt->fetch(PDO::FETCH_ASSOC);
            
            $updateHeaderStmt = $pdo->prepare("
                UPDATE work_in_progress
                SET total_items = ?, total_cost = ?
                WHERE id = ?
            ");
            $updateHeaderStmt->execute([$totals['total_items'], $totals['total_cost'], $wip_id]);

            $wipNumStmt = $pdo->prepare("SELECT wip_number FROM work_in_progress WHERE id = ?");
            $wipNumStmt->execute([$wip_id]);
            $wipNum = $wipNumStmt->fetchColumn();
            if ($item) {
                logActivity(
                    $pdo, $tenant_id, $user_id, 'delete', 'WIP Management', $wip_id, $wipNum,
                    "Removed material #{$item['material_id']} (qty {$item['issue_qty']}) from WIP \"$wipNum\"",
                    $item, null
                );
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Delete WIP
    if ($action === 'delete_wip') {
        $wip_id = $data['wip_id'] ?? null;
        
        if (!$wip_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing WIP ID']);
            exit;
        }
        
        try {
            // Grab full WIP header before deletion, for the Activity Log
            $wipHeaderStmt = $pdo->prepare("SELECT * FROM work_in_progress WHERE id = ? AND tenant_id = ?");
            $wipHeaderStmt->execute([$wip_id, $tenant_id]);
            $wipHeaderRow = $wipHeaderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$wipHeaderRow) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'WIP not found']);
                exit;
            }

            $pdo->beginTransaction();

            // Get WIP details
            $wipStmt = $pdo->prepare("
                SELECT wip.branch_id, wip.production_order_id, po.product_id as wip_product_id, wip.issue_date
                FROM work_in_progress wip
                JOIN production_orders po ON wip.production_order_id = po.id
                WHERE wip.id = ?
            ");
            $wipStmt->execute([$wip_id]);
            $wipData = $wipStmt->fetch(PDO::FETCH_ASSOC);

            // Get all items
            $itemsStmt = $pdo->prepare("SELECT * FROM work_in_progress_items WHERE work_in_progress_id = ?");
            $itemsStmt->execute([$wip_id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                // Delete stock ledger entries for raw material per unit
                $deleteRawStmt = $pdo->prepare("
                    DELETE FROM stock_ledger
                    WHERE reference_table = 'work_in_progress'
                    AND reference_id = ?
                    AND product_id = ?
                    AND unit_id = ?
                    AND account_id = 115
                ");
                $deleteRawStmt->execute([$wip_id, $item['material_id'], $item['uom_id']]);
                
                // Delete stock ledger entries for WIP inventory per unit (same material)
                $deleteWIPStmt = $pdo->prepare("
                    DELETE FROM stock_ledger
                    WHERE reference_table = 'work_in_progress'
                    AND reference_id = ?
                    AND product_id = ?
                    AND unit_id = ?
                    AND account_id = 116
                ");
                $deleteWIPStmt->execute([$wip_id, $item['material_id'], $item['uom_id']]);
                
                // Update production_order_materials per unit with GREATEST to prevent negative
                $pomStmt = $pdo->prepare("
                    UPDATE production_order_materials
                    SET issued_qty = GREATEST(0, issued_qty - ?)
                    WHERE production_order_id = ? AND material_id = ? AND uom_id = ?
                ");
                $pomStmt->execute([$item['issue_qty'], $wipData['production_order_id'], $item['material_id'], $item['uom_id']]);
            }
            
            // Delete accounting_ledger entries for this WIP
            $deleteGLStmt = $pdo->prepare("
                DELETE FROM accounting_ledger
                WHERE reference_table = 'work_in_progress'
                AND reference_id = ?
                AND tenant_id = ?
            ");
            $deleteGLStmt->execute([$wip_id, $tenant_id]);
            
            // Delete all items
            $deleteItemsStmt = $pdo->prepare("DELETE FROM work_in_progress_items WHERE work_in_progress_id = ?");
            $deleteItemsStmt->execute([$wip_id]);
            
            // Delete WIP header
            $deleteWIPHeaderStmt = $pdo->prepare("DELETE FROM work_in_progress WHERE id = ? AND tenant_id = ?");
            $deleteWIPHeaderStmt->execute([$wip_id, $tenant_id]);
            
            // Revert production order status to 'Planned' if no more WIPs exist
            $remainingStmt = $pdo->prepare("SELECT COUNT(*) FROM work_in_progress WHERE production_order_id = ? AND tenant_id = ?");
            $remainingStmt->execute([$wipData['production_order_id'], $tenant_id]);
            if ($remainingStmt->fetchColumn() == 0) {
                $revertStmt = $pdo->prepare("UPDATE production_orders SET status = 'Planned', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
                $revertStmt->execute([$wipData['production_order_id'], $tenant_id]);
            }

            logActivity(
                $pdo, $tenant_id, $user_id, 'delete', 'WIP Management', $wip_id, $wipHeaderRow['wip_number'],
                "Deleted WIP \"{$wipHeaderRow['wip_number']}\", total cost " . number_format($wipHeaderRow['total_cost'], 2),
                $wipHeaderRow, null
            );

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'WIP deleted successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Bulk Issue Materials (existing functionality)
    $po_id = $data['po_id'] ?? null;
    $branch_id = $data['branch_id'] ?? null;
    $machine_id = $data['machine_id'] ?? null;
    $issue_date = $data['issue_date'] ?? null;
    $materials = $data['materials'] ?? [];
    $wip_product_id = $data['wip_product_id'] ?? null;
    
    if (!$po_id || !$branch_id || !$issue_date || empty($materials) || !$wip_product_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Validate stock availability
        $totalCost = 0;
        foreach ($materials as $mat) {
            $stockStmt = $pdo->prepare("
                SELECT COALESCE(SUM(qty_in - qty_out), 0) as available_stock
                FROM stock_ledger
                WHERE tenant_id = ? AND product_id = ? AND branch_id = ?
            ");
            $stockStmt->execute([$tenant_id, $mat['material_id'], $branch_id]);
            $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stock['available_stock'] < $mat['issue_qty']) {
                throw new Exception('Insufficient stock for material ID: ' . $mat['material_id']);
            }
            
            $totalCost += $mat['issue_qty'] * $mat['unit_cost'];
        }
        
        // Insert WIP Issue Header
        $headerStmt = $pdo->prepare("
            INSERT INTO work_in_progress 
            (wip_number, tenant_id, production_order_id, branch_id, machine_id, issue_date, total_items, total_cost, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Generate WIP Number
        $wipStmt = $pdo->prepare("SELECT wip_number FROM work_in_progress WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $wipStmt->execute([$tenant_id]);
        $wipResult = $wipStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wipResult) {
            $lastNumber = (int)preg_replace('/[^0-9]/', '', $wipResult['wip_number']);
            $wipNumber = 'WIP-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $wipNumber = 'WIP-0001';
        }
        
        $headerStmt->execute([
            $wipNumber,
            $tenant_id,
            $po_id,
            $branch_id,
            $machine_id,
            $issue_date,
            count($materials),
            $totalCost,
            $user_id
        ]);
        $wipHeaderId = $pdo->lastInsertId();
        
        // Insert stock ledger entries (2 entries per material)
        $stockOutStmt = $pdo->prepare("
            INSERT INTO stock_ledger 
            (tenant_id, branch_id, product_id, account_id, reference_table, reference_id, 
             qty_out, unit_cost, unit_id, transaction_type, transaction_date, created_at)
            VALUES (?, ?, ?, 115, 'work_in_progress', ?, ?, ?, ?, 'WIP_ISSUE', ?, NOW())
        ");
        
        $stockInStmt = $pdo->prepare("
            INSERT INTO stock_ledger 
            (tenant_id, branch_id, product_id, account_id, reference_table, reference_id, 
             qty_in, unit_cost, unit_id, transaction_type, transaction_date, created_at)
            VALUES (?, ?, ?, 116, 'work_in_progress', ?, ?, ?, ?, 'WIP_RECEIVE', ?, NOW())
        ");
        
        $lineStmt = $pdo->prepare("
            INSERT INTO work_in_progress_items
            (tenant_id, work_in_progress_id, material_id, required_qty, issued_qty, available_qty, issue_qty, uom_id, unit_cost, total_cost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $updateStmt = $pdo->prepare("
            UPDATE production_order_materials 
            SET issued_qty = issued_qty + ?, status = 'Issued'
            WHERE id = ?
        ");
        
        foreach ($materials as $mat) {
            // 1. Raw Material Issue (qty_out)
            $stockOutStmt->execute([
                $tenant_id,
                $branch_id,
                $mat['material_id'],
                $wipHeaderId,
                $mat['issue_qty'],
                $mat['unit_cost'],
                $mat['uom_id'],
                $issue_date
            ]);
            
            // 2. WIP Inventory Receive (qty_in) - account_id 116 (same material)
            $stockInStmt->execute([
                $tenant_id,
                $branch_id,
                $mat['material_id'],
                $wipHeaderId,
                $mat['issue_qty'],
                $mat['unit_cost'],
                $mat['uom_id'],
                $issue_date
            ]);
            
            // 3. Accounting Ledger Entry: Dr. WIP Inventory, Cr. Raw Materials Inventory
            $glStmt = $pdo->prepare("
                INSERT INTO accounting_ledger
                (tenant_id, transaction_type, reference_table, reference_id, account_id, date, description, debit, credit, created_at)
                VALUES (?, 'wip_issue', 'work_in_progress', ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Debit: WIP Inventory (account_id = 116)
            $glStmt->execute([
                $tenant_id,
                $wipHeaderId,
                116,
                $issue_date,
                'Material issued to WIP: ' . $wipNumber,
                $mat['issue_qty'] * $mat['unit_cost'],
                0
            ]);
            
            // Credit: Raw Materials Inventory (account_id = 115)
            $glStmt->execute([
                $tenant_id,
                $wipHeaderId,
                115,
                $issue_date,
                'Material issued to WIP: ' . $wipNumber,
                0,
                $mat['issue_qty'] * $mat['unit_cost']
            ]);
            
            $lineStmt->execute([
                $tenant_id,
                $wipHeaderId,
                $mat['material_id'],
                $mat['required_qty'] ?? 0,
                $mat['issued_qty'] ?? 0,
                $mat['available_qty'] ?? 0,
                $mat['issue_qty'],
                $mat['uom_id'],
                $mat['unit_cost'],
                $mat['issue_qty'] * $mat['unit_cost']
            ]);
            
            if (isset($mat['pom_id'])) {
                $updateStmt->execute([$mat['issue_qty'], $mat['pom_id']]);
            }
        }
        
        // Update production order status to In Progress
        $poStmt = $pdo->prepare("
            UPDATE production_orders 
            SET status = 'In Progress', updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $poStmt->execute([$po_id, $tenant_id]);

        logActivity(
            $pdo, $tenant_id, $user_id, 'create', 'WIP Management', $wipHeaderId, $wipNumber,
            "Issued " . count($materials) . " material(s) to WIP \"$wipNumber\", total cost " . number_format($totalCost, 2),
            null,
            ['wip_number' => $wipNumber, 'production_order_id' => $po_id, 'branch_id' => $branch_id, 'machine_id' => $machine_id, 'total_cost' => $totalCost]
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Materials issued successfully', 'wip_id' => $wipHeaderId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
