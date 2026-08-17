<?php
require_once '../../../../includes/connection.php';

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
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $required = ['purchaseDate', 'supplierId', 'branchId', 'items'];
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
    $billStmt = $pdo->prepare("SELECT bill_no FROM purchase_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();
    
    if ($lastBill) {
        $lastNumber = (int)substr($lastBill, 4); // Extract number from PTI-XXXX
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'PTI-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    // Insert purchase tax invoice
    $stmt = $pdo->prepare("
        INSERT INTO purchase_invoice (
            tenant_id, company_id, currency_id, bill_no, purchase_date, supplier_id, branch_id,
            supplier_invoice_no, supplier_invoice_date, remarks, rp_total, tp_total, discount_total, 
            sales_tax_total, advance_tax_percent, advance_tax, net_amount, is_tax, 
            created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
    ");
    
    $stmt->execute([
        $tenant_id,
        $input['companyId'] ?? 1,
        $input['currencyId'] ?? 1,
        $billNo,
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
        $user_id
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // Insert invoice items
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
            $tenant_id,
            $invoice_id,
            $item['productId'],
            $item['uomId'],
            $item['quantity'],
            $item['rpUnitPrice'],
            $item['tpUnitPrice'],
            $item['rpTotalValue'],
            $item['tpTotalValue'],
            $item['discountPercent'],
            $item['discountAmount'],
            $item['salesTax'],
            $item['tpAmount'],
            $item['netAmount'],
            $user_id,
            $user_id
        ]);
        
        // Update stock ledger
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
            $tenant_id,
            $account_id,
            $input['branchId'],
            $item['productId'],
            $invoice_id,
            $item['quantity'],
            $item['tpUnitPrice'],
            $item['uomId'],
            $input['purchaseDate']
        ]);
    }
    
    // Accounting entries
    $ledger_stmt = $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit, credit
        ) VALUES (?, 'Purchase Tax Invoice', 'purchase_invoice', ?, ?, ?, ?, ?, ?)
    ");
    
    // 1. Debit - Purchases
    $ledger_stmt->execute([$tenant_id, $invoice_id, 19, $input['purchaseDate'], 'Purchase Tax Invoice - ' . $billNo, $input['tpTotal'], 0]);
    
    // 2. Debit - Input Tax Receivable
    if ($input['salesTaxTotal'] > 0) {
        $ledger_stmt->execute([$tenant_id, $invoice_id, 107, $input['purchaseDate'], 'Purchase Tax Invoice - ' . $billNo, $input['salesTaxTotal'], 0]);
    }
    
    // 3. Credit - Trade Creditors
    $ledger_stmt->execute([$tenant_id, $invoice_id, 14, $input['purchaseDate'], 'Purchase Tax Invoice - ' . $billNo, 0, $input['netAmount']]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase tax invoice saved successfully',
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