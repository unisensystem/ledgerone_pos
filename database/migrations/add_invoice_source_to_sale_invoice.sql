-- Distinguishes "Primery Trade Invoice" (dummy invoices - no accounting_ledger,
-- stock_ledger or receive_voucher entries) from "Traders Sale Invoice" (real invoices)
-- so their respective pos-list.php pages only ever show their own invoices.
-- Every other sale-invoice-creating module keeps writing rows with the default
-- 'trade' value unchanged - this column only distinguishes between these two.
ALTER TABLE sale_invoice
    ADD COLUMN invoice_source ENUM('trade', 'primary') NOT NULL DEFAULT 'trade';
