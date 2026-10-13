<?php
// Suppress any output before JSON
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

// Clear any output buffer
ob_end_clean();

$user_id = $_SESSION['user_id'] ?? 1;
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'products') {
        $stmt = $pdo->prepare("SELECT id, code, name FROM products WHERE tenant_id = ? AND inventory_account_id = 117 AND is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'boms' && isset($_GET['product_id'])) {
        $bomType = $_GET['bom_type'] ?? '';
        $sql = "SELECT id, bom_code, version, bom_type FROM bill_of_materials WHERE tenant_id = ? AND finished_good_id = ? AND is_active = 1";
        $params = [$tenant_id, $_GET['product_id']];
        if ($bomType !== '' && $bomType !== 'all') {
            $sql .= " AND bom_type = ?";
            $params[] = $bomType;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'branches') {
        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.branch_name,
                b.branch_type,
                pb.branch_name AS parent_branch_name
            FROM user_branches ub
            JOIN branches b  ON ub.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            WHERE ub.tenant_id = ?
              AND ub.user_id   = ?
              AND b.is_active  = 1
            ORDER BY pb.branch_name, b.branch_name
        ");
        $stmt->execute([$tenant_id, $user_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($action === 'machines' && isset($_GET['branch_id'])) {
        $stmt = $pdo->prepare("SELECT id, code, machine_name FROM machines WHERE tenant_id = ? AND branch_id = ? AND is_active = 1");
        $stmt->execute([$tenant_id, $_GET['branch_id']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'next_order_no') {
        $stmt = $pdo->prepare("SELECT order_no FROM production_orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastNo = $stmt->fetchColumn();
        
        if ($lastNo && preg_match('/PO-(\d+)/', $lastNo, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        
        $newNo = 'PO-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        echo json_encode(['success' => true, 'order_no' => $newNo]);
        exit;
    }
    
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT po.id, po.order_no, p.name as product_name, po.status
            FROM production_orders po
            JOIN products p ON po.product_id = p.id
            WHERE po.tenant_id = ?
            ORDER BY po.id DESC
        ");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }
    
    if ($action === 'calculate_materials' && isset($_GET['bom_id']) && isset($_GET['order_qty']) && isset($_GET['branch_id'])) {
        $bomId = $_GET['bom_id'];
        $orderQty = $_GET['order_qty'];
        $branchId = $_GET['branch_id'];
        
        // Get BOM base quantity
        $bomStmt = $pdo->prepare("SELECT bom_base_qty FROM bill_of_materials WHERE id = ?");
        $bomStmt->execute([$bomId]);
        $bomData = $bomStmt->fetch();
        $bomBaseQty = $bomData['bom_base_qty'] ?? 1;
        
        // Get BOM materials with product UOM info
        $stmt = $pdo->prepare("
            SELECT 
                bm.raw_material_id,
                bm.quantity as bom_qty,
                bm.unit_id,
                p.code as material_code,
                p.name as material_name,
                p.uom_type,
                p.default_unit_id,
                p.uom_group_id,
                p.product_conversion_factor,
                u.uom_name,
                u.conversion_factor,
                u.is_base_unit,
                u.unit_scope
            FROM bom_materials bm
            JOIN products p ON bm.raw_material_id = p.id
            JOIN uom u ON bm.unit_id = u.id
            WHERE bm.bom_id = ?
        ");
        $stmt->execute([$bomId]);
        $materials = $stmt->fetchAll();
        
        // Group materials by product
        $groupedMaterials = [];
        foreach ($materials as $mat) {
            $productId = $mat['raw_material_id'];
            
            if (!isset($groupedMaterials[$productId])) {
                $groupedMaterials[$productId] = [
                    'material_id' => $productId,
                    'material_code' => $mat['material_code'],
                    'material_name' => $mat['material_name'],
                    'uom_type' => $mat['uom_type'],
                    'units' => []
                ];
            }
            
            $requiredQty = $mat['bom_qty'] * ($orderQty / $bomBaseQty);
            
            // Get available stock from stock_ledger for this specific unit
            $stockStmt = $pdo->prepare("
                SELECT COALESCE(SUM(qty_in - qty_out), 0) as available_stock
                FROM stock_ledger
                WHERE tenant_id = ? 
                AND product_id = ? 
                AND branch_id = ?
                AND unit_id = ?
            ");
            $stockStmt->execute([$tenant_id, $productId, $branchId, $mat['unit_id']]);
            $stock = $stockStmt->fetch();
            
            $groupedMaterials[$productId]['units'][] = [
                'uom_id' => $mat['unit_id'],
                'uom_name' => $mat['uom_name'],
                'required_qty' => $requiredQty,
                'available_stock' => $stock['available_stock'],
                'conversion_factor' => $mat['unit_scope'] === 'per_product' ? $mat['product_conversion_factor'] : $mat['conversion_factor'],
                'is_base_unit' => $mat['is_base_unit']
            ];
        }
        
        // Convert to array
        $result = array_values($groupedMaterials);
        
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// POST - Create Production Order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $order_no = $data['order_no'] ?? null;
    $product_id = $data['product_id'] ?? null;
    $bom_id = $data['bom_id'] ?? null;
    $bom_version = $data['bom_version'] ?? null;
    $branch_id = $data['branch_id'] ?? null;
    $machine_id = $data['machine_id'] ?? null;
    $order_qty = $data['order_qty'] ?? null;
    $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
    $end_date = !empty($data['end_date']) ? $data['end_date'] : null;
    $materials = $data['materials'] ?? [];
    
    if (!$order_no || !$product_id || !$bom_id || !$branch_id || !$order_qty || empty($materials)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    if ($order_qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order quantity must be greater than 0']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert production order
        $stmt = $pdo->prepare("INSERT INTO production_orders (tenant_id, order_no, product_id, bom_id, bom_version, branch_id, machine_id, order_qty, status, start_date, end_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Planned', ?, ?, ?, NOW())");
        $stmt->execute([$tenant_id, $order_no, $product_id, $bom_id, $bom_version, $branch_id, $machine_id, $order_qty, $start_date, $end_date, $user_id]);
        $po_id = $pdo->lastInsertId();
        
        // Insert materials
        $stmt = $pdo->prepare("INSERT INTO production_order_materials (tenant_id, production_order_id, material_id, required_qty, uom_id, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        foreach ($materials as $mat) {
            $stmt->execute([$tenant_id, $po_id, $mat['material_id'], $mat['required_qty'], $mat['uom_id']]);
        }
        
        logActivity(
            $pdo, $tenant_id, $user_id, 'create', 'Production Order', $po_id, $order_no,
            "Created production order \"$order_no\", qty $order_qty",
            null,
            ['order_no' => $order_no, 'product_id' => $product_id, 'bom_id' => $bom_id, 'branch_id' => $branch_id, 'machine_id' => $machine_id, 'order_qty' => $order_qty, 'status' => 'Planned']
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Production Order created successfully', 'po_id' => $po_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
