<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once '../../../../includes/mobile_auth.php';
require_once '../../../../includes/sale_order_conflict_check.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}
$user_id = $auth['user_id'];
$tenant_id = $auth['tenant_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $required = ['saleDate', 'branchId', 'items'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }

    if (empty($input['items']) || !is_array($input['items'])) {
        throw new Exception('At least one item is required');
    }

    // A customer created offline (mobile's "Add New Customer" while taking
    // this very order) may not have a real customerId yet — it only gets
    // one once ITS OWN outbox entry syncs. The order carries the customer's
    // clientUuid instead in that case, resolved to a real id here. The
    // outbox replays entries in creation order, so the customer's own entry
    // syncs first; if it hasn't (still offline, or failed), resolution
    // below fails loudly and this order stays queued for the next retry
    // rather than silently attaching to the wrong/no customer.
    if (!empty($input['customerId'])) {
        $customerId = (int) $input['customerId'];
    } elseif (!empty($input['customerClientUuid'])) {
        $custStmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND client_uuid = ?");
        $custStmt->execute([$tenant_id, trim($input['customerClientUuid'])]);
        $customerId = $custStmt->fetchColumn();
        if (!$customerId) {
            throw new Exception('Customer not yet synced — will retry once it syncs');
        }
    } else {
        throw new Exception('Field customerId is required');
    }

    // Idempotent sync: the mobile app sends one client-generated UUID per
    // order created offline, and resends the same one on every retry. If an
    // order with this tenant_id + client_uuid already exists, hand back that
    // existing order instead of creating a duplicate — covers the case where
    // the sync succeeded server-side but the app never saw the response
    // (dropped connection, app killed mid-request, etc.) and retries blind.
    $clientUuid = !empty($input['clientUuid']) ? trim($input['clientUuid']) : null;
    if ($clientUuid !== null) {
        $existingStmt = $pdo->prepare("SELECT id, bill_no FROM sale_order WHERE tenant_id = ? AND client_uuid = ?");
        $existingStmt->execute([$tenant_id, $clientUuid]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode([
                'success' => true,
                'message' => 'Order already synced',
                'invoice_id' => $existing['id'],
                'bill_no' => $existing['bill_no'],
                'already_synced' => true,
            ]);
            exit;
        }
    }

    // Hybrid auto-post rule for offline-created orders: a mobile client can
    // only know the price/stock it last cached, which may have drifted
    // while it was offline (stock especially — several field bookers can be
    // offline at once, each phone showing whatever it last cached, each
    // happily booking against the same physical units nobody else can see
    // being promised; Sale Order never touches stock_ledger itself, so
    // nothing anywhere checked this before now). A real conflict never
    // blocks the order outright — it posts as Draft for office review
    // instead of auto-posting at a stale price or an oversold quantity.
    // Web and already-authenticated-via-session calls are never
    // second-guessed — only requests that actually went through the mobile
    // token path, where a stale offline cache is possible.
    $needsReview = false;
    $priceChanges = [];
    $stockWarnings = [];
    $requestedStatus = $input['status'] ?? 'Posted';
    if (!in_array($requestedStatus, ['Posted', 'Draft'], true)) {
        throw new Exception('Invalid status');
    }
    // Fail closed: run the conflict check for any token request that isn't
    // explicitly asking for Draft, not just an exact 'Posted' match — a
    // client can't skip review by sending some other value.
    if ($auth['via'] === 'token' && $requestedStatus !== 'Draft') {
        $conflicts = checkSaleOrderConflicts($pdo, $tenant_id, $input['items']);
        $needsReview = $conflicts['needsReview'];
        $priceChanges = $conflicts['priceChanges'];
        $stockWarnings = $conflicts['stockWarnings'];
    }

    $status = $needsReview ? 'Draft' : $requestedStatus;

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
        $billStmt = $pdo->prepare("SELECT bill_no FROM sale_order WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
        $billStmt->execute([$tenant_id]);
        $lastBill = $billStmt->fetchColumn();

        if ($lastBill) {
            $lastNumber = (int) substr($lastBill, 3); // Extract number from SO-XXXX
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        $billNo = 'SO-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        try {
            // Insert sale order
            // total_gst_percent/total_gst_amount/shipping_fees all have real
            // columns and web has sent them in its payload all along
            // (totalGSTPercent/totalGSTAmount/shippingFees) — they just
            // weren't in this INSERT's column list, so they were silently
            // discarded on both web and mobile alike. The money itself was
            // never wrong (net_amount already includes them, computed
            // client-side), just the breakdown wasn't recoverable later.
            $stmt = $pdo->prepare("
                INSERT INTO sale_order (
                    tenant_id, company_id, currency_id, bill_no, client_uuid, sale_date, customer_id, branch_id,
                    previous_balance, sale_officer_id, supplier_man_id, project_id, bilty_no, transport_name, total_bill, total_discount_percent,
                    total_discount_amount, total_gst_percent, total_gst_amount, shipping_fees, net_amount, remarks, status,
                    created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $tenant_id,
                $input['companyId'] ?? null,
                $input['currencyId'],
                $billNo,
                $clientUuid,
                $input['saleDate'],
                $customerId,
                $input['branchId'],
                extractBalanceAmount($input['previousBalance'] ?? '0.00'),
                $input['salesOfficerId'] ?? null,
                $input['supplierManId'] ?? null,
                !empty($input['projectId']) ? (int)$input['projectId'] : null,
                $input['biltyNo'] ?? null,
                $input['transportName'] ?? null,
                $input['totalBill'],
                $input['totalDiscountPercent'] ?? 0.00,
                $input['totalDiscountAmount'] ?? 0.00,
                $input['totalGSTPercent'] ?? 0.00,
                $input['totalGSTAmount'] ?? 0.00,
                $input['shippingFees'] ?? 0.00,
                $input['netAmount'],
                $input['remarks'] ?? null,
                $status,
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
            $customerId,
            $billNo,
            $input['amountPaid'],
            $paymentMethodId,
            $input['bankAccountId'] ?? null,
            $user_id,
            $user_id
        ]);

        $voucher_id = $pdo->lastInsertId();
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Sale Order', $invoice_id, $billNo,
        "Created sale order \"$billNo\" (" . count($input['items']) . " item(s), net " . number_format($input['netAmount'], 2) . ")",
        null,
        [
            'bill_no' => $billNo, 'sale_date' => $input['saleDate'], 'company_id' => $input['companyId'] ?? null,
            'currency_id' => $input['currencyId'], 'customer_id' => $customerId, 'branch_id' => $input['branchId'],
            'sale_officer_id' => $input['salesOfficerId'] ?? null, 'supplier_man_id' => $input['supplierManId'] ?? null,
            'project_id' => $input['projectId'] ?? null, 'bilty_no' => $input['biltyNo'] ?? null,
            'transport_name' => $input['transportName'] ?? null, 'total_bill' => $input['totalBill'],
            'total_discount_percent' => $input['totalDiscountPercent'] ?? 0, 'total_discount_amount' => $input['totalDiscountAmount'] ?? 0,
            'net_amount' => $input['netAmount'], 'remarks' => $input['remarks'] ?? null, 'status' => $status,
            'amount_paid' => $input['amountPaid'] ?? 0, 'items' => $input['items']
        ]
    );

    $pdo->commit();

    $reviewReason = null;
    if ($needsReview) {
        if (!empty($priceChanges) && !empty($stockWarnings)) {
            $reviewReason = 'Price and stock changed since offline — saved as Draft for review';
        } elseif (!empty($stockWarnings)) {
            $reviewReason = 'Not enough stock available — saved as Draft for review';
        } else {
            $reviewReason = 'Price changed since offline — saved as Draft for review';
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $needsReview ? $reviewReason : 'Sale order saved successfully',
        'invoice_id' => $invoice_id,
        'bill_no' => $billNo,
        'status' => $status,
        'needs_review' => $needsReview,
        'price_changes' => $priceChanges,
        'stock_warnings' => $stockWarnings,
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