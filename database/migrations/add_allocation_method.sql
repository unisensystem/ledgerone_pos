ALTER TABLE production_expenses 
ADD COLUMN allocation_method VARCHAR(20) DEFAULT 'direct',
ADD COLUMN per_unit_cost DECIMAL(15,4) DEFAULT 0;
