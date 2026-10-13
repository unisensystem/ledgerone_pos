<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';
require_once 'invoice-tax-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $source_id         = (int)($input['invoice_id']      ?? 0);
    $override_date     = !empty($input['sale_date'])      ? $input['sale_date']      : null;
    $override_customer = !empty($input['customer_id'])    ? (int)$input['customer_id'] : null;
    $fresh_balance     = (float)($input['previous_balance'] ?? 0);

    if (!$source_id) throw new Exception('invoice_id is required');

    // Fetch source invoice
    $invStmt = $pdo->prepare("SELECT * FROM sale_invoice WHERE id = ? AND tenant_id = ?");
    $invStmt->execute([$source_id, $tenant_id]);
    $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) throw new Exception('Source invoice not found');

    // Fetch source items (all rows including child rows)
    $itemsStmt = $pdo->prepare("SELECT * FROM sale_invoice_items WHERE sale_invoice_id = ? AND tenant_id = ? ORDER BY COALESCE(parent_row_id, id), id");
    $itemsStmt->execute([$source_id, $tenant_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch source invoice-level taxes
    $sourceTaxes = getInvoiceTaxes($pdo, $source_id, $tenant_id);

    $final_date     = $override_date     ?? date('Y-m-d');
    $final_customer = $override_customer ?? $invoice['customer_id'];

    // Get customer withholding tax info
    $custStmt = $pdo->prepare("SELECT advance_income_tax_percentage FROM customers WHERE id = ?");
    $custStmt->execute([$final_customer]);
    $customer = $custStmt->fetch(PDO::FETCH_ASSOC);
    $withholdingTaxPercent = floatval($customer['advance_income_tax_percentage'] ?? 0);
    $withholdingTaxAmount  = floatval($invoice['net_amount']) * ($withholdingTaxPercent / 100);

    $pdo->beginTransaction();

    // Generate bill_no inside transaction with lock
    $billStmt = $pdo->prepare("SELECT bill_no FROM sale_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();
    $newNum   = $lastBill ? ((int)substr($lastBill, 4)) + 1 : 1;
    $newBillNo = 'SAL-' . str_pad($newNum, 4, '0', STR_PAD_LEFT);

    // Insert duplicate invoice
    $ins = $pdo->prepare("
        INSERT INTO sale_invoice (
            tenant_id, currency_id, bill_no, sale_date, customer_id, sub_account_id,
            company_id, branch_id, container_id, previous_balance, sale_officer_id,
            supplier_man_id, sale_order_id, bilty_no, transport_name, project_id,
            total_bill, total_discount_percent, total_discount_amount, shipping_fees,
            net_amount, withholding_tax_percent, withholding_tax_amount,
            amount_paid_auto_fill, invoice_type, due_date, remarks, status,
            created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $ins->execute([
        $tenant_id,
        $invoice['currency_id'],
        $newBillNo,
        $final_date,
        $final_customer,
        $invoice['sub_account_id'],
        $invoice['company_id'],
        $invoice['branch_id'],
        $invoice['container_id'],
        $fresh_balance,
        $invoice['sale_officer_id'],
        $invoice['supplier_man_id'],
        $invoice['sale_order_id'],
        $invoice['bilty_no'],
        $invoice['transport_name'],
        $invoice['project_id'],
        $invoice['total_bill'],
        $invoice['total_discount_percent'],
        $invoice['total_discount_amount'],
        $invoice['shipping_fees'],
        $invoice['net_amount'],
        $withholdingTaxPercent,
        $withholdingTaxAmount,
        $invoice['amount_paid_auto_fill'] ?? 'yes',
        $invoice['invoice_type'] ?? 'Credit',
        $invoice['due_date'],
        $invoice['remarks'],
        'Posted',
        $user_id,
        $user_id
    ]);
    $newInvoiceId = $pdo->lastInsertId();

    // Copy invoice-level taxes
    if (!empty($sourceTaxes)) {
        $taxesToSave = array_map(fn($t) => [
            'taxRegimeId'    => $t['tax_regime_id'],
            'taxRateId'      => $t['tax_rate_id'],
            'taxName'        => $t['tax_name'],
            'ratePercentage' => $t['rate_percentage'],
            'baseAmount'     => $t['base_amount'],
            'taxAmount'      => $t['tax_amount'],
        ], $sourceTaxes);
        saveInvoiceTaxes($pdo, $tenant_id, $newInvoiceId, $taxesToSave, $user_id);
    }

    // Insert items + stock ledger
    $itemIns = $pdo->prepare("
        INSERT INTO sale_invoice_items (
            tenant_id, sale_invoice_id, product_id, uom_id,
            quantity, sale_price, gross_amount, discount_percent,
            discount_amount, trade_offer_percent, trade_offer_amount,
            tax_percent, tax_amount, foc_quantity, net_amount, parent_row_id,
            piece, carton, dozen, scheme, created_by, updated_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stockIns = $pdo->prepare("
        INSERT INTO stock_ledger (
            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
            qty_out, unit_cost, unit_id, transaction_type, transaction_date
        ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, 'Sale Invoice', ?)
    ");

    $focIns = $pdo->prepare("
        INSERT INTO stock_ledger (
            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
            qty_out, unit_cost, unit_id, transaction_type, transaction_date
        ) VALUES (?, ?, ?, ?, 'sale_invoice', ?, ?, ?, ?, 'Sale Invoice - FOC', ?)
    ");

    // Map old item id -> new item id for parent_row_id resolution
    $itemIdMap = [];

    foreach ($items as $item) {
        $newParentRowId = null;
        if (!empty($item['parent_row_id'])) {
            $newParentRowId = $itemIdMap[$item['parent_row_id']] ?? null;
        }

        $itemIns->execute([
            $tenant_id,
            $newInvoiceId,
            $item['product_id'],
            $item['uom_id'],
            $item['quantity'],
            $item['sale_price'],
            $item['gross_amount'],
            $item['discount_percent'],
            $item['discount_amount'],
            $item['trade_offer_percent'],
            $item['trade_offer_amount'],
            $item['tax_percent'],
            $item['tax_amount'],
            $item['foc_quantity'],
            $item['net_amount'],
            $newParentRowId,
            $item['piece'],
            $item['carton'],
            $item['dozen'],
            $item['scheme'] ?? 'sale_on_tp',
            $user_id,
            $user_id
        ]);
        $newItemId = $pdo->lastInsertId();
        $itemIdMap[$item['id']] = $newItemId;

        // Stock ledger — only for parent rows (no parent_row_id) with qty > 0
        if (empty($item['parent_row_id']) && $item['quantity'] > 0) {
            $prodStmt = $pdo->prepare("SELECT inventory_account_id, stock_affects FROM products WHERE id = ?");
            $prodStmt->execute([$item['product_id']]);
            $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

            if ($product && (int)($product['stock_affects'] ?? 1) == 1) {
                $costPrice = getCostPrice($pdo, $tenant_id, $item['product_id'], $invoice['branch_id']);
                $stockIns->execute([
                    $tenant_id,
                    $product['inventory_account_id'],
                    $invoice['branch_id'],
                    $item['product_id'],
                    $newInvoiceId,
                    $item['quantity'],
                    $costPrice,
                    $item['uom_id'],
                    $final_date
                ]);

                // FOC stock
                if ($item['foc_quantity'] > 0) {
                    $focIns->execute([
                        $tenant_id,
                        $product['inventory_account_id'],
                        $invoice['branch_id'],
                        $item['product_id'],
                        $newInvoiceId,
                        $item['foc_quantity'],
                        0,
                        $item['uom_id'],
                        $final_date
                    ]);
                }
            }
        }
    }

    // Accounting entries
    $ledger = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Sale Invoice', 'sale_invoice', ?, ?, ?, ?, ?, ?)
    ");

    $totalTradeOffer = 0;
    foreach ($items as $item) {
        if (!empty($item['parent_row_id'])) continue;
        $totalTradeOffer += floatval($item['trade_offer_amount']);
    }

    // Debit: Trade Debtors (Net Amount)
    $ledger->execute([$tenant_id, $newInvoiceId, 2, $final_date, 'Sale Invoice - '.$newBillNo, $invoice['net_amount'], 0]);

    // Debit: AIT Receivable (Withholding Tax)
    if ($withholdingTaxAmount > 0) {
        $ledger->execute([$tenant_id, $newInvoiceId, 137, $final_date, 'AIT Withholding - '.$newBillNo, $withholdingTaxAmount, 0]);
    }

    // Debit: Trade Discount (Trade Offer)
    if ($totalTradeOffer > 0) {
        $ledger->execute([$tenant_id, $newInvoiceId, 105, $final_date, 'Sale Invoice - '.$newBillNo, $totalTradeOffer, 0]);
    }

    // Credit: Sales Revenue (Net Amount) — Discount Allowed and Sales Tax
    // Payable no longer get their own ledger lines; both are folded into
    // Net Amount on Trade Debtors/Sales Revenue instead.
    $ledger->execute([$tenant_id, $newInvoiceId, 9, $final_date, 'Sale Invoice - '.$newBillNo, 0, $invoice['net_amount']]);

    logActivity(
        $pdo, $tenant_id, $user_id, 'create', 'Sale Tax Invoice', $newInvoiceId, $newBillNo,
        "Created sale tax invoice \"$newBillNo\" as a duplicate of \"{$invoice['bill_no']}\"",
        null,
        ['bill_no' => $newBillNo, 'customer_id' => $final_customer, 'sale_date' => $final_date, 'net_amount' => (float)$invoice['net_amount'], 'duplicated_from' => $invoice['bill_no']]
    );

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'Invoice duplicated successfully',
        'invoice_id' => $newInvoiceId,
        'bill_no'    => $newBillNo
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

function getCostPrice($pdo, $tenant_id, $product_id, $branch_id) {
    $methodStmt = $pdo->prepare("SELECT inventory_valuation_method FROM companies WHERE tenant_id = ? LIMIT 1");
    $methodStmt->execute([$tenant_id]);
    $method = $methodStmt->fetchColumn() ?: 'FIFO';

    if ($method === 'FIFO') {
        $stmt = $pdo->prepare("SELECT unit_cost FROM stock_ledger WHERE tenant_id=? AND product_id=? AND branch_id=? AND qty_in>0 AND unit_cost>0 ORDER BY transaction_date ASC, id ASC LIMIT 1");
    } elseif ($method === 'LIFO') {
        $stmt = $pdo->prepare("SELECT unit_cost FROM stock_ledger WHERE tenant_id=? AND product_id=? AND branch_id=? AND qty_in>0 AND unit_cost>0 ORDER BY transaction_date DESC, id DESC LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT SUM(qty_in*unit_cost)/NULLIF(SUM(qty_in),0) FROM stock_ledger WHERE tenant_id=? AND product_id=? AND branch_id=? AND qty_in>0 AND unit_cost>0");
    }
    $stmt->execute([$tenant_id, $product_id, $branch_id]);
    $cost = $stmt->fetchColumn() ?: 0;

    if (!$cost) {
        $stmt = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $cost = $stmt->fetchColumn() ?: 0;
    }
    return $cost;
}
