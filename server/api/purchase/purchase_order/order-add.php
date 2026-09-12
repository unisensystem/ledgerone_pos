<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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

    // Generate sequential bill number and insert. Wrapped in a retry loop because two
    // near-simultaneous saves can both read the same "last bill_no" and try to insert the
    // same next number — the uk_tenant_bill_no unique key then rejects the second one with
    // a 1062 duplicate-entry error. On that specific collision we just regenerate the next
    // number and retry, instead of failing the whole order.
    $maxAttempts = 5;
    $billNo = null;
    $invoice_id = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_order WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $billStmt->execute([$tenant_id]);
        $lastBill = $billStmt->fetchColumn();

        if ($lastBill) {
            $lastNumber = (int)substr($lastBill, 3); // Extract number from PO-XXXX
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        $billNo = 'PO-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        try {
            // Insert purchase order
            $stmt = $pdo->prepare("
                INSERT INTO purchase_order (
                    tenant_id, company_id, currency_id, bill_no, purchase_date, supplier_id, branch_id,
                    project_id, previous_balance, total_bill, total_discount_percent,
                    total_discount_amount, total_gst_percent, total_gst_amount,
                    shipping_fees, net_amount, supplier_invoice_no, supplier_invoice_date,
                    bilty_no, transport_name, remarks, created_by, updated_by
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
                $user_id
            ]);

            $invoice_id = $pdo->lastInsertId();
            break; // success
        } catch (PDOException $e) {
            $isDuplicateBillNo = $e->getCode() == 23000 && strpos($e->getMessage(), 'uk_tenant_bill_no') !== false;
            if ($isDuplicateBillNo && $attempt < $maxAttempts) {
                continue; // regenerate the next number and try again
            }
            throw $e;
        }
    }
    
    // Insert order items
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
    
    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Purchase Order', $invoice_id, $billNo,
        "Created purchase order \"$billNo\" (" . count($input['items']) . " item(s), net " . number_format($input['netAmount'], 2) . ")",
        null,
        [
            'bill_no' => $billNo, 'purchase_date' => $input['purchaseDate'], 'supplier_id' => $input['supplierId'],
            'branch_id' => $input['branchId'], 'company_id' => $input['companyId'], 'currency_id' => $input['currencyId'],
            'project_id' => $input['projectId'] ?? null, 'total_bill' => $input['totalBill'],
            'total_discount_percent' => $input['totalDiscountPercent'] ?? 0, 'total_discount_amount' => $input['totalDiscountAmount'] ?? 0,
            'net_amount' => $input['netAmount'], 'supplier_invoice_no' => $input['supplierInvoiceNo'],
            'bilty_no' => $input['biltyNo'], 'transport_name' => $input['transportName'],
            'remarks' => $input['remarks'] ?? null, 'items' => $input['items']
        ]
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase order saved successfully',
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