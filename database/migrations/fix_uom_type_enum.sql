-- Fix UOM type ENUM to match application values
ALTER TABLE uom MODIFY COLUMN uom_type VARCHAR(50) NOT NULL;
