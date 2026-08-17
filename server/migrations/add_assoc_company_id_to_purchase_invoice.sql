ALTER TABLE purchase_invoice
    ADD COLUMN assoc_company_id INT NULL DEFAULT NULL AFTER project_id,
    ADD CONSTRAINT fk_pi_assoc_company FOREIGN KEY (assoc_company_id) REFERENCES companies(id) ON DELETE SET NULL;
