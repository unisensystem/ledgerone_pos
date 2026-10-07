<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/posting_lock.php';
require_once '../../../../includes/activity-logger.php';

require_once 'invoice-tax-helper.php';

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
    
    // Debug logging
    error_log('pos-add.php received: ' . json_encode($input));
    error_log('brandId value: ' . ($input['brandId'] ?? 'NULL'));

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

$lockCheck = checkPostingDateAllowed($pdo, $tenant_id, 'sale_invoice', $input['saleDate']);
if (!$lockCheck['allowed']) {
    throw new Exception($lockCheck['message']);
}

$pdo->beginTransaction();

    // Generate sequential bill number tenant-wide
    $billStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM sale_invoice WHERE tenant_id = ? AND bill_no LIKE 'SAL-%'");
    $billStmt->execute([$tenant_id]);
    $lastNumber = (int) $billStmt->fetchColumn();
    $billNo = 'SAL-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

    // Get customer tax info
    $customerStmt = $pdo->prepare("SELECT is_sales_tax_registered, is_filer, advance_income_tax_percentage FROM customers WHERE id = ?");
    $customerStmt->execute([$input['customerId']]);
    $customer = $customerStmt->fetch();
    
    // Calculate withholding tax
    $withholdingTaxPercent = floatval($customer['advance_income_tax_percentage'] ?? 0);
    $withholdingTaxAmount = $input['netAmount'] * ($withholdingTaxPercent / 100);

    // Insert sale invoice
    $stmt = $pdo->prepare("
        INSERT INTO sale_invoice (
            tenant_id, currency_id, bill_no, sale_date, customer_id, sub_account_id, company_id, branch_id, counter_id,
            previous_balance, sale_officer_id, supplier_man_id, brand_id, sale_order_id, bilty_no, transport_name, customer_phone,
            total_bill, total_discount_percent, total_discount_amount,
            extra_discount_1_percent, extra_discount_1_amount, extra_discount_2_percent, extra_discount_2_amount,
            shipping_fees, net_amount, withholding_tax_percent, withholding_tax_amount, amount_paid_auto_fill,
            amount_returned, invoice_type, due_date, remarks, status, created_by, updated_by, project_id, container_id,
            payment_method, amount_paid
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $tenant_id,
        $input['currencyId'],
        $billNo,
        $input['saleDate'],
        $input['customerId'],
        $input['subAccountId'] ?? null,
        $input['companyId'] ?? null,
        $input['branchId'],
        (!empty($input['counterId']) && intval($input['counterId']) > 0) ? intval($input['counterId']) : null,
        extractBalanceAmount($input['previousBalance'] ?? '0.00'),
        $input['salesOfficerId'] ?? null,
        $input['supplierManId'] ?? null,
        $input['brandId'] ?? null,
        $input['saleOrderId'] ?? null,
        $input['biltyNo'] ?? null,
        $input['transportName'] ?? null,
        $input['customerPhone'] ?? null,
        $input['totalBill'],
        $input['totalDiscountPercent'] ?? 0.00,
        $input['totalDiscountAmount'] ?? 0.00,
        $input['extraDiscount1Percent'] ?? 0.00,
        $input['extraDiscount1Amount'] ?? 0.00,
        $input['extraDiscount2Percent'] ?? 0.00,
        $input['extraDiscount2Amount'] ?? 0.00,
        $input['shippingFees'] ?? 0.00,
        $input['netAmount'],
        $withholdingTaxPercent,
        $withholdingTaxAmount,
        $input['amountPaidAutoFill'] ?? 'yes',
        $input['amountReturned'] ?? 0.00,
        $input['invoiceType'] ?? 'Cash',
        $input['dueDate'] ?? null,
        $input['remarks'] ?? null,
        $input['status'] ?? 'Posted',
        $user_id,
        $user_id,
        (!empty($input['projectId']) && $input['projectId'] !== null && intval($input['projectId']) > 0) ? intval($input['projectId']) : null,
        (!empty($input['containerId']) && $input['containerId'] !== null && intval($input['containerId']) > 0) ? intval($input['containerId']) : null,
        $input['paymentMethod'] ?? null,
        floatval($input['amountPaid'] ?? 0)
    ]);

    $invoice_id = $pdo->lastInsertId();
    $status = $input['status'] ?? 'Posted';
    // Save signature. Two cases:
    //  1. User cropped a brand-new signature this session -> $input['signature'] is a
    //     data:image base64 string -> decode it and write a new file to disk.
    //  2. User kept the tenant's existing default signature (nothing re-cropped) ->
    //     $input['signature'] is just the filename already on disk (see
    //     get-default-signature.php) -> re-use it directly, no disk write needed.
    // Either way sale_invoice.signature ends up populated, so the NEXT invoice's
    // get-default-signature.php lookup (latest non-null signature for this tenant)
    // keeps picking up the most recently used signature automatically.
if ($status === 'Posted' || $status === 'Draft') {
    if (!empty($input['signature']) && is_string($input['signature'])) {
        if (strpos($input['signature'], 'data:image') === 0) {
            $signatureFileName = saveInvoiceSignature($input['signature'], $tenant_id, $invoice_id);
            if ($signatureFileName) {
                $pdo->prepare("UPDATE sale_invoice SET signature = ? WHERE id = ?")
                    ->execute([$signatureFileName, $invoice_id]);
            }
        } else {
            // Re-using an existing signature filename (tenant default). Don't trust the
            // client-supplied string outright - it later gets rendered as an <img src>
            // with no server-side check at render time, so verify it actually belongs to
            // THIS tenant's own signatures before storing it (blocks path traversal and
            // cross-tenant IDOR via a guessed/probed filename).
            $stmt = $pdo->prepare("SELECT 1 FROM sale_invoice WHERE tenant_id = ? AND signature = ? LIMIT 1");
            $stmt->execute([$tenant_id, $input['signature']]);
            if ($stmt->fetchColumn()) {
                $pdo->prepare("UPDATE sale_invoice SET signature = ? WHERE id = ?")
                    ->execute([$input['signature'], $invoice_id]);
            }
        }
    }
}

    // Save invoice-level taxes
    if (!empty($input['invoiceLevelTaxes']) && is_array($input['invoiceLevelTaxes'])) {
        $mappedTaxes = array_map(function($t) use ($input) {
            return [
                'taxRegimeId'   => $t['regime_id'] ?? null,
                'taxRateId'     => $t['tax_rate_id'] ?? null,
                'taxName'       => $t['regime_name'] ?? 'Tax',
                'ratePercentage'=> floatval($t['rate_percentage'] ?? 0),
                // Use the per-regime base the frontend resolved (total_bill / value_excl_sales_tax / net_amount)
                'baseAmount'    => floatval($t['base_amount'] ?? $input['netAmount'] ?? 0),
                'taxAmount'     => floatval($t['calculated_amount'] ?? 0),
            ];
        }, $input['invoiceLevelTaxes']);
        saveInvoiceTaxes($pdo, $tenant_id, $invoice_id, $mappedTaxes, $user_id);
    }

    // Insert invoice items
 $item_stmt = $pdo->prepare("
    INSERT INTO sale_invoice_items (
        tenant_id, sale_invoice_id, product_id, uom_id,
        quantity, sale_price, gross_amount, discount_percent,
        discount_amount, extra_discount_amount, trade_offer_percent, trade_offer_amount,
        tax_percent, tax_amount, foc_quantity, net_amount, parent_row_id,
        piece, carton, dozen, scheme, batch_no, expiry_date, created_by, updated_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
    $item['quantity'],
    $item['salePrice'],
    $item['grossAmount'],
    $item['discountPercent'] ?? 0.00,
    $item['discountAmount'] ?? 0.00,
    $item['extraDiscountAmount'] ?? 0.00,
    $item['tradeOfferPercent'] ?? 0.00,
    $item['tradeOfferAmount'] ?? 0.00,
    $item['taxPercent'] ?? 0.00,
    $item['taxAmount'] ?? 0.00,
    $item['focQty'] ?? 0.00,
    $item['netAmount'],
    $parentRowId,
    $item['piece'] ?? null,
    $item['carton'] ?? null,
    $item['dozen'] ?? null,
    $item['scheme'] ?? 'sale_on_tp',
    !empty($item['batchNo']) ? $item['batchNo'] : null,
    !empty($item['batchExpiryDate']) ? $item['batchExpiryDate'] : null,
    $user_id,
    $user_id
]);
        
        $itemId = $pdo->lastInsertId();
        $itemIdMap[$itemRowCounter] = $itemId;

        // Skip stock_ledger if stock_affects = 0
        $stockAffects = $item['stockAffects'] ?? 1;
        if ($status === 'Posted' && $stockAffects == 1) {
            // Get product's inventory account
            $productStmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
            $productStmt->execute([$item['productId']]);
            $inventoryAccountId = $productStmt->fetchColumn() ?: 0;
            $costPrice = getCostPrice($pdo, $tenant_id, $item['productId'], $input['branchId']);
            
            // Insert stock ledger for quantity sold (use actual quantity, not converted)
            $stock_stmt = $pdo->prepare("
                INSERT INTO stock_ledger (
                    tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                    qty_out, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
                ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, ?, ?, 'Sale Invoice', ?)
            ");
            $stock_stmt->execute([
                $tenant_id,
                $inventoryAccountId,
                $input['branchId'],
                $item['productId'],
                $invoice_id,
                $item['quantity'],
                $costPrice,
                $item['uomId'],
                !empty($item['batchNo']) ? $item['batchNo'] : null,
                !empty($item['batchExpiryDate']) ? $item['batchExpiryDate'] : null,
                $input['saleDate']
            ]);
            
            // Insert stock ledger for FOC quantity if exists
            if (isset($item['focQty']) && $item['focQty'] > 0) {
                // Use base unit ID for FOC quantity
                $focUnitId = $item['focUnitId'] ?? null;
                
                // If focUnitId is not provided, look up the base unit from the selected unit
                if (!$focUnitId || $focUnitId === '') {
                    $unitStmt = $pdo->prepare("SELECT is_base_unit, base_unit_id FROM uom WHERE id = ?");
                    $unitStmt->execute([$item['uomId']]);
                    $unitData = $unitStmt->fetch();
                    
                    if ($unitData) {
                        // If selected unit IS a base unit, use its ID; otherwise use its base_unit_id
                        $focUnitId = $unitData['is_base_unit'] == 1 ? $item['uomId'] : ($unitData['base_unit_id'] ?? $item['uomId']);
                    } else {
                        // Fallback to selected unit ID if query fails
                        $focUnitId = $item['uomId'];
                    }
                }
                
                $foc_stmt = $pdo->prepare("
                    INSERT INTO stock_ledger (
                        tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                        qty_out, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
                    ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, ?, ?, 'Sale Invoice - FOC', ?)
                ");
                $foc_stmt->execute([
                    $tenant_id,
                    $inventoryAccountId,
                    $input['branchId'],
                    $item['productId'],
                    $invoice_id,
                    $item['focQty'],
                    0,
                    $focUnitId,
                    !empty($item['batchNo']) ? $item['batchNo'] : null,
                    !empty($item['batchExpiryDate']) ? $item['batchExpiryDate'] : null,
                    $input['saleDate']
                ]);
            }
        }
    }

    // Skip accounting_ledger for Draft status
    if ($status === 'Posted') {
        // Calculate totals for accounting entries
        $totalTradeOffer = 0;
        $totalTax = 0;
        $totalGrossAmount = 0;
        
        foreach ($input['items'] as $item) {
            $totalTradeOffer += floatval($item['tradeOfferAmount'] ?? 0);
            $totalTax += floatval($item['taxAmount'] ?? 0);
            $totalGrossAmount += floatval($item['grossAmount']);
        }
        
        $totalInvoiceDiscount = floatval($input['totalDiscountAmount'] ?? 0);

        // Debit: Trade Debtors (Net Amount)
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 2, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Invoice - ' . $billNo, $input['netAmount']
        ]);

        // Debit: AIT Receivable (Withholding Tax)
        if ($withholdingTaxAmount > 0) {
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, debit
                ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 137, ?, ?, ?)
            ")->execute([
                $tenant_id, $invoice_id, $input['saleDate'],
                'AIT Withholding - ' . $billNo, $withholdingTaxAmount
            ]);
        }

        // Debit: Trade Discount (Trade Offer)
        if ($totalTradeOffer > 0) {
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, debit
                ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 105, ?, ?, ?)
            ")->execute([
                $tenant_id, $invoice_id, $input['saleDate'],
                'Sale Invoice - ' . $billNo, $totalTradeOffer
            ]);
        }

        // Credit: Sales Revenue (Net Amount) — Discount Allowed and Sales Tax
        // Payable no longer get their own ledger lines; both are folded into
        // Net Amount on Trade Debtors/Sales Revenue instead.
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 9, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Invoice - ' . $billNo, $input['netAmount']
        ]);
    }

    // Insert receive voucher(s) if amount paid > 0 and status is Posted.
    //
    // Split payment: multiple {method, bankAccountId, amount} lines that together make
    // up the total amountPaid (e.g. part cash, part bank transfer). Each split becomes
    // its OWN complete receive_voucher row (own voucher number, own debit/credit ledger
    // pair) — exactly like posting two separate Receive Vouchers by hand — rather than
    // one combined row, so the Receive Voucher list/ledger genuinely reflects how the
    // customer paid. Falls back to a single voucher when paymentSplits isn't sent (e.g.
    // the plain pos_invoice UI, which has no split payment option).
    // Change given back to the customer (amountReturned) was never retained as
    // revenue, so the Receive Voucher must reflect only the net amount that
    // actually stayed against this invoice, not the full cash tendered.
    $netAmountPaid = floatval($input['amountPaid'] ?? 0) - floatval($input['amountReturned'] ?? 0);
    if ($status === 'Posted' && $netAmountPaid > 0) {
        $paymentSplits = (!empty($input['paymentSplits']) && is_array($input['paymentSplits']))
            ? array_values(array_filter($input['paymentSplits'], function ($s) {
                return floatval($s['amount'] ?? 0) > 0;
            }))
            : [];

        if (empty($paymentSplits)) {
            $paymentSplits = [[
                'method' => $input['paymentMethod'],
                'bankAccountId' => $input['bankAccountId'] ?? null,
                'amount' => $netAmountPaid
            ]];
        }

        // Generate voucher numbers with year, incrementing once per split so each gets
        // its own unique number in the same batch.
        $currentYear = date('Y');
        $voucherStmt = $pdo->prepare("SELECT voucher_number FROM receive_voucher WHERE tenant_id = ? AND voucher_number LIKE ? ORDER BY id DESC LIMIT 1");
        $voucherStmt->execute([$tenant_id, "RV-{$currentYear}-%"]);
        $lastVoucher = $voucherStmt->fetchColumn();
        $nextNumber = $lastVoucher ? ((int) substr($lastVoucher, strrpos($lastVoucher, '-') + 1)) + 1 : 1;

        foreach ($paymentSplits as $split) {
            $splitAmount = floatval($split['amount'] ?? 0);
            if ($splitAmount <= 0) {
                continue;
            }

            $voucherNumber = 'RV-' . $currentYear . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;

            // payment_method_id here matches the standalone Receive Voucher module's
            // convention (accounts.id where sub_account_id=1): 1=Cash, 5=Bank Transfer.
            $splitMethodId = ($split['method'] === 'cash') ? 1 : 5;
            $splitBankAccountId = ($split['method'] === 'cash') ? null : ($split['bankAccountId'] ?? null);

            $rvStmt = $pdo->prepare("
                INSERT INTO receive_voucher (
                    tenant_id, company_id, currency_id, voucher_number, voucher_date, customer_id,
                    bill_no, amount, previous_balance, payment_method_id, bank_account_id, project_id,
                    counter_id, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $rvStmt->execute([
                $tenant_id,
                $input['companyId'] ?? null,
                $input['currencyId'],
                $voucherNumber,
                $input['saleDate'],
                $input['customerId'],
                $billNo,
                $splitAmount,
                extractBalanceAmount($input['previousBalance'] ?? '0.00'),
                $splitMethodId,
                $splitBankAccountId,
                (!empty($input['projectId']) && intval($input['projectId']) > 0) ? intval($input['projectId']) : null,
                (!empty($input['counterId']) && intval($input['counterId']) > 0) ? intval($input['counterId']) : null,
                $user_id,
                $user_id
            ]);

            $voucher_id = $pdo->lastInsertId();

            // Get account_id for cash/bank
            if ($splitMethodId === 1) {
                $cashAccountId = 1;
            } else {
                $cashAccountId = 1;
                if (!empty($splitBankAccountId)) {
                    $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
                    $bankStmt->execute([$splitBankAccountId]);
                    $fetchedAccountId = $bankStmt->fetchColumn();
                    if ($fetchedAccountId !== false && $fetchedAccountId !== null && $fetchedAccountId !== '') {
                        $cashAccountId = intval($fetchedAccountId);
                    }
                }
            }

            // Debit Cash/Bank Account
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, debit
                ) VALUES (?, 'Receive Voucher', 'receive_voucher', ?, ?, ?, ?, ?)
            ")->execute([
                $tenant_id,
                $voucher_id,
                $cashAccountId,
                $input['saleDate'],
                'Receive Voucher - ' . $voucherNumber,
                $splitAmount
            ]);

            // Credit Trade Debtors
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, credit
                ) VALUES (?, 'Receive Voucher', 'receive_voucher', ?, 2, ?, ?, ?)
            ")->execute([
                $tenant_id,
                $voucher_id,
                $input['saleDate'],
                'Receive Voucher - ' . $voucherNumber,
                $splitAmount
            ]);
        }
    }

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Sale Invoice', $invoice_id, $billNo,
        "Created sale invoice \"$billNo\" (" . count($input['items']) . " item(s), net " . number_format($input['netAmount'], 2) . ")",
        null,
        [
            'bill_no' => $billNo, 'sale_date' => $input['saleDate'], 'customer_id' => $input['customerId'],
            'branch_id' => $input['branchId'], 'company_id' => $input['companyId'] ?? null, 'currency_id' => $input['currencyId'],
            'sale_officer_id' => $input['salesOfficerId'] ?? null, 'supplier_man_id' => $input['supplierManId'] ?? null,
            'brand_id' => $input['brandId'] ?? null, 'sale_order_id' => $input['saleOrderId'] ?? null,
            'bilty_no' => $input['biltyNo'] ?? null, 'transport_name' => $input['transportName'] ?? null,
            'customer_phone' => $input['customerPhone'] ?? null,
            'total_bill' => $input['totalBill'], 'total_discount_percent' => $input['totalDiscountPercent'] ?? 0,
            'total_discount_amount' => $input['totalDiscountAmount'] ?? 0, 'shipping_fees' => $input['shippingFees'] ?? 0,
            'net_amount' => $input['netAmount'], 'invoice_type' => $input['invoiceType'] ?? 'Cash',
            'due_date' => $input['dueDate'] ?? null, 'remarks' => $input['remarks'] ?? null, 'status' => $status,
            'amount_paid' => $input['amountPaid'] ?? 0, 'items' => $input['items']
        ]
    );

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sale invoice saved successfully',
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

// Function to extract numeric amount from balance string with sign
function extractBalanceAmount($balanceString)
{
    if (empty($balanceString))
        return 0.00;

    // Check if it's Cr (Credit) - negative value
    if (preg_match('/^Cr\s*/i', $balanceString)) {
        $amount = trim(preg_replace('/^Cr\s*/i', '', $balanceString));
        return -floatval($amount);
    }
    
    // Dr (Debit) or no prefix - positive value
    $amount = trim(preg_replace('/^Dr\s*/i', '', $balanceString));
    return floatval($amount);
}

// Function to get cost price based on inventory valuation method
function getCostPrice($pdo, $tenant_id, $product_id, $branch_id)
{
    // Get inventory valuation method
    $methodStmt = $pdo->prepare("SELECT inventory_valuation_method FROM companies WHERE tenant_id = ? LIMIT 1");
    $methodStmt->execute([$tenant_id]);
    $method = $methodStmt->fetchColumn() ?: 'FIFO';
    
    $costPrice = 0;
    
    if ($method === 'FIFO') {
        // Get oldest purchase price
        $stmt = $pdo->prepare("
            SELECT unit_cost 
            FROM stock_ledger 
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND qty_in > 0 AND unit_cost > 0
            ORDER BY transaction_date ASC, id ASC 
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $product_id, $branch_id]);
        $costPrice = $stmt->fetchColumn() ?: 0;
    } elseif ($method === 'LIFO') {
        // Get latest purchase price
        $stmt = $pdo->prepare("
            SELECT unit_cost 
            FROM stock_ledger 
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND qty_in > 0 AND unit_cost > 0
            ORDER BY transaction_date DESC, id DESC 
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $product_id, $branch_id]);
        $costPrice = $stmt->fetchColumn() ?: 0;
    } elseif ($method === 'AVCO') {
        // Calculate weighted average cost
        $stmt = $pdo->prepare("
            SELECT 
                SUM(qty_in * unit_cost) / NULLIF(SUM(qty_in), 0) as avg_cost
            FROM stock_ledger 
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND qty_in > 0 AND unit_cost > 0
        ");
        $stmt->execute([$tenant_id, $product_id, $branch_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $costPrice = $result['avg_cost'] ?: 0;
    }
    
    // If no cost found from stock ledger, use purchase_price from products table
    if ($costPrice == 0) {
        $stmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $costPrice = $stmt->fetchColumn() ?: 0;
    }
    
    return $costPrice;
}
// Decode a base64 data-URL PNG (from the client-side signature crop) and save it
// to disk, returning the stored filename to be saved in sale_invoice.signature.
function saveInvoiceSignature($dataUrl, $tenant_id, $invoice_id)
{
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $dataUrl)) {
        return null;
    }
    $base64Data = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $decoded = base64_decode($base64Data);
    if ($decoded === false) {
        return null;
    }

    // pos-add.php lives at server/api/sale/pos_invoice/ — 4 levels up reaches the
    // project root (same as the "../../../../includes/connection.php" require above),
    // then into client/assets/uploads/signatures/. Adjust if your folder layout differs.
    $uploadDir = __DIR__ . '/../../../../client/assets/uploads/signatures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'sig_' . $tenant_id . '_' . $invoice_id . '_' . time() . '.png';
    if (file_put_contents($uploadDir . $fileName, $decoded) === false) {
        return null;
    }

    return $fileName;
}