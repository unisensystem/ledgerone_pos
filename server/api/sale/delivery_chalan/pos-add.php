<?php
require_once '../../../../includes/connection.php';
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

    $pdo->beginTransaction();

    // Generate sequential bill number
    $billStmt = $pdo->prepare("SELECT bill_no FROM sale_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();

    if ($lastBill) {
        $lastNumber = (int) substr($lastBill, 4); // Extract number from SAL-XXXX
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'SAL-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

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
            tenant_id, currency_id, bill_no, sale_date, customer_id, sub_account_id, company_id, branch_id,
            previous_balance, sale_officer_id, supplier_man_id, brand_id, sale_order_id, bilty_no, transport_name,
            total_bill, total_discount_percent, total_discount_amount,
            extra_discount_1_percent, extra_discount_1_amount, extra_discount_2_percent, extra_discount_2_amount,
            shipping_fees, net_amount, withholding_tax_percent, withholding_tax_amount, amount_paid_auto_fill,
            invoice_type, due_date, remarks, status, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        extractBalanceAmount($input['previousBalance'] ?? '0.00'),
        $input['salesOfficerId'] ?? null,
        $input['supplierManId'] ?? null,
        $input['brandId'] ?? null,
        $input['saleOrderId'] ?? null,
        $input['biltyNo'] ?? null,
        $input['transportName'] ?? null,
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
        $input['invoiceType'] ?? 'Cash',
        $input['dueDate'] ?? null,
        $input['remarks'] ?? null,
        $input['status'] ?? 'Posted',
        $user_id,
        $user_id
    ]);

    $invoice_id = $pdo->lastInsertId();
    $status = $input['status'] ?? 'Posted';

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
            discount_amount, trade_offer_percent, trade_offer_amount,
            tax_percent, tax_amount, foc_quantity, net_amount, parent_row_id,
            piece, carton, dozen, scheme, created_by, updated_by
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
            $item['quantity'],
            $item['salePrice'],
            $item['grossAmount'],
            $item['discountPercent'] ?? 0.00,
            $item['discountAmount'] ?? 0.00,
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
                    qty_out, unit_cost, unit_id, transaction_type, transaction_date
                ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, 'Sale Invoice', ?)
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
                        qty_out, unit_cost, unit_id, transaction_type, transaction_date
                    ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, 'Sale Invoice - FOC', ?)
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
        
        // Calculate net receivable (after withholding tax)
        $netReceivable = $input['netAmount'] - $withholdingTaxAmount;
        
        // Debit: Trade Debtors (Net Receivable after withholding tax)
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 2, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Invoice - ' . $billNo, $netReceivable
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
        
        // Debit: Discount Allowed (Invoice-level discount only)
        if ($totalInvoiceDiscount > 0) {
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, debit
                ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 89, ?, ?, ?)
            ")->execute([
                $tenant_id, $invoice_id, $input['saleDate'],
                'Sale Invoice - ' . $billNo, $totalInvoiceDiscount
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
        
        // Credit: Sales Revenue (Gross Amount)
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 9, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Invoice - ' . $billNo, $totalGrossAmount
        ]);
        
        // Credit: Sales Tax Payable
        if ($totalTax > 0) {
            $pdo->prepare("
                INSERT INTO accounting_ledger (
                    tenant_id, transaction_type, reference_table, reference_id,
                    account_id, date, description, credit
                ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, 106, ?, ?, ?)
            ")->execute([
                $tenant_id, $invoice_id, $input['saleDate'],
                'Sale Invoice - ' . $billNo, $totalTax
            ]);
        }
    }

    // Insert receive voucher if amount paid > 0 and status is Posted
    if ($status === 'Posted' && isset($input['amountPaid']) && $input['amountPaid'] > 0) {
        // Generate voucher number with year
        $currentYear = date('Y');
        $voucherStmt = $pdo->prepare("SELECT voucher_number FROM receive_voucher WHERE tenant_id = ? AND voucher_number LIKE ? ORDER BY id DESC LIMIT 1");
        $voucherStmt->execute([$tenant_id, "RV-{$currentYear}-%"]);
        $lastVoucher = $voucherStmt->fetchColumn();

        if ($lastVoucher) {
            $lastNumber = (int) substr($lastVoucher, strrpos($lastVoucher, '-') + 1);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        $voucherNumber = 'RV-' . $currentYear . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

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

        // Get account_id for cash/bank
        if ($input['paymentMethod'] === 'cash') {
            $cashAccountId = 1;
        } else {
            $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
            $bankStmt->execute([$input['bankAccountId']]);
            $cashAccountId = $bankStmt->fetchColumn();
        }

        // Debit Cash/Bank Account
        $rvDebitStmt = $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Receive Voucher', 'receive_voucher', ?, ?, ?, ?, ?)
        ");
        $rvDebitStmt->execute([
            $tenant_id,
            $voucher_id,
            $cashAccountId,
            $input['saleDate'],
            'Receive Voucher - ' . $voucherNumber,
            $input['amountPaid']
        ]);

        // Credit Trade Debtors
        $rvCreditStmt = $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Receive Voucher', 'receive_voucher', ?, 2, ?, ?, ?)
        ");
        $rvCreditStmt->execute([
            $tenant_id,
            $voucher_id,
            $input['saleDate'],
            'Receive Voucher - ' . $voucherNumber,
            $input['amountPaid']
        ]);
    }

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
