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

    // Validate that every item has a batch selected (batch_no required for return)
    foreach ($input['items'] as $item) {
        if (empty($item['batchNo'])) {
            throw new Exception('Please select a batch for every item — a product with no available stock cannot be returned');
        }
    }
    
    $pdo->beginTransaction();
    
    // Generate sequential bill number — tenant-wide
    $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_return WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();

    if ($lastBill && preg_match('/PRN-0*(\d+)$/', $lastBill, $m)) {
        $newNumber = (int)$m[1] + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'PRN-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    // Insert purchase return
    $stmt = $pdo->prepare("
        INSERT INTO purchase_return (
            tenant_id, company_id, currency_id, bill_no, purchase_date, supplier_id, branch_id,
            previous_balance, total_bill, total_discount_percent, 
            total_discount_amount, total_gst_percent, total_gst_amount, 
            shipping_fees, net_amount, purchase_invoice_no, 
            bilty_no, transport_name, remarks, sub_account_id, project_id, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $input['purchaseInvoiceId'],
        $input['biltyNo'],
        $input['transportName'],
        $input['remarks'] ?? null,
        $input['subAccountId'] ?? null,
        $input['projectId'] ?? null,
        $user_id,
        $user_id
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // Insert return items — batch_no and batch_expiry_date added
    $item_stmt = $pdo->prepare("
        INSERT INTO purchase_return_items (
            tenant_id, purchase_invoice_id, product_id, uom_id,
            quantity, purchase_price, gross_amount, discount_percent,
            discount_amount, net_amount, vehicle_no, trade_offer_percent, trade_offer_amount,
            tax_percent, tax_amount, foc_quantity, batch_no, batch_expiry_date, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $isFirstEntry ? $item['netAmount'] : 0,
                $item['vehicleNo'] ?? null,
                $item['tradeOfferPercent'] ?? 0.00,
                $isFirstEntry ? $item['tradeOfferAmount'] : 0,
                $item['gstPercent'] ?? 0.00,
                $isFirstEntry ? $item['gstAmount'] : 0,
                $item['focQty'] ?? 0,
                $item['batchNo'],
                $item['batchExpiryDate'] ?? null,
                $user_id,
                $user_id
            ]);
            $isFirstEntry = false;
        }
        
        // Get product's inventory_account_id
        $product_stmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
        $product_stmt->execute([$item['productId']]);
        $account_id = $product_stmt->fetchColumn();
        
        // Calculate total quantity from all unit entries
        $totalQty = 0;
        foreach ($item['unitEntries'] as $unitEntry) {
            $totalQty += $unitEntry['quantity'];
        }

        // Safety check: don't let qty_out exceed what's actually available in this batch.
        // (Frontend already restricts this via get-product-batches.php, this is a server-side guard.)
        $avail_stmt = $pdo->prepare("
            SELECT COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) AS available_qty
            FROM stock_ledger
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND batch_no = ?
        ");
        $avail_stmt->execute([$tenant_id, $item['productId'], $input['branchId'], $item['batchNo']]);
        $availableQty = (float) $avail_stmt->fetchColumn();

        if ($totalQty > $availableQty) {
            throw new Exception("Return quantity for batch '{$item['batchNo']}' exceeds available stock ({$availableQty})");
        }
        
        // Insert into stock_ledger - qty_out for returns (reducing inventory), batch_no/expiry recorded
        $stock_stmt = $pdo->prepare("
            INSERT INTO stock_ledger (
                tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                qty_out, unit_cost, unit_id, batch_no, batch_expiry_date, transaction_type, transaction_date
            ) VALUES (?, ?, ?, ?, 'purchase_return', ?, ?, ?, ?, ?, ?, 'Purchase Return', ?)
        ");
        $stock_stmt->execute([
            $tenant_id,
            $account_id,
            $input['branchId'],
            $item['productId'],
            $invoice_id,
            $totalQty,
            $item['purchasePrice'],
            $item['unitEntries'][0]['uomId'],
            $item['batchNo'],
            $item['batchExpiryDate'] ?? null,
            $input['purchaseDate']
        ]);
    }
    
    // Accounting entries
    $ledger_stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Purchase Return', 'purchase_return', ?, ?, ?, ?, ?, ?)
    ");
    
    // Calculate total item-level discounts and trade offers
    $totalItemDiscounts = 0;
    $totalItemTradeOffers = 0;
    foreach ($input['items'] as $item) {
        $totalItemDiscounts += ($item['discountAmount'] ?? 0);
        $totalItemTradeOffers += ($item['tradeOfferAmount'] ?? 0);
    }
    
    // Calculate inventory value = Gross - All Discounts - All Trade Offers
    $inventoryValue = $input['totalBill'] - $totalItemDiscounts - $totalItemTradeOffers - ($input['totalDiscountAmount'] ?? 0);
    
    // 1. Debit - Accounts Payable - Reduce liability
    $ledger_stmt->execute([$tenant_id, $invoice_id, 14, $input['purchaseDate'], 'Purchase Return - ' . $billNo, $input['netAmount'], 0]);
    
    // 2. Credit - Inventory - Reduce asset value
    $ledger_stmt->execute([$tenant_id, $invoice_id, 1, $input['purchaseDate'], 'Purchase Return - ' . $billNo, 0, $inventoryValue]);
    
    // 3. Credit - Input Tax Receivable - Reverse GST claimed
    if ($input['totalGSTAmount'] > 0) {
        $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Return - ' . $billNo, 0, $input['totalGSTAmount']]);
    }
    
    // 4. Credit - Freight Inward - Reverse shipping costs
    if ($input['shippingFees'] > 0) {
        $ledger_stmt->execute([$tenant_id, $invoice_id, 110, $input['purchaseDate'], 'Purchase Return - ' . $billNo, 0, $input['shippingFees']]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase return saved successfully',
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

// ALTER TABLE purchase_return_items
// ADD COLUMN batch_no VARCHAR(100) NULL AFTER uom_id,
// ADD COLUMN batch_expiry_date DATE NULL AFTER batch_no;
