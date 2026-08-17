-- Table to track actual WIP materials consumed per production completion
CREATE TABLE IF NOT EXISTS production_completion_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_completion_id INT NOT NULL,
    material_id INT NOT NULL,
    consumed_qty DECIMAL(15,4) NOT NULL,
    uom_id INT NOT NULL,
    unit_cost DECIMAL(15,4) NOT NULL,
    total_cost DECIMAL(15,4) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_completion_id) REFERENCES production_completions(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES products(id),
    FOREIGN KEY (uom_id) REFERENCES uom(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for faster lookups
CREATE INDEX idx_completion_materials ON production_completion_materials(production_completion_id);
CREATE INDEX idx_material ON production_completion_materials(material_id);
