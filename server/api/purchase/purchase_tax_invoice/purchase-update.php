<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoice_id = $input['invoice_id'] ?? null;
    
    if (!$invoice_id) {
        throw new Exception('Invoice ID is required');
    }
    
    // Grab old values before anything changes, for the Activity Log diff
    $stmt = $pdo->prepare("
        SELECT bill_no, company_id, currency_id, purchase_date, supplier_id, branch_id, supplier_invoice_no,
               remarks, rp_total, tp_total, discount_total, sales_tax_total, advance_tax_percent, advance_tax, net_amount
        FROM purchase_invoice WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$invoice_id, $tenant_id]);
    $oldInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldInvoice) {
        throw new Exception('Invoice not found');
    }
    $stmt = $pdo->prepare("SELECT product_id, uom_id, quantity, tp_unit_price, discount_percent, sales_tax FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ? ORDER BY product_id, uom_id, id");
    $stmt->execute([$invoice_id, $tenant_id]);
    $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE purchase_invoice SET
            company_id = ?, currency_id = ?, purchase_date = ?, supplier_id = ?, branch_id = ?,
            supplier_invoice_no = ?, supplier_invoice_date = ?, remarks = ?, rp_total = ?, tp_total = ?, 
            discount_total = ?, sales_tax_total = ?, advance_tax_percent = ?, advance_tax = ?, 
            net_amount = ?, is_tax = 1, updated_by = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([
        $input['companyId'] ?? 1,
        $input['currencyId'] ?? 1,
        $input['purchaseDate'],
        $input['supplierId'],
        $input['branchId'],
        $input['supplierInvoiceNo'] ?? null,
        $input['supplierInvoiceDate'] ?? null,
        $input['remarks'] ?? null,
        $input['rpTotal'],
        $input['tpTotal'],
        $input['discountTotal'],
        $input['salesTaxTotal'],
        $input['advanceTaxPercent'],
        $input['advanceTax'],
        $input['netAmount'],
        $user_id,
        $invoice_id,
        $tenant_id
    ]);
    
    $pdo->prepare("DELETE FROM purchase_invoice_items WHERE purchase_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    $pdo->prepare("DELETE FROM stock_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    $pdo->prepare("DELETE FROM accounting_ledger WHERE reference_table = 'purchase_invoice' AND reference_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);
    
    $item_stmt = $pdo->prepare("
        INSERT INTO purchase_invoice_items (
            tenant_id, purchase_invoice_id, product_id, uom_id, quantity, 
            rp_unit_price, tp_unit_price, rp_total_value, tp_total_value, 
            discount_percent, discount_amount, sales_tax, tp_amount, net_amount, 
            is_tax, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
    ");
    
    foreach ($input['items'] as $item) {
        $item_stmt->execute([
            $tenant_id, $invoice_id, $item['productId'], $item['uomId'], $item['quantity'],
            $item['rpUnitPrice'], $item['tpUnitPrice'], $item['rpTotalValue'], $item['tpTotalValue'],
            $item['discountPercent'], $item['discountAmount'], $item['salesTax'], $item['tpAmount'], 
            $item['netAmount'], $user_id, $user_id
        ]);
        
        $product_stmt = $pdo->prepare("SELECT inventory_account_id FROM products WHERE id = ?");
        $product_stmt->execute([$item['productId']]);
        $account_id = $product_stmt->fetchColumn();
        
        $stock_stmt = $pdo->prepare("
            INSERT INTO stock_ledger (
                tenant_id, account_id, branch_id, product_id, reference_table, reference_id,
                qty_in, unit_cost, unit_id, transaction_type, transaction_date
            ) VALUES (?, ?, ?, ?, 'purchase_invoice', ?, ?, ?, ?, 'Purchase Tax Invoice', ?)
        ");
        $stock_stmt->execute([
            $tenant_id, $account_id, $input['branchId'], $item['productId'], $invoice_id,
            $item['quantity'], $item['tpUnitPrice'], $item['uomId'], $input['purchaseDate']
        ]);
    }
    
    $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_invoice WHERE id = ?");
    $billStmt->execute([$invoice_id]);
    $billNo = $billStmt->fetchColumn();
    
    $ledger_stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Purchase Tax Invoice', 'purchase_invoice', ?, ?, ?, ?, ?, ?)
    ");
    
    $ledger_stmt->execute([$tenant_id, $invoice_id, 19, $input['purchaseDate'], 'Purchase Tax Invoice - ' . $billNo, $input['tpTotal'], 0]);
    
    if ($input['salesTaxTotal'] > 0) {
        $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Tax Invoice - ' . $billNo, $input['salesTaxTotal'], 0]);
    }
    
    $ledger_stmt->execute([$tenant_id, $invoice_id, 14, $input['purchaseDate'], 'Purchase Tax Invoice - ' . $billNo, 0, $input['netAmount']]);
    
    $newInvoice = [
        'bill_no' => $oldInvoice['bill_no'],
        'company_id' => $input['companyId'] ?? 1,
        'currency_id' => $input['currencyId'] ?? 1,
        'purchase_date' => $input['purchaseDate'],
        'supplier_id' => $input['supplierId'],
        'branch_id' => $input['branchId'],
        'supplier_invoice_no' => $input['supplierInvoiceNo'] ?? null,
        'remarks' => $input['remarks'] ?? null,
        'rp_total' => $input['rpTotal'],
        'tp_total' => $input['tpTotal'],
        'discount_total' => $input['discountTotal'],
        'sales_tax_total' => $input['salesTaxTotal'],
        'advance_tax_percent' => $input['advanceTaxPercent'],
        'advance_tax' => $input['advanceTax'],
        'net_amount' => $input['netAmount']
    ];
    $changeDescriptions = buildChangeDescription($oldInvoice, $newInvoice, [
        'purchase_date' => 'Purchase Date', 'supplier_id' => 'Supplier', 'branch_id' => 'Branch',
        'supplier_invoice_no' => 'Supplier Invoice No', 'remarks' => 'Remarks', 'tp_total' => 'TP Total',
        'discount_total' => 'Discount Total', 'sales_tax_total' => 'Sales Tax Total', 'advance_tax' => 'Advance Tax',
        'net_amount' => 'Net Amount'
    ]);
    $newItemsFlat = array_map(function ($item) {
        return [
            'product_id' => $item['productId'], 'uom_id' => $item['uomId'], 'quantity' => $item['quantity'],
            'tp_unit_price' => $item['tpUnitPrice'], 'discount_percent' => $item['discountPercent'], 'sales_tax' => $item['salesTax']
        ];
    }, $input['items']);
    if (itemsChanged($oldItems, $newItemsFlat, ['product_id', 'uom_id', 'quantity', 'tp_unit_price', 'discount_percent', 'sales_tax'])) {
        $changeDescriptions[] = 'Items updated';
    }
    $editDescription = !empty($changeDescriptions)
        ? "Updated purchase tax invoice \"{$oldInvoice['bill_no']}\": " . implode('; ', $changeDescriptions)
        : "Updated purchase tax invoice \"{$oldInvoice['bill_no']}\" - no tracked field changes";
    logActivity($pdo, $tenant_id, $user_id, 'update', 'Purchase Tax Invoice', $invoice_id, $oldInvoice['bill_no'], $editDescription, $oldInvoice, $newInvoice);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Invoice updated successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
