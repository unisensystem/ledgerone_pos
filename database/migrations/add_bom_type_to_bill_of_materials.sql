-- Adds a BOM Type/Group field so a finished good can have more than one
-- named BOM (e.g. "BOM 1" and "BOM 2"), selectable via a filter dropdown
-- on the Production Order screen.
ALTER TABLE `bill_of_materials`
  ADD COLUMN `bom_type` VARCHAR(50) NOT NULL DEFAULT 'BOM 1' AFTER `version`;
