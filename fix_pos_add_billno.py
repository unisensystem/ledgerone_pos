f = open(r'c:/xampp/htdocs/ledgerone_erp/server/api/sale/pos_invoice/pos-add.php', 'rb')
d = f.read()
f.close()

old = b"    // Generate sequential bill number\r\n    $billStmt = $pdo->prepare(\"SELECT bill_no FROM sale_invoice WHERE tenant_id = ? ORDER BY id DESC LIMIT 1\");\r\n    $billStmt->execute([$tenant_id]);\r\n    $lastBill = $billStmt->fetchColumn();\r\n\r\n    if ($lastBill) {\r\n        $lastNumber = (int) substr($lastBill, 4); // Extract number from SAL-XXXX\r\n        $newNumber = $lastNumber + 1;\r\n    } else {\r\n        $newNumber = 1;\r\n    }\r\n    $billNo = 'SAL-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);"

new = b"    // Generate sequential bill number per company\r\n    $companyIdForBill = isset($input['companyId']) ? (int)$input['companyId'] : null;\r\n    if ($companyIdForBill) {\r\n        $billStmt = $pdo->prepare(\"SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM sale_invoice WHERE tenant_id = ? AND company_id = ? AND bill_no LIKE 'SAL-%'\");\r\n        $billStmt->execute([$tenant_id, $companyIdForBill]);\r\n    } else {\r\n        $billStmt = $pdo->prepare(\"SELECT MAX(CAST(SUBSTRING(bill_no, 5) AS UNSIGNED)) FROM sale_invoice WHERE tenant_id = ? AND bill_no LIKE 'SAL-%'\");\r\n        $billStmt->execute([$tenant_id]);\r\n    }\r\n    $lastNumber = (int) $billStmt->fetchColumn();\r\n    $billNo = 'SAL-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);"

print('old found:', old in d)
d = d.replace(old, new, 1)

f = open(r'c:/xampp/htdocs/ledgerone_erp/server/api/sale/pos_invoice/pos-add.php', 'wb')
f.write(d)
f.close()
print('Done')
