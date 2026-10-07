<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/posting_lock.php';
require_once '../../../../includes/activity-logger.php';
require_once '../../../../includes/mobile_auth.php';

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
    $required = ['saleDate', 'customerId', 'branchId', 'items'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }

    if (empty($input['items']) || !is_array($input['items'])) {
    throw new Exception('At least one item is required');
}

$lockCheck = checkPostingDateAllowed($pdo, $tenant_id, 'sale_return', $input['saleDate']);
if (!$lockCheck['allowed']) {
    throw new Exception($lockCheck['message']);
}

    // Idempotent sync (same reasoning as order-add.php): the mobile app sends
    // one client-generated UUID per return created offline, resent on every
    // retry. If a return with this tenant_id + client_uuid already exists,
    // hand back that existing return instead of creating a duplicate — a
    // genuine duplicate here would restock the same items twice.
    $clientUuid = !empty($input['clientUuid']) ? trim($input['clientUuid']) : null;
    if ($clientUuid !== null) {
        $existingStmt = $pdo->prepare("SELECT id, bill_no FROM sale_return WHERE tenant_id = ? AND client_uuid = ?");
        $existingStmt->execute([$tenant_id, $clientUuid]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode([
                'success' => true,
                'message' => 'Sale return saved successfully',
                'invoice_id' => $existing['id'],
                'bill_no' => $existing['bill_no'],
                'already_synced' => true,
            ]);
            exit;
        }
    }

// Generate sequential bill number...

    // Generate sequential bill number — tenant-wide
    $billStmt = $pdo->prepare("SELECT bill_no FROM sale_return WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();

    if ($lastBill && preg_match('/SR-0*(\d+)$/', $lastBill, $m)) {
        $newNumber = (int)$m[1] + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'SR-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();

    // Insert sale return
    $stmt = $pdo->prepare("
        INSERT INTO sale_return (
            tenant_id, company_id, currency_id, bill_no, client_uuid, sale_date, customer_id, customer_phone, branch_id,
            previous_balance, total_bill, total_discount_percent,
            total_discount_amount, extra_discount_1_amount, extra_discount_2_amount, shipping_fees,
            net_amount, sale_invoice_no, amount_refunded, project_id,
            payment_method, bank_account_id, sale_officer_id, supplier_man_id, sub_account_id, remarks, status, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $tenant_id,
        $input['companyId'] ?? null,
        $input['currencyId'],
        $billNo,
        $clientUuid,
        $input['saleDate'],
        $input['customerId'],
        $input['customerPhone'] ?? null,
        $input['branchId'],
        extractBalanceAmount($input['previousBalance'] ?? '0.00'),
        $input['totalBill'],
        $input['totalDiscountPercent'] ?? 0.00,
        $input['totalDiscountAmount'] ?? 0.00,
        $input['extraDiscount1Amount'] ?? 0.00,
        $input['extraDiscount2Amount'] ?? 0.00,
        $input['shippingFees'] ?? 0.00,
        $input['netAmount'],
        $input['saleInvoiceId'] ?? null,
        $input['amountPaid'] ?? 0.00,
        $input['projectId'] ?? null,
        !empty($input['paymentMethod']) ? ucwords(str_replace('_', ' ', $input['paymentMethod'])) : null,
        $input['bankAccountId'] ?? null,
        $input['salesOfficerId'] ?? null,
        $input['supplierManId'] ?? null,
        $input['subAccountId'] ?? null,
        $input['remarks'] ?? null,
        $input['status'] ?? 'Posted',
        $user_id,
        $user_id
    ]);

    $invoice_id = $pdo->lastInsertId();
    $status = $input['status'] ?? 'Posted';

    // Save invoice-level taxes (copied from original sale invoice)
    if (!empty($input['invoiceLevelTaxes']) && is_array($input['invoiceLevelTaxes'])) {
        $taxStmt = $pdo->prepare("
            INSERT INTO sale_return_taxes (
                tenant_id, sale_return_id, tax_regime_id, tax_rate_id,
                tax_name, rate_percentage, base_amount, tax_amount,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($input['invoiceLevelTaxes'] as $tax) {
            $taxAmount = floatval($tax['taxAmount'] ?? 0);
            $ratePercentage = floatval($tax['ratePercentage'] ?? 0);
            if ($taxAmount > 0 || $ratePercentage > 0) {
                $taxStmt->execute([
                    $tenant_id,
                    $invoice_id,
                    $tax['taxRegimeId'] ?? null,
                    $tax['taxRateId'] ?? null,
                    $tax['taxName'] ?? 'Tax',
                    $ratePercentage,
                    floatval($tax['baseAmount'] ?? 0),
                    $taxAmount,
                    $user_id,
                    $user_id
                ]);
            }
        }
    }

    // Insert return items - one record per unit entry
    $item_stmt = $pdo->prepare("
        INSERT INTO sale_return_items (
            tenant_id, sale_invoice_id, product_id, uom_id, batch_no, expiry_date,
            quantity, sale_price, gross_amount, discount_percent,
            discount_amount, trade_offer_percent, trade_offer_amount,
            tax_percent, tax_amount, foc_quantity, stock_status, net_amount, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($input['items'] as $item) {
        if (isset($item['unitEntries']) && is_array($item['unitEntries'])) {
            // Calculate total quantity in pieces for amount calculations
            $totalQtyInPieces = 0;
            foreach ($item['unitEntries'] as $entry) {
                if ($entry['quantity'] > 0) {
                    $totalQtyInPieces += convertToPieces($entry['quantity'], $entry['uomId'], $pdo, $item['productId']);
                }
            }
            
            // Insert one record per unit entry
            foreach ($item['unitEntries'] as $entry) {
                if ($entry['quantity'] > 0) {
                    $qtyInPieces = convertToPieces($entry['quantity'], $entry['uomId'], $pdo, $item['productId']);
                    $proportionOfTotal = $totalQtyInPieces > 0 ? ($qtyInPieces / $totalQtyInPieces) : 0;
                    
                    $item_stmt->execute([
                        $tenant_id,
                        $invoice_id,
                        $item['productId'],
                        $entry['uomId'],
                        $item['batchNo'] ?? null,
                        $item['expiryDate'] ?? null,
                        $entry['quantity'],  // Original quantity (e.g., 2 for 2 Dozen)
                        $item['salePrice'],
                        $item['grossAmount'] * $proportionOfTotal,
                        $item['discountPercent'] ?? 0.00,
                        $item['discountAmount'] * $proportionOfTotal ?? 0.00,
                        $item['tradeOfferPercent'] ?? 0.00,
                        $item['tradeOfferAmount'] * $proportionOfTotal ?? 0.00,
                        $item['taxPercent'] ?? 0.00,
                        $item['taxAmount'] * $proportionOfTotal ?? 0.00,
                        $item['focQuantity'] * $proportionOfTotal ?? 0.00,
                        $item['stockStatus'] ?? 'sellable',
                        $item['netAmount'] * $proportionOfTotal,
                        $user_id,
                        $user_id
                    ]);
                }
            }
        }

        // Skip stock_ledger for Draft status
        if ($status === 'Posted') {
            // Get inventory account_id from product
            $accountStmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
            $accountStmt->execute([$item['productId']]);
            $accountId = $accountStmt->fetchColumn() ?: 0;

            // Insert stock ledger entries for each unit - ORIGINAL quantities
            if (isset($item['unitEntries']) && is_array($item['unitEntries'])) {
                foreach ($item['unitEntries'] as $entry) {
                    if ($entry['quantity'] > 0) {
                        $stock_stmt = $pdo->prepare("
                            INSERT INTO stock_ledger (
                                tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                                qty_in, unit_cost, unit_id, batch_no, expiry_date, stock_status, transaction_type, transaction_date
                            ) VALUES (?, ?, ?, ?, 'sale_return', ?, ?, ?, ?, ?, ?, ?, 'Sale Return', ?)
                        ");
                        $stock_stmt->execute([
                            $tenant_id,
                            $accountId,
                            $input['branchId'],
                            $item['productId'],
                            $invoice_id,
                            $entry['quantity'],  // ORIGINAL quantity (e.g., 2 for 2 Dozen)
                            $item['salePrice'],
                            $entry['uomId'],     // Original unit (e.g., Dozen)
                            $item['batchNo'] ?? null,
                            $item['expiryDate'] ?? null,
                            $item['stockStatus'] ?? 'sellable',
                            $input['saleDate']
                        ]);
                    }
                }
            }

            // Insert stock ledger for FOC quantity if exists
            if (isset($item['focQuantity']) && $item['focQuantity'] > 0) {
                // Use first unit from unitEntries or default to Piece
                $focUomId = 9; // Default to Piece
                if (isset($item['unitEntries']) && count($item['unitEntries']) > 0) {
                    $focUomId = $item['unitEntries'][0]['uomId'];
                }

                $foc_stmt = $pdo->prepare("
                    INSERT INTO stock_ledger (
                        tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                        qty_in, unit_cost, unit_id, batch_no, expiry_date, stock_status, transaction_type, transaction_date
                    ) VALUES (?, ?, ?, ?, 'sale_return', ?, ?, ?, ?, ?, ?, ?, 'Sale Return - FOC', ?)
                ");
                $foc_stmt->execute([
                    $tenant_id,
                    $accountId,
                    $input['branchId'],
                    $item['productId'],
                    $invoice_id,
                    $item['focQuantity'],
                    0,
                    $focUomId,
                    $item['batchNo'] ?? null,
                    $item['expiryDate'] ?? null,
                    $item['stockStatus'] ?? 'sellable',
                    $input['saleDate']
                ]);
            }
        }
    }

    // Skip accounting_ledger for Draft status
    if ($status === 'Posted') {
        // Calculate total Tax
        $totalTax = 0;
        foreach ($input['items'] as $item) {
            $totalTax += floatval($item['taxAmount'] ?? 0);
        }

        // Entry 1: Record Sales Return
        // Dr: Sales Returns & Allowances (Net Amount excluding Tax)
        $netAmountExcludingTax = $input['netAmount'] - $totalTax;
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 18, ?, ?, ?)
        ")->execute([
                    $tenant_id,
                    $invoice_id,
                    $input['saleDate'],
                    'Sale Return - ' . $billNo,
                    $netAmountExcludingTax
                ]);

        // Dr: Sales Tax Payable (Tax reversal)
        if ($totalTax > 0) {
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, debit
                ) VALUES (?, 'Sale Return', 'sale_return', ?, 106, ?, ?, ?)
            ")->execute([
                        $tenant_id,
                        $invoice_id,
                        $input['saleDate'],
                        'Sale Return - Tax Reversal - ' . $billNo,
                        $totalTax
                    ]);
        }

        // Cr: Trade Debtors (Total Net Amount)
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 2, ?, ?, ?)
        ")->execute([
                    $tenant_id,
                    $invoice_id,
                    $input['saleDate'],
                    'Sale Return - ' . $billNo,
                    $input['netAmount']
                ]);
    }

    // Entry 2: If amount refunded > 0, record cash payment
    if ($status === 'Posted' && isset($input['amountPaid']) && $input['amountPaid'] > 0) {
        // Get account_id for cash/bank
        if ($input['paymentMethod'] === 'cash') {
            $cashAccountId = 1;
        } else {
            $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
            $bankStmt->execute([$input['bankAccountId']]);
            $cashAccountId = $bankStmt->fetchColumn();
        }

        // Dr: Trade Debtors
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 2, ?, ?, ?)
        ")->execute([
                    $tenant_id,
                    $invoice_id,
                    $input['saleDate'],
                    'Sale Return Payment - ' . $billNo,
                    $input['amountPaid']
                ]);

        // Cr: Cash/Bank Account
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, ?, ?, ?, ?)
        ")->execute([
                    $tenant_id,
                    $invoice_id,
                    $cashAccountId,
                    $input['saleDate'],
                    'Sale Return Payment - ' . $billNo,
                    $input['amountPaid']
                ]);
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Sale Return', $invoice_id, $billNo,
        "Created sale return \"$billNo\", net amount " . number_format((float)$input['netAmount'], 2),
        null,
        ['bill_no' => $billNo, 'customer_id' => $input['customerId'], 'sale_date' => $input['saleDate'], 'net_amount' => (float)$input['netAmount'], 'status' => $status]
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sale return saved successfully',
        'invoice_id' => $invoice_id,
        'bill_no' => $billNo
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