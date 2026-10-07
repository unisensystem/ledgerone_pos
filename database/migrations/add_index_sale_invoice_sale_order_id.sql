-- sale_invoice.sale_order_id had no index at all, forcing a full table scan
-- (18k+ rows) on every row of the Sale Order list's fulfillment-status lookup
-- (EXISTS ... WHERE inv.sale_order_id = si.id AND inv.status = 'Posted'),
-- making server/api/sale/sale_order/order-list.php take 14-15 seconds for
-- tenants with a few thousand orders. This is a read-path fix only.
ALTER TABLE sale_invoice
    ADD INDEX idx_sale_order_id_status (sale_order_id, status);
