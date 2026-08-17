<?php
// FINAL CORRECTED ACCOUNTING FOR SALES RETURN
// Replace lines 217-310 in return-add.php with this code

if ($status === 'Posted') {
    // Calculate totals
    $totalGST = 0;
    
    foreach ($input['items'] as $item) {
        $totalGST += floatval($item['gstAmount'] ?? 0);
    }
    
    // Entry 1: Record Sales Return
    // Dr: Sales Returns & Allowances (Net Amount excluding GST)
    $netAmountExcludingGST = $input['netAmount'] - $totalGST;
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, 18, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $input['saleDate'],
        'Sale Return - ' . $billNo, $netAmountExcludingGST
    ]);
    
    // Dr: Sales Tax Payable (GST reversal)
    if ($totalGST > 0) {
        $pdo->prepare("
            INSERT INTO accounting_ledger (
                tenant_id, transaction_type, reference_table, reference_id,
                account_id, date, description, debit
            ) VALUES (?, 'Sale Return', 'sale_return', ?, 106, ?, ?, ?)
        ")->execute([
            $tenant_id, $invoice_id, $input['saleDate'],
            'Sale Return - GST Reversal - ' . $billNo, $totalGST
        ]);
    }
    
    // Cr: Trade Debtors (Total Net Amount)
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, credit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, 2, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $input['saleDate'],
        'Sale Return - ' . $billNo, $input['netAmount']
    ]);
}

// Entry 2: If amount refunded > 0, record cash payment
if ($status === 'Posted' && isset($input['amountPaid']) && $input['amountPaid'] > 0) {
    // Get cash/bank account
    if ($input['paymentMethod'] === 'cash') {
        $cashAccountId = 1;
    } else {
        $bankStmt = $pdo->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
        $bankStmt->execute([$input['bankAccountId']]);
        $cashAccountId = $bankStmt->fetchColumn();
    }

    // Dr: Trade Debtors
    $pdo->prepare("
        INSERT INTO accounting_ledger (
            tenant_id, transaction_type, reference_table, reference_id,
            account_id, date, description, debit
        ) VALUES (?, 'Sale Return', 'sale_return', ?, 2, ?, ?, ?)
    ")->execute([
        $tenant_id, $invoice_id, $input['saleDate'],
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
