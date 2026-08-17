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

$invoice_id = $_GET['invoice_id'] ?? null;

if (!$invoice_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
    exit;
}

try {
    // Get invoice data
    $stmt = $pdo->prepare("
        SELECT 
            pi.*,
            s.supplier_name,
            s.supplier_code,
            s.current_balance,
            b.branch_name,
            b.branch_code,
            b.branch_type,
            pb.branch_name as parent_branch_name,
            c.symbol as currency_symbol,
            c.code as currency_code,
            c.name as currency_name,
            pi.company_id
        FROM purchase_invoice pi
        LEFT JOIN suppliers s ON pi.supplier_id = s.id
        LEFT JOIN branches b ON pi.branch_id = b.id
        LEFT JOIN branches pb ON b.parent_branch_id = pb.id
        LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
        WHERE pi.id = ? AND pi.tenant_id = ?
    ");
    $stmt->execute([$invoice_id, $tenant_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Get invoice items grouped by product
    $itemStmt = $pdo->prepare("
        SELECT 
            pii.*,
            p.name as product_name,
            p.code as product_code,
            p.uom_type,
            p.uom_group_id,
            p.default_unit_id,
            u.uom_name
        FROM purchase_invoice_items pii
        LEFT JOIN products p ON pii.product_id = p.id
        LEFT JOIN uom u ON pii.uom_id = u.id
        WHERE pii.purchase_invoice_id = ? AND pii.tenant_id = ?
        ORDER BY pii.id
    ");
    $itemStmt->execute([$invoice_id, $tenant_id]);
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
        $totalTax = 0;
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
                $totalTax += floatval($item['tax_amount']);
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
            'tax_percent' => $firstItem['tax_percent'],
            'tax_amount' => $totalTax,
            'foc_quantity' => $totalFoc,
            'net_amount' => $totalNet,
            'unit_entries' => array_values($uomMap)
        ];
    }
    
    $items = array_values($groupedItems);
    
    echo json_encode([
        'success' => true,
        'invoice' => $invoice,
        'items' => $items,
        'shipping_fees' => floatval($invoice['shipping_fees'] ?? 0),
        'shipping_fees_type' => $invoice['shipping_fees_type'] ?? 'add'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
