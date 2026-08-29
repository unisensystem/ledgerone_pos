-- Adds a Credit (Cr) amount alongside the existing Debit (Dr) amount on each
-- opening balance invoice row, so a customer's opening invoices can be a mix
-- of Dr and Cr entries instead of Debit-only.
ALTER TABLE opening_balance_invoices ADD COLUMN credit DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER debit;
