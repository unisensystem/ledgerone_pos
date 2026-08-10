<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// GET - Fetch finished goods or raw materials
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'finished_goods') {
        $stmt = $pdo->prepare("SELECT id, code, name FROM products WHERE tenant_id = ? AND inventory_account_id = 117 AND is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'next_code') {
        $stmt = $pdo->prepare("SELECT bom_code FROM bill_of_materials WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastCode = $stmt->fetchColumn();
        
        if ($lastCode && preg_match('/BOM-(\d+)/', $lastCode, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = 1;
        }
        
        $newCode = 'BOM-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        echo json_encode(['success' => true, 'code' => $newCode]);
        exit;
    }
    
    if ($action === 'raw_materials') {
        $stmt = $pdo->prepare("SELECT id, code, name, uom_type, default_unit_id, uom_group_id, product_conversion_factor FROM products WHERE tenant_id = ? AND inventory_account_id IN (115, 117) AND is_active = 1");
        $stmt->execute([$tenant_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }
    
    if ($action === 'product_uom' && isset($_GET['product_id'])) {
        $productId = $_GET['product_id'];
        $stmt = $pdo->prepare("SELECT uom_type, default_unit_id, uom_group_id, product_conversion_factor FROM products WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$productId, $tenant_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        $units = [];
        
        // Check for 'single' or 'unit' (both mean single unit)
        if (($product['uom_type'] === 'single' || $product['uom_type'] === 'unit') && $product['default_unit_id']) {
            $stmt = $pdo->prepare("SELECT id, uom_name, conversion_factor, is_base_unit, unit_scope FROM uom WHERE id = ?");
            $stmt->execute([$product['default_unit_id']]);
            $unit = $stmt->fetch();
            if ($unit) {
                if ($unit['unit_scope'] === 'per_product' && $product['product_conversion_factor']) {
                    $unit['conversion_factor'] = $product['product_conversion_factor'];
                }
                $units[] = $unit;
            }
        } else if ($product['uom_type'] === 'group' && $product['uom_group_id']) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.uom_name, u.conversion_factor, u.is_base_unit, u.unit_scope
                FROM uom_group_units ugu
                JOIN uom u ON ugu.uom_id = u.id
                WHERE ugu.uom_group_id = ?
                ORDER BY u.is_base_unit DESC, u.id ASC
            ");
            $stmt->execute([$product['uom_group_id']]);
            $units = $stmt->fetchAll();
            
            foreach ($units as &$unit) {
                if ($unit['unit_scope'] === 'per_product' && $product['product_conversion_factor']) {
                    $unit['conversion_factor'] = $product['product_conversion_factor'];
                }
            }
        }
        
        echo json_encode(['success' => true, 'uom_type' => $product['uom_type'], 'units' => $units]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// POST - Save BOM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $bom_code = $data['bom_code'] ?? null;
    $finished_good_id = $data['finished_good_id'] ?? null;
    $version = $data['version'] ?? '1.0';
    $is_active = $data['is_active'] ?? 1;
    $bom_base_qty = $data['bom_base_qty'] ?? 1;
    $batch_locked = $data['batch_locked'] ?? 0;
    $remarks = $data['remarks'] ?? null;
    $materials = $data['materials'] ?? [];
    
    if (!$bom_code || !$finished_good_id || empty($materials)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert BOM header
        $stmt = $pdo->prepare("INSERT INTO bill_of_materials (tenant_id, bom_code, finished_good_id, version, is_active, bom_base_qty, batch_locked, remarks, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$tenant_id, $bom_code, $finished_good_id, $version, $is_active, $bom_base_qty, $batch_locked, $remarks, $user_id]);
        $bom_id = $pdo->lastInsertId();
        
        // Insert BOM materials
        $stmt = $pdo->prepare("INSERT INTO bom_materials (bom_id, raw_material_id, quantity, unit_id) VALUES (?, ?, ?, ?)");
        foreach ($materials as $material) {
            if ($material['quantity'] > 0) {
                $stmt->execute([$bom_id, $material['raw_material_id'], $material['quantity'], $material['unit_id']]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'BOM saved successfully', 'bom_id' => $bom_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit;