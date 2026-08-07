<?php
require_once '../../../../includes/connection.php';
require_once 'purchase-invoice-tax-helper.php';

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
$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $source_id         = (int)($input['invoice_id'] ?? 0);
    $override_date     = !empty($input['purchase_date']) ? $input['purchase_date'] : null;
    $override_supplier = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;

    if (!$source_id) {
        throw new Exception('invoice_id is required');
    }

    // Fetch source invoice
    $inv = $pdo->prepare("SELECT * FROM purchase_invoice WHERE id = ? AND tenant_id = ?");
    $inv->execute([$source_id, $tenant_id]);
    $invoice = $inv->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) throw new Exception('Source invoice not found');

    // Fetch source items (all rows including multi-unit entries)
    $items_stmt = $pdo->prepare("SELECT * FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ? ORDER BY id");
    $items_stmt->execute([$source_id, $tenant_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch source invoice-level taxes
    $source_taxes = getPurchaseInvoiceTaxes($pdo, $source_id, $tenant_id);

    $final_date     = $override_date ?? date('Y-m-d');
    $final_supplier = $override_supplier ?? $invoice['supplier_id'];
    $fresh_balance  = (float)($input['previous_balance'] ?? 0);

    $pdo->beginTransaction();

    // Generate bill_no tenant-wide inside transaction
    $billStmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM purchase_invoice WHERE tenant_id = ? AND bill_no LIKE 'PUR-%' FOR UPDATE");
    $billStmt->execute([$tenant_id]);
    $lastNumber = (int)$billStmt->fetchColumn();
    $new_bill_no = 'PUR-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

    // Insert duplicate invoice
    $stmt = $pdo->prepare("
        INSERT INTO purchase_invoice (
            tenant_id, company_id, currency_id, bill_no, purchase_date, supplier_id, branch_id, container_id,
            previous_balance, total_bill, total_discount_percent,
            total_discount_amount, total_tax_percent, total_tax_amount,
            shipping_fees, shipping_fees_type, net_amount, supplier_invoice_no, supplier_invoice_date,
            purchase_order_id, bilty_no, transport_name, remarks, sub_account_id, project_id, assoc_company_id, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenant_id,
        $invoice['company_id'],
        $invoice['currency_id'],
        $new_bill_no,
        $final_date,
        $final_supplier,
        $invoice['branch_id'],
        $invoice['container_id'],
        $fresh_balance,
        $invoice['total_bill'],
        $invoice['total_discount_percent'],
        $invoice['total_discount_amount'],
        $invoice['total_tax_percent'],
        $invoice['total_tax_amount'],
        $invoice['shipping_fees'],
        $invoice['shipping_fees_type'],
        $invoice['net_amount'],
        $invoice['supplier_invoice_no'],
        $invoice['supplier_invoice_date'],
        $invoice['purchase_order_id'],
        $invoice['bilty_no'],
        $invoice['transport_name'],
        $invoice['remarks'],
        $invoice['sub_account_id'],
        $invoice['project_id'],
        $invoice['assoc_company_id'],
        $user_id,
        $user_id
    ]);

    $new_invoice_id = $pdo->lastInsertId();

    // Insert duplicate items + stock ledger
    $item_stmt = $pdo->prepare("
        INSERT INTO purchase_invoice_items (
            tenant_id, purchase_invoice_id, product_id, uom_id,
            quantity, purchase_price, gross_amount, discount_percent,
            discount_amount, trade_offer_percent, trade_offer_amount,
            tax_percent, tax_amount, foc_quantity, net_amount, size,
            created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stock_stmt = $pdo->prepare("
        INSERT INTO stock_ledger (
            tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
            qty_in, unit_cost, unit_id, transaction_type, transaction_date
        ) VALUES (?, ?, ?, ?, 'purchase_invoice', ?, ?, ?, ?, 'Purchase Invoice', ?)
    ");

    foreach ($items as $item) {
        $item_stmt->execute([
            $tenant_id,
            $new_invoice_id,
            $item['product_id'],
            $item['uom_id'],
            $item['quantity'],
            $item['purchase_price'],
            $item['gross_amount'],
            $item['discount_percent'],
            $item['discount_amount'],
            $item['trade_offer_percent'],
            $item['trade_offer_amount'],
            $item['tax_percent'],
            $item['tax_amount'],
            $item['foc_quantity'],
            $item['net_amount'],
            $item['size'],
            $user_id,
            $user_id
        ]);

        // Stock ledger — check stock_affects from products table
        if ($item['quantity'] > 0) {
            $product_stmt = $pdo->prepare("SELECT inventory_account_id, stock_affects FROM products WHERE id = ?");
            $product_stmt->execute([$item['product_id']]);
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

            if ($product && (int)($product['stock_affects'] ?? 1) == 1 && $product['inventory_account_id']) {
                $stock_stmt->execute([
                    $tenant_id,
                    $product['inventory_account_id'],
                    $invoice['branch_id'],
                    $item['product_id'],
                    $new_invoice_id,
                    $item['quantity'],
                    $item['purchase_price'],
                    $item['uom_id'],
                    $final_date
                ]);
            }
        }
    }

    // Copy invoice-level taxes
    if (!empty($source_taxes)) {
        $taxes_to_save = array_map(fn($t) => [
            'taxRegimeId'    => $t['tax_regime_id'],
            'taxName'        => $t['tax_name'],
            'ratePercentage' => $t['rate_percentage'],
            'baseAmount'     => $t['base_amount'],
            'taxAmount'      => $t['tax_amount'],
        ], $source_taxes);

        savePurchaseInvoiceTaxes($pdo, $tenant_id, $new_invoice_id, $taxes_to_save, $user_id);
    }

    // Accounting entries
    $ledger_stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Purchase Invoice', 'purchase_invoice', ?, ?, ?, ?, ?, ?)
    ");

    $purchaseAmount = $invoice['total_bill'] - $invoice['total_discount_amount'];

    // 1. Debit - Purchases
    $ledger_stmt->execute([$tenant_id, $new_invoice_id, 19, $final_date, 'Purchase Invoice - ' . $new_bill_no, $purchaseAmount, 0]);

    // 2. Debit - Input Tax Receivable (invoice-level GST)
    if ($invoice['total_tax_amount'] > 0) {
        $ledger_stmt->execute([$tenant_id, $new_invoice_id, 107, $final_date, 'Purchase Invoice - ' . $new_bill_no, $invoice['total_tax_amount'], 0]);
    }

    // 3. Debit - Freight Inward
    if ($invoice['shipping_fees'] > 0) {
        $ledger_stmt->execute([$tenant_id, $new_invoice_id, 110, $final_date, 'Purchase Invoice - ' . $new_bill_no, $invoice['shipping_fees'], 0]);
    }

    // 4. Credit - Purchase Discounts Received
    if ($invoice['total_discount_amount'] > 0) {
        $ledger_stmt->execute([$tenant_id, $new_invoice_id, 103, $final_date, 'Purchase Invoice - ' . $new_bill_no, 0, $invoice['total_discount_amount']]);
    }

    // 5. Credit - Trade Creditors
    $ledger_stmt->execute([$tenant_id, $new_invoice_id, 14, $final_date, 'Purchase Invoice - ' . $new_bill_no, 0, $invoice['net_amount']]);

    // 6. Debit - Input Tax Receivable for each invoice-level tax
    foreach ($source_taxes as $tax) {
        $taxAmt = floatval($tax['tax_amount'] ?? 0);
        if ($taxAmt > 0) {
            $ledger_stmt->execute([$tenant_id, $new_invoice_id, 107, $final_date, 'Purchase Invoice Tax - ' . $new_bill_no, $taxAmt, 0]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'Invoice duplicated successfully',
        'invoice_id' => $new_invoice_id,
        'bill_no'    => $new_bill_no
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
