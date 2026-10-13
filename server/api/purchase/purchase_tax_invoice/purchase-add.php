<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $required = ['purchaseDate', 'supplierId', 'branchId', 'items'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }
    
    if (empty($input['items']) || !is_array($input['items'])) {
        throw new Exception('At least one item is required');
    }
    
    $pdo->beginTransaction();
    
    // Generate sequential bill number
    $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();
    
    if ($lastBill) {
        $lastNumber = (int)substr($lastBill, 4); // Extract number from PUR-XXXX
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'PUR-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    // Insert purchase invoice
    $stmt = $pdo->prepare("
        INSERT INTO purchase_invoice (
            tenant_id, company_id, currency_id, bill_no, purchase_date, supplier_id, branch_id,
            previous_balance, total_bill, total_discount_percent, 
            total_discount_amount, total_gst_percent, total_gst_amount, 
            shipping_fees, net_amount, supplier_invoice_no, supplier_invoice_date, 
            purchase_order_id, bilty_no, transport_name, remarks, sub_account_id, project_id, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $tenant_id,
        $input['companyId'],
        $input['currencyId'],
        $billNo,
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
        $user_id
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // Insert invoice items
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
            $tenant_id,
            $invoice_id,
            $item['productId'],
            $item['uomId'],
            $item['vehicleNo'] ?? null,
            $item['quantity'],
            $item['purchasePrice'],
            $item['grossAmount'],
            $item['discountPercent'] ?? 0.00,
            $item['discountAmount'] ?? 0.00,
            $item['tradeOfferPercent'] ?? 0.00,
            $item['tradeOfferAmount'] ?? 0.00,
            $item['gstPercent'] ?? 0.00,
            $item['gstAmount'] ?? 0.00,
            $item['focQty'] ?? 0,
            $item['netAmount'],
            $parentRowId,
            $item['pcs'] ?? 0,
            $item['ctn'] ?? 0,
            $item['dz'] ?? 0,
            $user_id,
            $user_id
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
            
            // Insert into stock_ledger
            $stock_stmt = $pdo->prepare("
                INSERT INTO stock_ledger (
                    tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                    qty_in, unit_cost, unit_id, transaction_type, transaction_date
                ) VALUES (?, ?, ?, ?, 'purchase_invoice', ?, ?, ?, ?, 'Purchase Invoice', ?)
            ");
            $stock_stmt->execute([
                $tenant_id,
                $account_id,
                $input['branchId'],
                $item['productId'],
                $invoice_id,
                $item['quantity'],
                $item['purchasePrice'],
                $item['uomId'],
                $input['purchaseDate']
            ]);
        }
    }
    
    // Accounting entries
    $ledger_stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Purchase Invoice', 'purchase_invoice', ?, ?, ?, ?, ?, ?)
    ");
    
    // 1. Debit - Purchases (Net Amount)
    $ledger_stmt->execute([$tenant_id, $invoice_id, 19, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['netAmount'], 0]);

    // 2. Debit - Input Tax Receivable
    if ($input['totalGSTAmount'] > 0) {
        $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['totalGSTAmount'], 0]);
    }

    // 3. Debit - Freight Inward
    if ($input['shippingFees'] > 0) {
        $ledger_stmt->execute([$tenant_id, $invoice_id, 110, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['shippingFees'], 0]);
    }

    // 4. Credit - Trade Creditors (Net Amount) — Purchase Discounts Received no
    // longer gets its own ledger line; it's folded into Net Amount on
    // Purchases/Trade Creditors instead.
    $ledger_stmt->execute([$tenant_id, $invoice_id, 14, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, 0, $input['netAmount']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase invoice saved successfully',
        'invoice_id' => $invoice_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Function to extract numeric amount from balance string
function extractBalanceAmount($balanceString) {
    if (empty($balanceString)) return 0.00;
    
    // Remove 'Dr' or 'Cr' and extract numeric value
    $amount = preg_replace('/^(Dr|Cr)\s*/', '', $balanceString);
    return floatval($amount);
}