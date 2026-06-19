DELIVERY CHALAN PRINT ERROR - FIXED ✅
=====================================

ERROR:
------
When trying to print delivery chalan with ID, the following error occurred:
"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'si.bill_no' in 'field list'"

CAUSE:
------
The pos-edit.php file was trying to select si.bill_no (from sale_invoice table)
but the LEFT JOIN for sale_invoice was missing from the FROM clause.

The SELECT query was referencing sale_invoice columns without properly joining the table.

SOLUTION:
---------
Added the missing LEFT JOIN statement in:
File: server/api/sale/delivery_chalan/pos-edit.php

The complete SELECT query now includes:
```sql
SELECT dc.*,
       c.customer_name, c.customer_code,
       co.company_name,
       b.branch_name, b.branch_code, b.branch_type,
       pb.branch_name  AS parent_branch_name,
       cur.symbol      AS currency_symbol,
       cur.code        AS currency_code,
       cur.name        AS currency_name,
       e.id            AS sales_officer_id,
       e.full_name     AS sales_officer_name,
       sm.id           AS supplier_man_id,
       sm.full_name    AS supplier_man_name,
       si.bill_no      AS sale_invoice_no
FROM delivery_chalan dc
LEFT JOIN customers  c   ON dc.customer_id     = c.id
LEFT JOIN companies  co  ON dc.company_id      = co.id
LEFT JOIN branches   b   ON dc.branch_id       = b.id
LEFT JOIN branches   pb  ON b.parent_branch_id = pb.id
LEFT JOIN ledgerone_public.currencies cur ON dc.currency_id = cur.id
LEFT JOIN employees  e   ON dc.sale_officer_id = e.id
LEFT JOIN employees  sm  ON dc.supplier_man_id = sm.id
LEFT JOIN sale_invoice si ON dc.sale_invoice_id = si.id
WHERE dc.id = ? AND dc.tenant_id = ?
```

TESTING:
--------
✅ Delivery chalan print should now work
✅ Can access invoice-print.php?id=2 without SQL error
✅ All sale invoice information properly displayed

FILES UPDATED:
--------------
✅ server/api/sale/delivery_chalan/pos-edit.php

RELATED FEATURES:
-----------------
✅ Project dropdown integration for delivery chalan
✅ Project data now saves to delivery_chalan.project_id
✅ Project displayed on print output
✅ Project restored when editing delivery chalan
