<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Get purchase order data with branch details
    $stmt = $pdo->prepare("
        SELECT 
            po.*,
            s.supplier_code,
            s.supplier_name,
            b.branch_name,
            b.branch_type,
            b.branch_code,
            pb.branch_name as parent_branch_name
        FROM purchase_order po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN branches b ON po.branch_id = b.id
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        WHERE po.id = ? AND po.tenant_id = ?
    ");
    $stmt->execute([$order_id, $tenant_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    // Get purchase order items grouped by product
    $itemStmt = $pdo->prepare("
        SELECT 
            poi.*,
            p.name as product_name,
            p.code as product_code,
            p.uom_type,
            p.uom_group_id,
            p.default_unit_id,
            u.uom_name
        FROM purchase_order_items poi
        LEFT JOIN products p ON poi.product_id = p.id
        LEFT JOIN uom u ON poi.uom_id = u.id
        WHERE poi.purchase_invoice_id = ? AND poi.tenant_id = ?
        ORDER BY poi.id
    ");
    $itemStmt->execute([$order_id, $tenant_id]);
    $rawItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group items by product_id
    $productGroups = [];
    foreach ($rawItems as $item) {
        $productGroups[$item['product_id']][] = $item;
    }
    
    $groupedItems = [];
    $itemIndex = 0;
    
    foreach ($productGroups as $productId => $items) {
        $uomMap = [];
        $firstItem = null;
        $totalGross = 0;
        $totalDiscount = 0;
        $totalTradeOffer = 0;
        $totalGst = 0;
        $totalFoc = 0;
        $totalNet = 0;
        
        foreach ($items as $item) {
            if (!$firstItem) $firstItem = $item;
            
            $uomId = $item['uom_id'];
            if (!isset($uomMap[$uomId])) {
                $uomMap[$uomId] = [
                    'uom_id' => $uomId,
                    'uom_name' => $item['uom_name'],
                    'quantity' => 0
                ];
            }
            $uomMap[$uomId]['quantity'] += floatval($item['quantity']);
            
            if (floatval($item['gross_amount']) > 0) {
                $totalGross += floatval($item['gross_amount']);
                $totalDiscount += floatval($item['discount_amount']);
                $totalTradeOffer += floatval($item['trade_offer_amount']);
                $totalGst += floatval($item['gst_amount']);
                $totalFoc += floatval($item['foc_quantity']);
                $totalNet += floatval($item['net_amount']);
            }
        }
        
        $itemIndex++;
        $groupedItems[$itemIndex] = [
            'product_id' => $productId,
            'product_name' => $firstItem['product_name'],
            'product_code' => $firstItem['product_code'],
            'uom_type' => $firstItem['uom_type'],
            'uom_group_id' => $firstItem['uom_group_id'],
            'default_unit_id' => $firstItem['default_unit_id'],
            'purchase_price' => $firstItem['purchase_price'],
            'gross_amount' => $totalGross,
            'discount_percent' => $firstItem['discount_percent'],
            'discount_amount' => $totalDiscount,
            'trade_offer_percent' => $firstItem['trade_offer_percent'],
            'trade_offer_amount' => $totalTradeOffer,
            'gst_percent' => $firstItem['gst_percent'],
            'gst_amount' => $totalGst,
            'foc_quantity' => $totalFoc,
            'net_amount' => $totalNet,
            'unit_entries' => array_values($uomMap)
        ];
    }
    
    $items = array_values($groupedItems);
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
