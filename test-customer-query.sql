-- Check if customers table has company_id column
SELECT COLUMN_NAME, DATA_TYPE 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'ledgerone_tenant' 
  AND TABLE_NAME = 'customers'
  AND COLUMN_NAME = 'company_id';

-- Check sample customers with company_id
SELECT id, customer_code, customer_name, company_id 
FROM customers 
WHERE tenant_id = 2 
LIMIT 20;
