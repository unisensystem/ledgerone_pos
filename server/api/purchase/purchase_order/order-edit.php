<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $invoice_id = $_GET['id'] ?? null;
    
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
                b.branch_name,
                b.branch_type,
                b.branch_code,
                pb.branch_name as parent_branch_name,
                c.symbol as currency_symbol,
                c.code as currency_code,
                c.name as currency_name,
                co.company_name,
                co.legal_name,
                co.email as company_email,
                co.phone as company_phone,
                co.address as company_address,
                co.city as company_city,
                co.state as company_state,
                co.zipcode as company_zipcode,
                co.logo_url,
                p.project_code,
                p.project_name,
                pc.city_name as project_city_name
            FROM purchase_order pi
            LEFT JOIN suppliers s ON pi.supplier_id = s.id
            LEFT JOIN branches b ON pi.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
            LEFT JOIN companies co ON pi.company_id = co.id
            LEFT JOIN projects p ON pi.project_id = p.id
            LEFT JOIN project_cities pc ON p.project_city_id = pc.id
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
            FROM purchase_order_items pii
            LEFT JOIN products p ON pii.product_id = p.id
            LEFT JOIN uom u ON pii.uom_id = u.id
            WHERE pii.purchase_invoice_id = ? AND pii.tenant_id = ?
            ORDER BY pii.id
        ");
        $itemStmt->execute([$invoice_id, $tenant_id]);
        $rawItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group items: merge all entries with same product_id and UOM set
        $productGroups = [];
        foreach ($rawItems as $item) {
            $productGroups[$item['product_id']][] = $item;
        }
        
        $groupedItems = [];
        $itemIndex = 0;
        
        foreach ($productGroups as $productId => $items) {
            // Collect all UOMs and sum quantities
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
                
                // Sum amounts only from entries with amounts
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
            'invoice' => $invoice,
            'items' => $items
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoice_id = $input['invoice_id'] ?? null;
    
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update purchase order
        $stmt = $pdo->prepare("
            UPDATE purchase_order SET
                company_id = ?, currency_id = ?, purchase_date = ?, supplier_id = ?, branch_id = ?,
                project_id = ?, previous_balance = ?, total_bill = ?, total_discount_percent = ?,
                total_discount_amount = ?, total_gst_percent = ?, total_gst_amount = ?,
                shipping_fees = ?, net_amount = ?, supplier_invoice_no = ?, 
                supplier_invoice_date = ?, bilty_no = ?, transport_name = ?, remarks = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $input['companyId'],
            $input['currencyId'],
            $input['purchaseDate'],
            $input['supplierId'],
            $input['branchId'],
            $input['projectId'] ?? null,
            extractBalanceAmount($input['previousBalance'] ?? '0.00'),
            $input['totalBill'],
            $input['totalDiscountPercent'] ?? 0.00,
            $input['totalDiscountAmount'] ?? 0.00,
            $input['totalGSTPercent'] ?? 0.00,
            $input['totalGSTAmount'] ?? 0.00,
            $input['shippingFees'] ?? 0.00,
            $input['netAmount'],
            $input['supplierInvoiceNo'],
            $input['supplierInvoiceDate'],
            $input['biltyNo'],
            $input['transportName'],
            $input['remarks'] ?? null,
            $user_id,
            $invoice_id,
            $tenant_id
        ]);
        
        // Delete existing items and related records
        $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        
        // Insert new items with dynamic UOM system
        $item_stmt = $pdo->prepare("
            INSERT INTO purchase_order_items (
                tenant_id, purchase_invoice_id, product_id, uom_id,
                quantity, purchase_price, gross_amount, discount_percent,
                discount_amount, trade_offer_percent, trade_offer_amount,
                gst_percent, gst_amount, foc_quantity, net_amount,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($input['items'] as $item) {
            // Each item can have multiple unit entries
            $isFirstEntry = true;
            foreach ($item['unitEntries'] as $unitEntry) {
                $item_stmt->execute([
                    $tenant_id,
                    $invoice_id,
                    $item['productId'],
                    $unitEntry['uomId'],
                    $unitEntry['quantity'],
                    $item['purchasePrice'],
                    $isFirstEntry ? $item['grossAmount'] : 0,
                    $item['discountPercent'] ?? 0.00,
                    $isFirstEntry ? $item['discountAmount'] : 0,
                    $item['tradeOfferPercent'] ?? 0.00,
                    $isFirstEntry ? $item['tradeOfferAmount'] : 0,
                    $item['gstPercent'] ?? 0.00,
                    $isFirstEntry ? $item['gstAmount'] : 0,
                    $item['focQty'] ?? 0,
                    $isFirstEntry ? $item['netAmount'] : 0,
                    $user_id,
                    $user_id
                ]);
                $isFirstEntry = false;
            }
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Purchase order updated successfully']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to extract numeric amount from balance string
function extractBalanceAmount($balanceString) {
    if (empty($balanceString)) return 0.00;
    $amount = preg_replace('/^(Dr|Cr)\s*/', '', $balanceString);
    return floatval($amount);
}