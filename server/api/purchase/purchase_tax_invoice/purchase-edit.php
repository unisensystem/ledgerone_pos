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
                po.bill_no as purchase_order_bill_no,
                sa.sub_account_name,
                co.company_name,
                co.legal_name,
                co.email as company_email,
                co.phone as company_phone,
                co.address as company_address,
                co.city as company_city,
                co.state as company_state,
                co.zipcode as company_zipcode,
                co.logo_url,
                pr.project_code,
                pr.project_name
            FROM purchase_invoice pi
            LEFT JOIN suppliers s ON pi.supplier_id = s.id
            LEFT JOIN branches b ON pi.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
            LEFT JOIN purchase_order po ON pi.purchase_order_id = po.id
            LEFT JOIN supplier_sub_accounts sa ON pi.sub_account_id = sa.id
            LEFT JOIN companies co ON pi.company_id = co.id
            LEFT JOIN projects pr ON pi.project_id = pr.id
            WHERE pi.id = ? AND pi.tenant_id = ?
        ");
        $stmt->execute([$invoice_id, $tenant_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            exit;
        }
        
        // Get invoice items
        $itemStmt = $pdo->prepare("
            SELECT 
                pii.*,
                p.name as product_name,
                p.code as product_code
            FROM purchase_invoice_items pii
            LEFT JOIN products p ON pii.product_id = p.id
            WHERE pii.purchase_invoice_id = ? AND pii.tenant_id = ?
        ");
        $itemStmt->execute([$invoice_id, $tenant_id]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        
        // Update purchase invoice
        $stmt = $pdo->prepare("
            UPDATE purchase_invoice SET
                company_id = ?, currency_id = ?, purchase_date = ?, supplier_id = ?, branch_id = ?,
                previous_balance = ?, total_bill = ?, total_discount_percent = ?,
                total_discount_amount = ?, total_gst_percent = ?, total_gst_amount = ?,
                shipping_fees = ?, net_amount = ?, supplier_invoice_no = ?, 
                supplier_invoice_date = ?, purchase_order_id = ?, bilty_no = ?, transport_name = ?, remarks = ?, sub_account_id = ?, project_id = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $input['companyId'],
            $input['currencyId'],
            $input['purchaseDate'],
            $input['supplierId'],
            $input['branchId'],
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
            $input['purchaseOrderId'] ?? null,
            $input['biltyNo'],
            $input['transportName'],
            $input['remarks'] ?? null,
            $input['subAccountId'] ?? null,
            $input['projectId'] ?? null,
            $user_id,
            $invoice_id,
            $tenant_id
        ]);
        
        // Delete existing items and related records
        $pdo->prepare("DELETE FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        
        // Insert new items
        $item_stmt = $pdo->prepare("
            INSERT INTO purchase_invoice_items (
                tenant_id, purchase_invoice_id, product_id, uom_id, vehicle_no,
                quantity, purchase_price, gross_amount, discount_percent,
                discount_amount, trade_offer_percent, trade_offer_amount,
                gst_percent, gst_amount, foc_quantity, net_amount, parent_row_id,
                piece, carton, dozen, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $itemRowCounter = 0;
        $itemIdMap = [];
        
        foreach ($input['items'] as $item) {
            $itemRowCounter++;
            
            // Map parent_row_id to actual database ID
            $parentRowId = null;
            if (isset($item['parentRowId']) && $item['parentRowId'] !== null) {
                $parentRowId = $itemIdMap[$item['parentRowId']] ?? null;
            }
            
            $item_stmt->execute([
                $tenant_id, $invoice_id, $item['productId'], $item['uomId'], $item['vehicleNo'] ?? null,
                $item['quantity'], $item['purchasePrice'], $item['grossAmount'],
                $item['discountPercent'] ?? 0.00, $item['discountAmount'] ?? 0.00,
                $item['tradeOfferPercent'] ?? 0.00, $item['tradeOfferAmount'] ?? 0.00,
                $item['gstPercent'] ?? 0.00, $item['gstAmount'] ?? 0.00,
                $item['focQty'] ?? 0, $item['netAmount'], $parentRowId,
                $item['pcs'] ?? 0, $item['ctn'] ?? 0, $item['dz'] ?? 0,
                $user_id, $user_id
            ]);
            
            $itemId = $pdo->lastInsertId();
            $itemIdMap[$itemRowCounter] = $itemId;
            
            // Skip stock_ledger if stock_affects = 0
            $stockAffects = $item['stockAffects'] ?? 1;
            if ($stockAffects == 1) {
                // Get product's inventory_account_id
                $product_stmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
                $product_stmt->execute([$item['productId']]);
                $account_id = $product_stmt->fetchColumn();
                
                // Insert stock ledger
                $stock_stmt = $pdo->prepare("
                    INSERT INTO stock_ledger (
                        tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                        qty_in, unit_cost, unit_id, transaction_type, transaction_date
                    ) VALUES (?, ?, ?, ?, 'purchase_invoice', ?, ?, ?, ?, 'Purchase Invoice', ?)
                ");
                $stock_stmt->execute([
                    $tenant_id, $account_id, $input['branchId'], $item['productId'], $invoice_id,
                    $item['quantity'], $item['purchasePrice'], $item['uomId'], $input['purchaseDate']
                ]);
            }
        }
        
        // Get bill number
        $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_invoice WHERE id = ?");
        $billStmt->execute([$invoice_id]);
        $billNo = $billStmt->fetchColumn();
        
        // Accounting entries
        $ledger_stmt = $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit, credit
            ) VALUES (?, 'Purchase Invoice', 'purchase_invoice', ?, ?, ?, ?, ?, ?)
        ");
        
        // 1. Debit - Purchases (totalBill already has item discounts applied, subtract invoice-level discount)
        $purchaseAmount = $input['totalBill'] - $input['totalDiscountAmount'];
        $ledger_stmt->execute([$tenant_id, $invoice_id, 19, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $purchaseAmount, 0]);
        
        // 2. Debit - Input Tax Receivable
        if ($input['totalGSTAmount'] > 0) {
            $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['totalGSTAmount'], 0]);
        }
        
        // 3. Debit - Freight Inward
        if ($input['shippingFees'] > 0) {
            $ledger_stmt->execute([$tenant_id, $invoice_id, 110, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['shippingFees'], 0]);
        }
        
        // 4. Credit - Purchase Discounts Received (invoice-level discount)
        if ($input['totalDiscountAmount'] > 0) {
            $ledger_stmt->execute([$tenant_id, $invoice_id, 103, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, 0, $input['totalDiscountAmount']]);
        }
        
        // 5. Credit - Trade Creditors
        $ledger_stmt->execute([$tenant_id, $invoice_id, 14, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, 0, $input['netAmount']]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Purchase invoice updated successfully']);
        
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