<?php
// CORRECTED ACCOUNTING FOR SALES RETURN
// Replace lines 217-310 in return-add.php with this code

if ($status === 'Posted') {
    // Calculate totals
    $totalGST = 0;
    $totalCOGS = 0;
    
    foreach ($input['items'] as $item) {
        $totalGST += floatval($item['gstAmount'] ?? 0);
        
        // Get COGS for inventory reversal
        $cogsStmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
        $cogsStmt->execute([$item['productId']]);
        $costPrice = $cogsStmt->fetchColumn() ?: 0;
        $totalCOGS += $costPrice * floatval($item['quantity']);
    }
    
    // Get customer's AR account
    $customerStmt = $pdo->prepare("SELECT account_id FROM customers WHERE id = ?");
    $customerStmt->execute([$input['customerId']]);
    $customerAccountId = $customerStmt->fetchColumn() ?: 2; // Default AR account
    
    // Entry 1: Record Sales Return (Net Amount)
    // Dr: Sales Returns & Allowances
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, 18, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $input['saleDate'],
        'Sale Return - ' . $billNo, $input['netAmount']
    ]);
    
    // Cr: Accounts Receivable - Customer
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, credit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, ?, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $customerAccountId, $input['saleDate'],
        'Sale Return - ' . $billNo, $input['netAmount']
    ]);
    
    // Entry 2: Reverse COGS & Restore Inventory
    if ($totalCOGS > 0) {
        // Dr: Inventory
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 3, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Return - Inventory Restore - ' . $billNo, $totalCOGS
        ]);
        
        // Cr: Cost of Goods Sold
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 19, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Return - COGS Reversal - ' . $billNo, $totalCOGS
        ]);
    }
    
    // Entry 3: Reverse GST (if applicable)
    if ($totalGST > 0) {
        // Dr: Sales Tax Payable
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 106, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Return - GST Reversal - ' . $billNo, $totalGST
        ]);
        
        // Cr: Accounts Receivable - Customer
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, credit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, ?, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $customerAccountId, $input['saleDate'],
            'Sale Return - GST Reversal - ' . $billNo, $totalGST
        ]);
    }
}

// Entry 4: If amount refunded > 0, record cash payment
if ($status === 'Posted' && isset($input['amountPaid']) && $input['amountPaid'] > 0) {
    // Get customer's AR account
    $customerStmt = $pdo->prepare("SELECT account_id FROM customers WHERE id = ?");
    $customerStmt->execute([$input['customerId']]);
    $customerAccountId = $customerStmt->fetchColumn() ?: 2;
    
    // Get cash/bank account
    if ($input['paymentMethod'] === 'cash') {
        $cashAccountId = 1;
    } else {
        $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
        $bankStmt->execute([$input['bankAccountId']]);
        $cashAccountId = $bankStmt->fetchColumn();
    }

    // Dr: Accounts Receivable - Customer
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, ?, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $customerAccountId, $input['saleDate'],
        'Sale Return Payment - ' . $billNo, $input['amountPaid']
    ]);

    // Cr: Cash/Bank Account
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, credit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, ?, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $cashAccountId, $input['saleDate'],
        'Sale Return Payment - ' . $billNo, $input['amountPaid']
    ]);
}
