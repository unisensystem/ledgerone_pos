<?php
require_once '../../../../includes/connection.php';
require_once 'invoice-tax-helper.php';

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
                si.*,
                c.customer_name,
                c.customer_code,
                c.address as customer_address,
                c.primary_phone as customer_phone,
                c.email as customer_email,
                csa.sub_account_name,
                co.company_name,
                b.branch_name,
                b.branch_code,
                b.branch_type,
                pb.branch_name as parent_branch_name,
                cur.symbol as currency_symbol,
                cur.code as currency_code,
                cur.name as currency_name,
                e.id as sales_officer_id,
                e.full_name as sales_officer_name,
                sm.id as supplier_man_id,
                sm.employee_id as supplier_man_employee_id,
                sm.full_name as supplier_man_name,
                COALESCE(rv.amount, 0) as amount_paid,
                CASE 
                    WHEN rv.payment_method_id = 1 THEN 'Cash'
                    WHEN rv.payment_method_id = 2 THEN 'Bank Transfer'
                    ELSE NULL
                END as payment_method
            FROM sale_invoice si
            LEFT JOIN customers c ON si.customer_id = c.id
            LEFT JOIN customer_sub_accounts csa ON si.sub_account_id = csa.id
            LEFT JOIN companies co ON si.company_id = co.id
            LEFT JOIN branches b ON si.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
            LEFT JOIN employees e ON si.sale_officer_id = e.id
            LEFT JOIN employees sm ON si.supplier_man_id = sm.id
            LEFT JOIN receive_voucher rv ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
            WHERE si.id = ? AND si.tenant_id = ?
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
                sii.*,
                COALESCE(sii.scheme, 'sale_on_tp') as scheme,
                p.name as product_name,
                p.code as product_code,
                p.stock_affects,
                p.invoice_affects,
                u.uom_name
            FROM sale_invoice_items sii
            LEFT JOIN products p ON sii.product_id = p.id
            LEFT JOIN uom u ON sii.uom_id = u.id
            WHERE sii.sale_invoice_id = ? AND sii.tenant_id = ?
            ORDER BY COALESCE(sii.parent_row_id, sii.id), sii.id
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

        // Get customer tax info
        $customerStmt = $pdo->prepare("SELECT is_sales_tax_registered, is_filer, advance_income_tax_percentage FROM customers WHERE id = ?");
        $customerStmt->execute([$input['customerId']]);
        $customer = $customerStmt->fetch();
        
        // Calculate withholding tax
        $withholdingTaxPercent = floatval($customer['advance_income_tax_percentage'] ?? 0);
        $withholdingTaxAmount = $input['netAmount'] * ($withholdingTaxPercent / 100);

        // Update sale invoice
        $stmt = $pdo->prepare("
            UPDATE sale_invoice SET
                currency_id = ?, sale_date = ?, customer_id = ?, sub_account_id = ?, company_id = ?, branch_id = ?,
                previous_balance = ?, sale_officer_id = ?, supplier_man_id = ?, sale_order_id = ?, bilty_no = ?, transport_name = ?, project_id = ?, total_bill = ?, total_discount_percent = ?,
                total_discount_amount = ?, net_amount = ?, withholding_tax_percent = ?, withholding_tax_amount = ?, amount_paid_auto_fill = ?, remarks = ?, status = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $input['currencyId'],                                // 1
            $input['saleDate'],                                  // 2
            $input['customerId'],                                // 3
            $input['subAccountId'] ?? null,                      // 4
            $input['companyId'] ?? null,                         // 5
            $input['branchId'],                                  // 6
            extractBalanceAmount($input['previousBalance'] ?? '0.00'), // 7
            $input['salesOfficerId'] ?? null,                    // 8
            $input['supplierManId'] ?? null,                     // 9
            $input['saleOrderId'] ?? null,                       // 10
            $input['biltyNo'] ?? null,                           // 11
            $input['transportName'] ?? null,                     // 12
            $input['projectId'] ?? null,                         // 13 - project_id
            $input['totalBill'],                                 // 14
            $input['totalDiscountPercent'] ?? 0.00,              // 15
            $input['totalDiscountAmount'] ?? 0.00,               // 16
            $input['netAmount'],                                 // 17
            $withholdingTaxPercent,                              // 18
            $withholdingTaxAmount,                               // 19
            $input['amountPaidAutoFill'] ?? 'yes',               // 20
            $input['remarks'] ?? null,                           // 21
            $input['status'] ?? 'Posted',                        // 22
            $user_id,                                            // 23
            $invoice_id,                                         // 24
            $tenant_id                                           // 25
        ]);

        // Delete existing items and related records
        $pdo->prepare("DELETE FROM sale_invoice_taxes WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM sale_invoice_items WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'sale_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'sale_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        
        // Delete existing receive voucher and its accounting entries
        $billStmt = $pdo->prepare("SELECT bill_no FROM sale_invoice WHERE id = ?");
        $billStmt->execute([$invoice_id]);
        $billNo = $billStmt->fetchColumn();
        
        $rvStmt = $pdo->prepare("SELECT id FROM receive_voucher WHERE bill_no = ? AND tenant_id = ?");
        $rvStmt->execute([$billNo, $tenant_id]);
        $existingRvId = $rvStmt->fetchColumn();
        
        if ($existingRvId) {
            $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'receive_voucher' AND reference_id = ? AND tenant_id = ?")->execute([$existingRvId, $tenant_id]);
            $pdo->prepare("DELETE FROM receive_voucher WHERE id = ? AND tenant_id = ?")->execute([$existingRvId, $tenant_id]);
        }

        $status = $input['status'] ?? 'Posted';

        // Save invoice-level taxes
        if (!empty($input['invoiceLevelTaxes']) && is_array($input['invoiceLevelTaxes'])) {
            $mappedTaxes = array_map(function($t) use ($input) {
                return [
                    'taxRegimeId'   => $t['regime_id'] ?? null,
                    'taxRateId'     => $t['tax_rate_id'] ?? null,
                    'taxName'       => $t['regime_name'] ?? 'Tax',
                    'ratePercentage'=> floatval($t['rate_percentage'] ?? 0),
                    'baseAmount'    => floatval($input['netAmount'] ?? 0),
                    'taxAmount'     => floatval($t['calculated_amount'] ?? 0),
                ];
            }, $input['invoiceLevelTaxes']);
            saveInvoiceTaxes($pdo, $tenant_id, $invoice_id, $mappedTaxes, $user_id);
        }

        // Insert new items
        $item_stmt = $pdo->prepare("
            INSERT INTO sale_invoice_items (
                tenant_id, sale_invoice_id, product_id, uom_id,
                quantity, sale_price, gross_amount, discount_percent,
                discount_amount, trade_offer_percent, trade_offer_amount,
                tax_percent, tax_amount, foc_quantity, net_amount, parent_row_id,
                piece, carton, dozen, scheme, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $itemIdMap = [];
        foreach ($input['items'] as $index => $item) {
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
            
            $itemIdMap[$index + 1] = $pdo->lastInsertId();

            // Skip stock ledger for Draft status
            if ($status === 'Posted') {
                // Convert quantity to pieces based on UOM
                $qtyInPieces = convertToPieces($item['quantity'], $item['uomId'], $pdo, $item['productId']);
                
                // Get product cost price
                $costPrice = getCostPrice($pdo, $tenant_id, $item['productId'], $input['branchId']);
                
                // Insert stock ledger for quantity sold
                $stock_stmt = $pdo->prepare("
                        INSERT INTO stock_ledger (
                            tenant_id, branch_id, product_id, reference_table, reference_id,
                            qty_out, unit_cost, unit_id, transaction_type, transaction_date
                        ) VALUES (?, ?, ?, 'sale_invoice', ?, ?, ?, ?, 'Sale Invoice', ?)
                    ");
                $stock_stmt->execute([
                    $tenant_id,
                    $input['branchId'],
                    $item['productId'],
                    $invoice_id,
                    $qtyInPieces,
                    $costPrice,
                    $item['uomId'],
                    $input['saleDate']
                ]);
                
                // Insert stock ledger for FOC quantity if exists
                if (isset($item['focQty']) && $item['focQty'] > 0) {
                    $focQtyInPieces = convertToPieces($item['focQty'], $item['uomId'], $pdo, $item['productId']);
                    
                    $foc_stmt = $pdo->prepare("
                        INSERT INTO stock_ledger (
                            tenant_id, branch_id, product_id, reference_table, reference_id,
                            qty_out, unit_cost, unit_id, transaction_type, transaction_date
                        ) VALUES (?, ?, ?, 'sale_invoice', ?, ?, ?, ?, 'Sale Invoice - FOC', ?)
                    ");
                    $foc_stmt->execute([
                        $tenant_id,
                        $input['branchId'],
                        $item['productId'],
                        $invoice_id,
                        $focQtyInPieces,
                        0,
                        $item['uomId'],
                        $input['saleDate']
                    ]);
                }
            }
        }

        // Skip accounting ledger for Draft status
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

        echo json_encode(['success' => true, 'message' => 'Sale invoice updated successfully']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to extract numeric amount from balance string
function extractBalanceAmount($balanceString)
{
    if (empty($balanceString))
        return 0.00;
    $amount = preg_replace('/^(Dr|Cr)\s*/', '', $balanceString);
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