ALTER TABLE tax_regimes 
ADD COLUMN application_level ENUM('item', 'invoice') DEFAULT 'item';
