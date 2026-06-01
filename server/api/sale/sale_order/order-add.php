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
    $required = ['saleDate', 'customerId', 'branchId', 'items'];
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
    $billStmt = $pdo->prepare("SELECT bill_no FROM sale_order WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();

    if ($lastBill) {
        $lastNumber = (int) substr($lastBill, 4); // Extract number from SAL-XXXX
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'SO-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

    // Insert sale invoice
    $stmt = $pdo->prepare("
        INSERT INTO sale_order (
            tenant_id, company_id, currency_id, bill_no, sale_date, customer_id, branch_id,
            previous_balance, sale_officer_id, supplier_man_id, bilty_no, transport_name, total_bill, total_discount_percent, 
            total_discount_amount, net_amount, remarks, status,
            created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $tenant_id,
        $input['companyId'] ?? null,
        $input['currencyId'],
        $billNo,
        $input['saleDate'],
        $input['customerId'],
        $input['branchId'],
        extractBalanceAmount($input['previousBalance'] ?? '0.00'),
        $input['salesOfficerId'] ?? null,
        $input['supplierManId'] ?? null,
        $input['biltyNo'] ?? null,
        $input['transportName'] ?? null,
        $input['totalBill'],
        $input['totalDiscountPercent'] ?? 0.00,
        $input['totalDiscountAmount'] ?? 0.00,
        $input['netAmount'],
        $input['remarks'] ?? null,
        $input['status'] ?? 'Posted',
        $user_id,
        $user_id
    ]);

    $invoice_id = $pdo->lastInsertId();
    $status = $input['status'] ?? 'Posted';

    // Insert invoice items with dynamic UOM support
    $item_stmt = $pdo->prepare("
        INSERT INTO sale_order_items (
            tenant_id, sale_invoice_id, product_id, uom_id,
            quantity, sale_price, gross_amount, discount_percent,
            discount_amount, trade_offer_percent, trade_offer_amount,
            gst_percent, gst_amount, foc_quantity, scheme, net_amount, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($input['items'] as $item) {
        $isFirstEntry = true;
        foreach ($item['unitEntries'] as $unitEntry) {
            $item_stmt->execute([
                $tenant_id,
                $invoice_id,
                $item['productId'],
                $unitEntry['uomId'],
                $unitEntry['quantity'],
                $item['salePrice'],
                $isFirstEntry ? $item['grossAmount'] : 0,
                $item['discountPercent'] ?? 0.00,
                $isFirstEntry ? $item['discountAmount'] : 0,
                $item['tradeOfferPercent'] ?? 0.00,
                $isFirstEntry ? $item['tradeOfferAmount'] : 0,
                $item['gstPercent'] ?? 0.00,
                $isFirstEntry ? $item['gstAmount'] : 0,
                $item['focQty'] ?? 0,
                $item['scheme'] ?? 'sale_on_tp',
                $isFirstEntry ? $item['netAmount'] : 0,
                $user_id,
                $user_id
            ]);
            $isFirstEntry = false;
        }
    }

    // Insert receive voucher if amount paid > 0 and status is Posted
    if ($status === 'Posted' && isset($input['amountPaid']) && $input['amountPaid'] > 0) {
        // Generate voucher number
        $voucherStmt = $pdo->prepare("SELECT voucher_number FROM receive_voucher WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $voucherStmt->execute([$tenant_id]);
        $lastVoucher = $voucherStmt->fetchColumn();

        if ($lastVoucher) {
            $lastNumber = (int) substr($lastVoucher, 3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        $voucherNumber = 'RV-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        // Determine payment method ID (1=Cash, 2=Bank Transfer)
        $paymentMethodId = ($input['paymentMethod'] === 'cash') ? 1 : 2;

        // Insert receive voucher
        $rvStmt = $pdo->prepare("
            INSERT INTO receive_voucher (
                tenant_id, currency_id, voucher_number, voucher_date, customer_id,
                bill_no, amount, payment_method_id, bank_account_id, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $rvStmt->execute([
            $tenant_id,
            $input['currencyId'],
            $voucherNumber,
            $input['saleDate'],
            $input['customerId'],
            $billNo,
            $input['amountPaid'],
            $paymentMethodId,
            $input['bankAccountId'] ?? null,
            $user_id,
            $user_id
        ]);

        $voucher_id = $pdo->lastInsertId();
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sale order saved successfully',
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
function extractBalanceAmount($balanceString)
{
    if (empty($balanceString))
        return 0.00;

    // Remove 'Dr' or 'Cr' and extract numeric value
    $amount = preg_replace('/^(Dr|Cr)\s*/', '', $balanceString);
    return floatval($amount);
}

// Function to convert quantity to pieces based on UOM
function convertToPieces($quantity, $uomId, $pdo, $productId)
{
    $qty = floatval($quantity);
    $uomId = intval($uomId);
    
    // Piece (id: 9) - base unit
    if ($uomId === 9) {
        return $qty;
    }
    
    // Dozen (id: 10) - 12 pieces
    if ($uomId === 10) {
        return $qty * 12;
    }
    
    // Carton (id: 16) - get from product's carton_conversion
    if ($uomId === 16) {
        $stmt = $pdo->prepare("SELECT carton_conversion FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $cartonConversion = $stmt->fetchColumn();
        return $qty * intval($cartonConversion ?: 1);
    }
    
    // Other units - return as is
    return $qty;
}