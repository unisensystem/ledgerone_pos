-- Migration: Fix null or invalid product_type values
-- This script updates all products with null or invalid product_type to 'physical'

UPDATE products 
SET product_type = 'physical'
WHERE product_type IS NULL 
   OR product_type = '' 
   OR product_type NOT IN ('physical', 'service');

-- Verify the fix
SELECT COUNT(*) as products_with_invalid_type
FROM products 
WHERE product_type IS NULL 
   OR product_type = '' 
   OR product_type NOT IN ('physical', 'service');
