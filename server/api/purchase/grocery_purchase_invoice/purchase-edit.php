<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once 'purchase-invoice-tax-helper.php';

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

// A grocery-style product (UOM Group, e.g. Bag/Kg/Gram) can have a single row
// carry multiple unit entries. The client sends ONE purchasePrice per-base-unit
// (e.g. 140/Kg) for the whole row — this scales it to whichever specific unit
// a given unitEntry actually uses (e.g. 140*50=7000 for a Bag entry). Mirrors
// purchase-add.php's identical helper (kept here too since this endpoint has
// its own INSERT logic for the PUT/update path).
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
                pi.*,
                s.supplier_name,
                s.supplier_code,
                b.branch_name,
                b.branch_type,
                b.branch_code,
                pb.branch_name as parent_branch_name,
                c.symbol as currency_symbol,
                c.code as currency_code,
                c.name as currency_name,
                po.bill_no as purchase_order_bill_no,
                sa.sub_account_name,
                co.company_name,
                co.legal_name,
                co.email as company_email,
                co.phone as company_phone,
                co.address as company_address,
                co.city as company_city,
                co.state as company_state,
                co.zipcode as company_zipcode,
                co.logo_url,
                pr.project_code,
                pr.project_name
            FROM purchase_invoice pi
            LEFT JOIN suppliers s ON pi.supplier_id = s.id
            LEFT JOIN branches b ON pi.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies c ON pi.currency_id = c.id
            LEFT JOIN purchase_order po ON pi.purchase_order_id = po.id
            LEFT JOIN supplier_sub_accounts sa ON pi.sub_account_id = sa.id
            LEFT JOIN companies co ON pi.company_id = co.id
            LEFT JOIN projects pr ON pi.project_id = pr.id
            WHERE pi.id = ? AND pi.tenant_id = ?
        ");
        $stmt->execute([$invoice_id, $tenant_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            exit;
        }
        
        // Get invoice items grouped by product
        $itemStmt = $pdo->prepare("
            SELECT
                pii.*,
                p.name as product_name,
                p.code as product_code,
                p.uom_type,
                p.uom_group_id,
                p.default_unit_id,
                p.stock_affects,
                p.invoice_affects,
                u.uom_name
            FROM purchase_invoice_items pii
            LEFT JOIN products p ON pii.product_id = p.id
            LEFT JOIN uom u ON pii.uom_id = u.id
            WHERE pii.purchase_invoice_id = ? AND pii.tenant_id = ?
            ORDER BY COALESCE(pii.parent_row_id, pii.id), pii.id
        ");
        $itemStmt->execute([$invoice_id, $tenant_id]);
        $rawItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$rawItems) {
            $items = [];
            $children = [];
        } else {
            // ✅ FIX: Separate children (parent_row_id set) from main entries
            $children = [];
            $mainItems = [];  // Only rows with parent_row_id = NULL
            
            foreach ($rawItems as $item) {
                if (!empty($item['parent_row_id'])) {
                    // This is a child row (different UOM of a parent)
                    $children[] = [
                        'id' => $item['id'],
                        'parent_row_id' => $item['parent_row_id'],
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'] ?? 'Unknown',
                        'product_code' => $item['product_code'] ?? '',
                        'uom_id' => $item['uom_id'],
                        'quantity' => floatval($item['quantity'] ?? 0),
                        'purchase_price' => floatval($item['purchase_price'] ?? 0),
                        'stock_affects' => $item['stock_affects'] ?? 1,
                        'invoice_affects' => $item['invoice_affects'] ?? 1
                    ];
                } else {
                    // This is a main entry (parent_row_id is NULL)
                    $mainItems[] = $item;
                }
            }

            // ✅ FIX: Group ONLY if multiple rows have same product_id AND same non-zero amounts
            // Otherwise, keep them as separate line items
            $groupedItems = [];
            $itemIndex = 0;
            
            // Index main items by their ID for reference
            $mainItemsById = [];
            foreach ($mainItems as $item) {
                $mainItemsById[$item['id']] = $item;
            }

            // Re-group the flat per-unit-entry rows back into one line per original
            // Add-Invoice row. When a product is sold/bought across multiple unit
            // columns in one row (e.g. Bag + Kg + Gram), the save endpoint inserts
            // one purchase_invoice_items row PER unit entry, all sharing the same
            // parent_row_id (NULL, since that field is actually for cross-row
            // product-variant linking, not within-row unit linking) — so it can't be
            // used to tell sibling unit-entries apart. The real signal: only the
            // FIRST entry of a row carries the row's real gross/net amount; every
            // other entry of that same row is inserted immediately after with
            // gross_amount = net_amount = 0 (see purchase-add.php's $isFirstEntry).
            // So: a row with gross_amount > 0 starts a new group; any immediately
            // following same-product/same-batch row with gross_amount = 0 joins it.
            $currentGroupIndex = null;
            foreach ($mainItems as $item) {
                $hasOwnAmount = floatval($item['gross_amount'] ?? 0) > 0 || floatval($item['net_amount'] ?? 0) > 0;
                $sameAsCurrentGroup = $currentGroupIndex !== null
                    && $groupedItems[$currentGroupIndex]['product_id'] == $item['product_id']
                    && $groupedItems[$currentGroupIndex]['batch_no'] === ($item['batch_no'] ?? '');

                if ($hasOwnAmount || !$sameAsCurrentGroup) {
                    // Starts a new line item
                    $itemIndex++;
                    $currentGroupIndex = $itemIndex;
                    $groupedItems[$itemIndex] = [
                        'id' => $item['id'],
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'] ?? 'Unknown',
                        'product_code' => $item['product_code'] ?? '',
                        'uom_type' => $item['uom_type'] ?? 'unit',
                        'uom_group_id' => $item['uom_group_id'],
                        'default_unit_id' => $item['default_unit_id'],
                        'purchase_price' => floatval($item['purchase_price'] ?? 0),
                        'gross_amount' => floatval($item['gross_amount'] ?? 0),
                        'discount_percent' => floatval($item['discount_percent'] ?? 0),
                        'discount_amount' => floatval($item['discount_amount'] ?? 0),
                        'extra_discount_percent' => floatval($item['extra_discount_percent'] ?? 0),
                        'trade_offer_percent' => floatval($item['trade_offer_percent'] ?? 0),
                        'trade_offer_amount' => floatval($item['trade_offer_amount'] ?? 0),
                        'tax_percent' => floatval($item['tax_percent'] ?? 0),
                        'tax_amount' => floatval($item['tax_amount'] ?? 0),
                        'foc_quantity' => floatval($item['foc_quantity'] ?? 0),
                        'net_amount' => floatval($item['net_amount'] ?? 0),
                        'size' => $item['size'] ?? '',
                        'batch_no' => $item['batch_no'] ?? '',
                        'batch_expiry_date' => $item['batch_expiry_date'] ?? '',
                        'unit_entries' => []
                    ];
                }

                $groupedItems[$currentGroupIndex]['unit_entries'][] = [
                    'uom_id' => $item['uom_id'],
                    'uom_name' => $item['uom_name'] ?? 'Unknown',
                    'quantity' => floatval($item['quantity'] ?? 0),
                    // Per-unit rate for this specific entry (e.g. per-Bag vs per-Kg) —
                    // the grouped item's own 'purchase_price' only reflects the FIRST
                    // entry's unit, so print/display code needing each entry's own
                    // rate should read this instead.
                    'purchase_price' => floatval($item['purchase_price'] ?? 0)
                ];
            }

            $items = array_values($groupedItems);
        }
        
        $taxes = getPurchaseInvoiceTaxes($pdo, $invoice_id, $tenant_id);

        echo json_encode([
            'success' => true,
            'invoice' => $invoice,
            'items'   => $items ?? [],
            'children' => $children ?? [],
            'invoice_taxes' => $taxes
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error loading invoice: ' . $e->getMessage()]);
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
            SELECT bill_no, company_id, currency_id, purchase_date, supplier_id, branch_id, container_id, previous_balance,
                   total_bill, total_discount_percent, total_discount_amount, total_tax_percent, total_tax_amount,
                   shipping_fees, net_amount, supplier_invoice_no, purchase_order_id, bilty_no, transport_name, remarks, project_id
            FROM purchase_invoice WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$invoice_id, $tenant_id]);
        $oldInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldInvoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT product_id, uom_id, quantity, purchase_price, discount_percent, tax_percent FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ? ORDER BY product_id, uom_id, id");
        $stmt->execute([$invoice_id, $tenant_id]);
        $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        // Update purchase invoice
        $stmt = $pdo->prepare("
            UPDATE purchase_invoice SET
                company_id = ?, currency_id = ?, purchase_date = ?, supplier_id = ?, branch_id = ?, container_id = ?,
                previous_balance = ?, total_bill = ?, total_discount_percent = ?,
                total_discount_amount = ?, total_tax_percent = ?, total_tax_amount = ?,
                shipping_fees = ?, shipping_fees_type = ?, net_amount = ?, supplier_invoice_no = ?, 
                supplier_invoice_date = ?, purchase_order_id = ?, bilty_no = ?, transport_name = ?, remarks = ?, sub_account_id = ?, project_id = ?, assoc_company_id = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $input['companyId'],
            $input['currencyId'],
            $input['purchaseDate'],
            $input['supplierId'],
            $input['branchId'],
            $input['containerId'] ?? null,
            extractBalanceAmount($input['previousBalance'] ?? '0.000'),
            $input['totalBill'],
            $input['totalDiscountPercent'] ?? 0.00,
            $input['totalDiscountAmount'] ?? 0.00,
            $input['totalTaxPercent'] ?? 0.00,
            $input['totalTaxAmount'] ?? 0.00,
            $input['shippingFees'] ?? 0.00,
            $input['shippingFeesType'] ?? 'add',
            $input['netAmount'],
            $input['supplierInvoiceNo'],
            $input['supplierInvoiceDate'],
            $input['purchaseOrderId'] ?? null,
            $input['biltyNo'],
            $input['transportName'],
            $input['remarks'] ?? null,
            $input['subAccountId'] ?? null,
            $input['projectId'] ?? null,
            $input['assocCompanyId'] ?? null,
            $user_id,
            $invoice_id,
            $tenant_id
        ]);
        
        // Delete existing items and related records
        $pdo->prepare("DELETE FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
        
        // Insert new items with dynamic UOM system
        $item_stmt = $pdo->prepare("
            INSERT INTO purchase_invoice_items (
                tenant_id, purchase_invoice_id, product_id, uom_id,
                quantity, purchase_price, gross_amount, discount_percent,
                discount_amount, extra_discount_percent, trade_offer_percent, trade_offer_amount,
                tax_percent, tax_amount, foc_quantity, net_amount, size,
                batch_no, batch_expiry_date, parent_row_id,
                created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $itemRowCounter = 0;
        $itemIdMap = [];

        foreach ($input['items'] as $item) {
            $itemRowCounter++;

            $parentRowId = null;
            if (isset($item['parentRowId']) && $item['parentRowId'] !== null) {
                $parentRowId = $itemIdMap[$item['parentRowId']] ?? null;
            }

            $isFirstEntry = true;
            foreach ($item['unitEntries'] as $unitEntry) {
                $entryUnitFactor = getUnitCostFactor($pdo, $item['productId'], $unitEntry['uomId']);
                $entryPrice = $item['purchasePrice'] * $entryUnitFactor;
                $item_stmt->execute([
                    $tenant_id,
                    $invoice_id,
                    $item['productId'],
                    $unitEntry['uomId'],
                    $unitEntry['quantity'],
                    $entryPrice,
                    $isFirstEntry ? $item['grossAmount'] : 0,
                    $item['discountPercent'] ?? 0.00,
                    $isFirstEntry ? $item['discountAmount'] : 0,
                    $item['extraDiscountPercent'] ?? 0.00,
                    $item['tradeOfferPercent'] ?? 0.00,
                    $isFirstEntry ? $item['tradeOfferAmount'] : 0,
                    $item['taxPercent'] ?? 0.00,
                    $isFirstEntry ? $item['taxAmount'] : 0,
                    $item['focQty'] ?? 0,
                    $isFirstEntry ? $item['netAmount'] : 0,
                    $item['size'] ?? null,
                    $item['batchNo'] ?? null,
                    $item['batchExpiryDate'] ?? null,
                    $parentRowId,
                    $user_id,
                    $user_id
                ]);

                if ($isFirstEntry) {
                    $itemIdMap[$itemRowCounter] = $pdo->lastInsertId();
                }

                $stockAffects = $item['stockAffects'] ?? 1;
                if ($stockAffects == 1) {
                    $product_stmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
                    $product_stmt->execute([$item['productId']]);
                    $account_id = $product_stmt->fetchColumn();
                    
                    $stock_stmt = $pdo->prepare("
                        INSERT INTO stock_ledger (
                            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                            qty_in, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
                        ) VALUES (?, ?, ?, ?, 'purchase_invoice', ?, ?, ?, ?, ?, ?, 'Purchase Invoice', ?)
                    ");
                    $stock_stmt->execute([
                        $tenant_id,
                        $account_id,
                        $input['branchId'],
                        $item['productId'],
                        $invoice_id,
                        $unitEntry['quantity'],
                        $entryPrice,
                        $unitEntry['uomId'],
                        $item['batchNo'] ?? null,
                        $item['batchExpiryDate'] ?? null,
                        $input['purchaseDate']
                    ]);
                }
                
                $isFirstEntry = false;
            }

            // Insert stock ledger for FOC quantity if exists — matches Sale
            // Invoice's handling ("Sale Invoice - FOC"). Added once per item
            // (not per unit entry) so a bonus quantity isn't multiplied
            // across a multi-unit line. Without this, FOC units were saved
            // on the item row but never entered stock_ledger, so they never
            // showed up in Stock Position even though physically received.
            if (($stockAffects ?? 1) == 1 && !empty($item['focQty']) && $item['focQty'] > 0) {
                $focUnitId = $item['focUnitId'] ?? null;
                if (!$focUnitId) {
                    $refUnitId = $item['unitEntries'][0]['uomId'] ?? null;
                    if ($refUnitId) {
                        $unitStmt = $pdo->prepare("SELECT is_base_unit, base_unit_id FROM uom WHERE id = ?");
                        $unitStmt->execute([$refUnitId]);
                        $unitData = $unitStmt->fetch();
                        if ($unitData) {
                            $focUnitId = $unitData['is_base_unit'] == 1 ? $refUnitId : ($unitData['base_unit_id'] ?? $refUnitId);
                        } else {
                            $focUnitId = $refUnitId;
                        }
                    }
                }
                if ($focUnitId && isset($account_id)) {
                    $foc_stmt = $pdo->prepare("
                        INSERT INTO stock_ledger (
                            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                            qty_in, unit_cost, unit_id, batch_no, expiry_date, transaction_type, transaction_date
                        ) VALUES (?, ?, ?, ?, 'purchase_invoice', ?, ?, ?, ?, ?, ?, 'Purchase Invoice - FOC', ?)
                    ");
                    $foc_stmt->execute([
                        $tenant_id,
                        $account_id,
                        $input['branchId'],
                        $item['productId'],
                        $invoice_id,
                        $item['focQty'],
                        0,
                        $focUnitId,
                        $item['batchNo'] ?? null,
                        $item['batchExpiryDate'] ?? null,
                        $input['purchaseDate']
                    ]);
                }
            }
        }

        // Get bill number
        $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_invoice WHERE id = ?");
        $billStmt->execute([$invoice_id]);
        $billNo = $billStmt->fetchColumn();
        
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
        if ($input['totalTaxAmount'] > 0) {
            $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['totalTaxAmount'], 0]);
        }

        // 3. Debit - Freight Inward
        if ($input['shippingFees'] > 0) {
            $ledger_stmt->execute([$tenant_id, $invoice_id, 110, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, $input['shippingFees'], 0]);
        }

        // 4. Credit - Trade Creditors (Net Amount)
        $ledger_stmt->execute([$tenant_id, $invoice_id, 14, $input['purchaseDate'], 'Purchase Invoice - ' . $billNo, 0, $input['netAmount']]);

        // 6. Save invoice-level taxes — always reconcile (even when the new list is
        // empty) so a removed/zeroed tax doesn't leave a stale row behind for
        // print/edit to keep reading.
        $invoiceTaxesInput = (isset($input['invoiceTaxes']) && is_array($input['invoiceTaxes'])) ? $input['invoiceTaxes'] : [];
        savePurchaseInvoiceTaxes($pdo, $tenant_id, $invoice_id, $invoiceTaxesInput, $user_id);
        foreach ($invoiceTaxesInput as $tax) {
            $taxAmt = floatval($tax['taxAmount'] ?? 0);
            if ($taxAmt > 0) {
                $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Invoice Tax - ' . $billNo, $taxAmt, 0]);
            }
        }

        $newInvoice = [
            'bill_no' => $oldInvoice['bill_no'],
            'company_id' => $input['companyId'],
            'currency_id' => $input['currencyId'],
            'purchase_date' => $input['purchaseDate'],
            'supplier_id' => $input['supplierId'],
            'branch_id' => $input['branchId'],
            'container_id' => $input['containerId'] ?? null,
            'previous_balance' => extractBalanceAmount($input['previousBalance'] ?? '0.000'),
            'total_bill' => $input['totalBill'],
            'total_discount_percent' => $input['totalDiscountPercent'] ?? 0,
            'total_discount_amount' => $input['totalDiscountAmount'] ?? 0,
            'total_tax_percent' => $input['totalTaxPercent'] ?? 0,
            'total_tax_amount' => $input['totalTaxAmount'] ?? 0,
            'shipping_fees' => $input['shippingFees'] ?? 0,
            'net_amount' => $input['netAmount'],
            'supplier_invoice_no' => $input['supplierInvoiceNo'],
            'purchase_order_id' => $input['purchaseOrderId'] ?? null,
            'bilty_no' => $input['biltyNo'],
            'transport_name' => $input['transportName'],
            'remarks' => $input['remarks'] ?? null,
            'project_id' => $input['projectId'] ?? null
        ];
        $changeDescriptions = buildChangeDescription($oldInvoice, $newInvoice, [
            'purchase_date' => 'Purchase Date', 'supplier_id' => 'Supplier', 'branch_id' => 'Branch',
            'total_bill' => 'Total Bill', 'total_discount_percent' => 'Discount %', 'total_discount_amount' => 'Discount Amount',
            'total_tax_amount' => 'Tax Amount', 'shipping_fees' => 'Shipping Fees', 'net_amount' => 'Net Amount',
            'supplier_invoice_no' => 'Supplier Invoice No', 'bilty_no' => 'Bilty No', 'transport_name' => 'Transport',
            'remarks' => 'Remarks'
        ]);
        $newItemsFlat = [];
        foreach ($input['items'] as $item) {
            foreach ($item['unitEntries'] as $unitEntry) {
                $newItemsFlat[] = [
                    'product_id' => $item['productId'], 'uom_id' => $unitEntry['uomId'], 'quantity' => $unitEntry['quantity'],
                    'purchase_price' => $item['purchasePrice'] * getUnitCostFactor($pdo, $item['productId'], $unitEntry['uomId']),
                    'discount_percent' => $item['discountPercent'] ?? 0, 'tax_percent' => $item['taxPercent'] ?? 0
                ];
            }
        }
        if (itemsChanged($oldItems, $newItemsFlat, ['product_id', 'uom_id', 'quantity', 'purchase_price', 'discount_percent', 'tax_percent'])) {
            $changeDescriptions[] = 'Items updated';
        }
        $editDescription = !empty($changeDescriptions)
            ? "Updated purchase invoice \"{$oldInvoice['bill_no']}\": " . implode('; ', $changeDescriptions)
            : "Updated purchase invoice \"{$oldInvoice['bill_no']}\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Purchase Invoice', $invoice_id, $oldInvoice['bill_no'], $editDescription, $oldInvoice, $newInvoice);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Purchase invoice updated successfully']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to extract numeric amount from balance string
function extractBalanceAmount($balanceString) {
    if (empty($balanceString)) return 0.00;
    
    // Dr = positive (we owe supplier), Cr = negative (supplier owes us)
    $isCr = stripos($balanceString, 'Cr') !== false;
    $amount = preg_replace('/^(Dr|Cr)\s*/i', '', trim($balanceString));
    $amount = floatval($amount);
    return $isCr ? -$amount : $amount;
}