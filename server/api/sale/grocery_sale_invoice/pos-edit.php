<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
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
                si.ship_to,
                c.customer_name,
                c.customer_code,
                c.address as customer_address,
                -- Aliased as customer_primary_phone (not customer_phone) to avoid colliding
                -- with si.customer_phone (the invoice's own saved/editable Phone No field,
                -- from si.* above) — the two can legitimately differ once someone edits the
                -- invoice's phone to something other than the customer's current phone.
                c.primary_phone as customer_primary_phone,
                c.email as customer_email,
                c.ntn as customer_ntn,
                c.strn as customer_gst,
                c.identity_card_no as customer_cnic,
                c.license_no as customer_license_no,
                a.area_name,
                csa.sub_account_name,
                co.company_name,
                co.address as company_address,
                so.bill_no as sale_order_no,
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
                br.supplier_name as brand_name,
                (
                    SELECT COALESCE(SUM(rv.amount), 0) FROM receive_voucher rv
                    WHERE rv.bill_no = si.bill_no AND rv.tenant_id = si.tenant_id
                ) as amount_paid,
                (
                    SELECT
                        CASE
                            WHEN COUNT(*) = 0 THEN NULL
                            WHEN COUNT(DISTINCT rv.payment_method_id) > 1 THEN 'Split'
                            WHEN MAX(rv.payment_method_id) = 1 THEN 'Cash'
                            WHEN MAX(rv.payment_method_id) = 5 THEN 'Bank Transfer'
                            ELSE NULL
                        END
                    FROM receive_voucher rv
                    WHERE rv.bill_no = si.bill_no AND rv.tenant_id = si.tenant_id
                ) as payment_method,
                (
                    SELECT rv.bank_account_id FROM receive_voucher rv
                    WHERE rv.bill_no = si.bill_no AND rv.tenant_id = si.tenant_id AND rv.payment_method_id = 5
                    ORDER BY rv.id ASC LIMIT 1
                ) as bank_account_id
            FROM sale_invoice si
            LEFT JOIN customers c ON si.customer_id = c.id
            LEFT JOIN areas a ON c.area_id = a.id
            LEFT JOIN customer_sub_accounts csa ON si.sub_account_id = csa.id
            LEFT JOIN companies co ON si.company_id = co.id
            LEFT JOIN sale_order so ON si.sale_order_id = so.id
            LEFT JOIN branches b ON si.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
            LEFT JOIN employees e ON si.sale_officer_id = e.id
            LEFT JOIN employees sm ON si.supplier_man_id = sm.id
            LEFT JOIN suppliers br ON si.brand_id = br.id
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

        // Split payment breakdown (only populated when this invoice's payment was
        // actually split across multiple methods — see pos-add.php/pos-edit.php PUT).
        $splitsStmt = $pdo->prepare("
            SELECT payment_method_id, bank_account_id, amount
            FROM receive_voucher
            WHERE bill_no = ? AND tenant_id = ?
            ORDER BY id ASC
        ");
        $splitsStmt->execute([$invoice['bill_no'], $tenant_id]);
        $receiveVouchers = $splitsStmt->fetchAll(PDO::FETCH_ASSOC);
        // Only surface as a "split" when there's genuinely more than one receive_voucher
        // for this bill — a normal single-method payment stays a single voucher row and
        // the Add page's regular Payment Method field handles it as before.
        $paymentSplits = count($receiveVouchers) > 1 ? $receiveVouchers : [];

        echo json_encode([
            'success' => true,
            'invoice' => $invoice,
            'items' => $items,
            'payment_splits' => $paymentSplits
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
        // Grab old values before anything changes, for the Activity Log diff
        $stmt = $pdo->prepare("
            SELECT bill_no, currency_id, sale_date, customer_id, sub_account_id, company_id, branch_id, previous_balance,
                   sale_officer_id, supplier_man_id, brand_id, sale_order_id, bilty_no, transport_name, customer_phone, total_bill,
                   total_discount_percent, total_discount_amount, extra_discount_1_percent, extra_discount_1_amount,
                   extra_discount_2_percent, extra_discount_2_amount, net_amount, remarks, ship_to, status, project_id, container_id
            FROM sale_invoice WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$invoice_id, $tenant_id]);
        $oldInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldInvoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT product_id, uom_id, quantity, sale_price, discount_percent, tax_percent, foc_quantity FROM sale_invoice_items WHERE sale_invoice_id = ? AND tenant_id = ? ORDER BY product_id, uom_id, id");
        $stmt->execute([$invoice_id, $tenant_id]);
        $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                previous_balance = ?, sale_officer_id = ?, supplier_man_id = ?, brand_id = ?, sale_order_id = ?, bilty_no = ?, transport_name = ?, customer_phone = ?, total_bill = ?, total_discount_percent = ?,
                total_discount_amount = ?, extra_discount_1_percent = ?, extra_discount_1_amount = ?, extra_discount_2_percent = ?, extra_discount_2_amount = ?,
                net_amount = ?, withholding_tax_percent = ?, withholding_tax_amount = ?, amount_paid_auto_fill = ?, amount_returned = ?, remarks = ?, ship_to = ?, status = ?, project_id = ?, container_id = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $input['currencyId'],
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
            $input['customerPhone'] ?? null,
            $input['totalBill'],
            $input['totalDiscountPercent'] ?? 0.00,
            $input['totalDiscountAmount'] ?? 0.00,
            $input['extraDiscount1Percent'] ?? 0.00,
            $input['extraDiscount1Amount'] ?? 0.00,
            $input['extraDiscount2Percent'] ?? 0.00,
            $input['extraDiscount2Amount'] ?? 0.00,
            $input['netAmount'],
            $withholdingTaxPercent,
            $withholdingTaxAmount,
            $input['amountPaidAutoFill'] ?? 'yes',
            $input['amountReturned'] ?? 0.00,
            $input['remarks'] ?? null,
            $input['shipTo'] ?? null,
            $input['status'] ?? 'Posted',
            $input['projectId'] ?? null,
            (!empty($input['containerId']) && $input['containerId'] !== null && intval($input['containerId']) > 0) ? intval($input['containerId']) : null,
            $user_id,
            $invoice_id,
            $tenant_id
        ]);

        // Signature: the column is touched ONLY when the user explicitly does something.
        //  - removeSignature flag          -> clear it
        //  - brand-new cropped data:image  -> save new file, store its filename
        //  - existing filename of THIS tenant -> re-use it
        // In every other case (the normal edit where the front end sends null) the
        // saved signature is left exactly as it is, so editing an old invoice can
        // never wipe its signature.
        if (!empty($input['removeSignature'])) {
            $pdo->prepare("UPDATE sale_invoice SET signature = NULL WHERE id = ? AND tenant_id = ?")
                ->execute([$invoice_id, $tenant_id]);
        } elseif (!empty($input['signature']) && is_string($input['signature'])) {
            if (strpos($input['signature'], 'data:image') === 0) {
                $signatureFileName = saveInvoiceSignature($input['signature'], $tenant_id, $invoice_id);
                if ($signatureFileName) {
                    $pdo->prepare("UPDATE sale_invoice SET signature = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$signatureFileName, $invoice_id, $tenant_id]);
                }
            } else {
                // Filename re-use: verify it belongs to this tenant's own signatures
                // (blocks path traversal / cross-tenant access), same check as pos-add.php.
                $chk = $pdo->prepare("SELECT 1 FROM sale_invoice WHERE tenant_id = ? AND signature = ? LIMIT 1");
                $chk->execute([$tenant_id, $input['signature']]);
                if ($chk->fetchColumn()) {
                    $pdo->prepare("UPDATE sale_invoice SET signature = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$input['signature'], $invoice_id, $tenant_id]);
                }
            }
        }

        // Delete existing items and related records
        $pdo->prepare("DELETE FROM sale_invoice_taxes WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM sale_invoice_items WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'sale_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'sale_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        
        // Delete existing receive voucher(s) and their accounting entries — a split
        // payment produces one receive_voucher row per method, so all matching rows
        // for this bill_no must be wiped before re-creating them below.
        $billStmt = $pdo->prepare("SELECT bill_no FROM sale_invoice WHERE id = ?");
        $billStmt->execute([$invoice_id]);
        $billNo = $billStmt->fetchColumn();

        $rvStmt = $pdo->prepare("SELECT id FROM receive_voucher WHERE bill_no = ? AND tenant_id = ?");
        $rvStmt->execute([$billNo, $tenant_id]);
        $existingRvIds = $rvStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($existingRvIds as $existingRvId) {
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
        discount_amount, extra_discount_amount, trade_offer_percent, trade_offer_amount,
        tax_percent, tax_amount, foc_quantity, net_amount, parent_row_id,
        piece, carton, dozen, scheme, batch_no, expiry_date, created_by, updated_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $itemIdMap[$index + 1] = $pdo->lastInsertId();

            // Skip stock ledger for Draft status
            if ($status === 'Posted') {
                // Get product cost price
                $costPrice = getCostPrice($pdo, $tenant_id, $item['productId'], $input['branchId'], $item['uomId']);

                // Get product's inventory account (must be re-fetched, not assumed —
                // an edit's stock_ledger rows must land on the product's CURRENT account,
                // otherwise this column silently falls back to the table's DEFAULT 33
                // (Trading Inventory), splitting stock reports for products reclassified
                // to a different inventory account, e.g. Finished Goods).
                $productId = intval($item['productId']);
                $productStmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ? AND tenant_id = ?");
                $productStmt->execute([$productId, $tenant_id]);
                $inventoryAccountId = intval($productStmt->fetchColumn() ?: 33);

                // Insert stock ledger for quantity sold (use actual quantity, not converted —
                // stock-position.php's reports convert qty_out to base units themselves using
                // unit_id at read time; pre-converting here while still storing the original
                // unit_id double-converts and produces wildly wrong stock figures. This matches
                // pos-add.php's insert exactly.)
                $stock_stmt = $pdo->prepare("
                        INSERT INTO stock_ledger (
                            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                            qty_out, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
                        ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, ?, ?, 'Sale Invoice', ?)
                    ");
                $stock_stmt->execute([
                    $tenant_id,
                    $inventoryAccountId,
                    intval($input['branchId']),
                    $productId,
                    $invoice_id,
                    floatval($item['quantity']),
                    $costPrice,
                    intval($item['uomId']),
                    !empty($item['batchNo']) ? $item['batchNo'] : null,
                    !empty($item['batchExpiryDate']) ? $item['batchExpiryDate'] : null,
                    $input['saleDate']
                ]);

                // Insert stock ledger for FOC quantity if exists
                if (isset($item['focQty']) && $item['focQty'] > 0) {
                    // Use base unit ID for FOC quantity (mirrors pos-add.php)
                    $focUnitId = $item['focUnitId'] ?? null;
                    if (!$focUnitId || $focUnitId === '') {
                        $unitStmt = $pdo->prepare("SELECT is_base_unit, base_unit_id FROM uom WHERE id = ?");
                        $unitStmt->execute([intval($item['uomId'])]);
                        $unitData = $unitStmt->fetch();
                        if ($unitData) {
                            $focUnitId = $unitData['is_base_unit'] == 1 ? $item['uomId'] : ($unitData['base_unit_id'] ?? $item['uomId']);
                        } else {
                            $focUnitId = $item['uomId'];
                        }
                    }
                    $focUnitId = intval($focUnitId);

                    $foc_stmt = $pdo->prepare("
                        INSERT INTO stock_ledger (
                            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                            qty_out, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
                        ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, ?, ?, 'Sale Invoice - FOC', ?)
                    ");
                    $foc_stmt->execute([
                        $tenant_id,
                        $inventoryAccountId,
                        intval($input['branchId']),
                        $productId,
                        $invoice_id,
                        floatval($item['focQty']),
                        0,
                        $focUnitId,
                        !empty($item['batchNo']) ? $item['batchNo'] : null,
                        !empty($item['batchExpiryDate']) ? $item['batchExpiryDate'] : null,
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

        // Insert receive voucher(s) if amount paid > 0 and status is Posted — see
        // pos-add.php for the full explanation of the one-voucher-per-split design.
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
                        tenant_id, currency_id, voucher_number, voucher_date, customer_id,
                        bill_no, amount, payment_method_id, bank_account_id, project_id, created_by, updated_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $rvStmt->execute([
                    $tenant_id,
                    $input['currencyId'],
                    $voucherNumber,
                    $input['saleDate'],
                    $input['customerId'],
                    $billNo,
                    $splitAmount,
                    $splitMethodId,
                    $splitBankAccountId,
                    (!empty($input['projectId']) && intval($input['projectId']) > 0) ? intval($input['projectId']) : null,
                    $user_id,
                    $user_id
                ]);

                $voucher_id = $pdo->lastInsertId();

                // Get account_id for cash/bank
                if ($splitMethodId === 1) {
                    $cashAccountId = 1;
                } else {
                    $cashAccountId = 1; // default fallback
                    if (!empty($splitBankAccountId)) {
                        $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
                        $bankStmt->execute([$splitBankAccountId]);
                        $fetchedAccountId = $bankStmt->fetchColumn();
                        if ($fetchedAccountId !== false && $fetchedAccountId !== '' && $fetchedAccountId !== null) {
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

        $newInvoice = [
            'bill_no' => $oldInvoice['bill_no'],
            'currency_id' => $input['currencyId'],
            'sale_date' => $input['saleDate'],
            'customer_id' => $input['customerId'],
            'sub_account_id' => $input['subAccountId'] ?? null,
            'company_id' => $input['companyId'] ?? null,
            'branch_id' => $input['branchId'],
            'previous_balance' => extractBalanceAmount($input['previousBalance'] ?? '0.00'),
            'sale_officer_id' => $input['salesOfficerId'] ?? null,
            'supplier_man_id' => $input['supplierManId'] ?? null,
            'brand_id' => $input['brandId'] ?? null,
            'sale_order_id' => $input['saleOrderId'] ?? null,
            'bilty_no' => $input['biltyNo'] ?? null,
            'transport_name' => $input['transportName'] ?? null,
            'customer_phone' => $input['customerPhone'] ?? null,
            'total_bill' => $input['totalBill'],
            'total_discount_percent' => $input['totalDiscountPercent'] ?? 0,
            'total_discount_amount' => $input['totalDiscountAmount'] ?? 0,
            'extra_discount_1_percent' => $input['extraDiscount1Percent'] ?? 0,
            'extra_discount_1_amount' => $input['extraDiscount1Amount'] ?? 0,
            'extra_discount_2_percent' => $input['extraDiscount2Percent'] ?? 0,
            'extra_discount_2_amount' => $input['extraDiscount2Amount'] ?? 0,
            'net_amount' => $input['netAmount'],
            'remarks' => $input['remarks'] ?? null,
            'ship_to' => $input['shipTo'] ?? null,
            'status' => $status,
            'project_id' => $input['projectId'] ?? null,
            'container_id' => (!empty($input['containerId']) && intval($input['containerId']) > 0) ? intval($input['containerId']) : null
        ];
        $changeDescriptions = buildChangeDescription($oldInvoice, $newInvoice, [
            'sale_date' => 'Sale Date', 'customer_id' => 'Customer', 'branch_id' => 'Branch',
            'sale_officer_id' => 'Sales Officer', 'supplier_man_id' => 'Supplier Man', 'brand_id' => 'Brand',
            'bilty_no' => 'Bilty No', 'transport_name' => 'Transport', 'customer_phone' => 'Phone No', 'total_bill' => 'Total Bill',
            'total_discount_percent' => 'Discount %', 'total_discount_amount' => 'Discount Amount',
            'net_amount' => 'Net Amount', 'remarks' => 'Remarks', 'ship_to' => 'Ship To',
            'status' => 'Status', 'project_id' => 'Project', 'container_id' => 'Container'
        ]);
        // Flat comparison against sale_invoice_items rows (same shape submitted items use)
        $newItemsFlat = array_map(function ($item) {
            return [
                'product_id' => $item['productId'], 'uom_id' => $item['uomId'], 'quantity' => $item['quantity'],
                'sale_price' => $item['salePrice'], 'discount_percent' => $item['discountPercent'] ?? 0,
                'tax_percent' => $item['taxPercent'] ?? 0, 'foc_quantity' => $item['focQty'] ?? 0
            ];
        }, $input['items']);
        if (itemsChanged($oldItems, $newItemsFlat, ['product_id', 'uom_id', 'quantity', 'sale_price', 'discount_percent', 'tax_percent', 'foc_quantity'])) {
            $changeDescriptions[] = 'Items updated';
        }
        $editDescription = !empty($changeDescriptions)
            ? "Updated sale invoice \"{$oldInvoice['bill_no']}\": " . implode('; ', $changeDescriptions)
            : "Updated sale invoice \"{$oldInvoice['bill_no']}\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Sale Invoice', $invoice_id, $oldInvoice['bill_no'], $editDescription, $oldInvoice, $newInvoice);

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
// A grocery-style product (UOM Group, e.g. Bag/Kg/Gram) can have stock_ledger
// rows recorded in different units across its history. unit_cost on each row
// is "cost for one of THAT row's own unit", so rows must be normalized to a
// common (base-unit) footing before FIFO/LIFO/AVCO can compare/average them —
// mirrors pos-add.php's identical fix.
function getUnitCostFactor($pdo, $product_id, $unit_id)
{
    if (!$unit_id) return 1.0;

    $stmt = $pdo->prepare("SELECT conversion_factor FROM product_uom_conversions WHERE product_id = ? AND uom_id = ?");
    $stmt->execute([$product_id, $unit_id]);
    $perProductFactor = $stmt->fetchColumn();
    if ($perProductFactor !== false && $perProductFactor !== null && (float)$perProductFactor > 0) {
        return (float)$perProductFactor;
    }

    $stmt = $pdo->prepare("SELECT is_base_unit, conversion_factor FROM uom WHERE id = ?");
    $stmt->execute([$unit_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return 1.0;
    if ((int)$row['is_base_unit'] === 1) return 1.0;
    $factor = (float)$row['conversion_factor'];
    return $factor > 0 ? $factor : 1.0;
}

function getCostPrice($pdo, $tenant_id, $product_id, $branch_id, $target_unit_id = null)
{
    // Get inventory valuation method
    $methodStmt = $pdo->prepare("SELECT inventory_valuation_method FROM companies WHERE tenant_id = ? LIMIT 1");
    $methodStmt->execute([$tenant_id]);
    $method = $methodStmt->fetchColumn() ?: 'FIFO';

    $costPrice = 0; // always accumulated as a per-base-unit rate below

    if ($method === 'FIFO' || $method === 'LIFO') {
        $order = $method === 'FIFO' ? 'ASC' : 'DESC';
        $stmt = $pdo->prepare("
            SELECT unit_cost, unit_id
            FROM stock_ledger
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND qty_in > 0 AND unit_cost > 0
            ORDER BY transaction_date $order, id $order
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $product_id, $branch_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $rowFactor = getUnitCostFactor($pdo, $product_id, $row['unit_id']);
            $costPrice = $row['unit_cost'] / $rowFactor;
        }
    } elseif ($method === 'AVCO') {
        $stmt = $pdo->prepare("
            SELECT unit_cost, unit_id, qty_in
            FROM stock_ledger
            WHERE tenant_id = ? AND product_id = ? AND branch_id = ? AND qty_in > 0 AND unit_cost > 0
        ");
        $stmt->execute([$tenant_id, $product_id, $branch_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalBaseQty = 0;
        $totalBaseValue = 0;
        foreach ($rows as $row) {
            $rowFactor = getUnitCostFactor($pdo, $product_id, $row['unit_id']);
            $baseQty = $row['qty_in'] * $rowFactor;
            $baseCost = $row['unit_cost'] / $rowFactor;
            $totalBaseQty += $baseQty;
            $totalBaseValue += $baseQty * $baseCost;
        }
        $costPrice = $totalBaseQty > 0 ? ($totalBaseValue / $totalBaseQty) : 0;
    }

    // If no cost found from stock ledger, use purchase_price from products
    // table (already stored per-base-unit).
    if ($costPrice == 0) {
        $stmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $costPrice = $stmt->fetchColumn() ?: 0;
    }

    if ($target_unit_id) {
        $targetFactor = getUnitCostFactor($pdo, $product_id, $target_unit_id);
        $costPrice = $costPrice * $targetFactor;
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

// Decode a base64 data-URL PNG (from the client-side signature crop) and save it
// to disk, returning the stored filename to be saved in sale_invoice.signature.
// Same folder + naming as pos-add.php's saveInvoiceSignature() (both files live in
// server/api/sale/grocery_sale_invoice/, so the relative path is identical).
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