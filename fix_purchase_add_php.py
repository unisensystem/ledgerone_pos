f = open(r'c:/xampp/htdocs/ledgerone_erp/server/api/purchase/purchase_invoice/purchase-add.php', 'r', encoding='utf-8')
d = f.read()
f.close()

old = """    // Generate sequential bill number
    $billStmt = $pdo->prepare(\"SELECT bill_no FROM purchase_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1\");
    $billStmt->execute([$tenant_id]);
    $lastBill = $billStmt->fetchColumn();
    
    if ($lastBill) {
        $lastNumber = (int)substr($lastBill, 4); // Extract number from PUR-XXXX
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    $billNo = 'PUR-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);"""

new = """    // Generate sequential bill number per company
    $companyIdForBill = isset($input['companyId']) ? (int)$input['companyId'] : null;
    if ($companyIdForBill) {
        $billStmt = $pdo->prepare(\"SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM purchase_invoice WHERE tenant_id = ? AND company_id = ? AND bill_no LIKE 'PUR-%'\");
        $billStmt->execute([$tenant_id, $companyIdForBill]);
    } else {
        $billStmt = $pdo->prepare(\"SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM purchase_invoice WHERE tenant_id = ? AND bill_no LIKE 'PUR-%'\");
        $billStmt->execute([$tenant_id]);
    }
    $lastNumber = (int) $billStmt->fetchColumn();
    $billNo = 'PUR-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);"""

print('found:', old in d)
d = d.replace(old, new, 1)

f = open(r'c:/xampp/htdocs/ledgerone_erp/server/api/purchase/purchase_invoice/purchase-add.php', 'w', encoding='utf-8')
f.write(d)
f.close()
print('Done')
