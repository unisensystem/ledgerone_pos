-- Costing Configuration Schema

-- 1. System Configuration Table (if not exists)
CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant_config (tenant_id, config_key),
    INDEX idx_tenant_key (tenant_id, config_key)
);

-- 2. Overhead Rates Table
CREATE TABLE IF NOT EXISTS overhead_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    rate_type ENUM('per_unit', 'per_hour', 'percentage_of_material') NOT NULL,
    rate_value DECIMAL(15,4) NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_active (tenant_id, is_active)
);

-- 3. Add columns to production_expenses table (if not exists)
ALTER TABLE production_expenses 
ADD COLUMN IF NOT EXISTS allocation_method VARCHAR(20) DEFAULT 'direct' COMMENT 'direct, multi, period',
ADD COLUMN IF NOT EXISTS per_unit_cost DECIMAL(15,4) DEFAULT 0 COMMENT 'Calculated per unit overhead cost';

-- 4. Add columns to completed_products table for costing tracking
ALTER TABLE completed_products
ADD COLUMN IF NOT EXISTS costing_method VARCHAR(20) DEFAULT 'actual' COMMENT 'actual or standard',
ADD COLUMN IF NOT EXISTS material_cost DECIMAL(15,4) DEFAULT 0 COMMENT 'Material cost component',
ADD COLUMN IF NOT EXISTS overhead_cost DECIMAL(15,4) DEFAULT 0 COMMENT 'Overhead cost component',
ADD COLUMN IF NOT EXISTS cost_adjusted TINYINT(1) DEFAULT 0 COMMENT 'Whether cost has been adjusted',
ADD COLUMN IF NOT EXISTS adjusted_at TIMESTAMP NULL COMMENT 'When cost was last adjusted';

-- 5. Create cost adjustment log table
CREATE TABLE IF NOT EXISTS cost_adjustments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    adjustment_date DATE NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    production_order_id INT NOT NULL,
    product_id INT NOT NULL,
    old_unit_cost DECIMAL(15,4) NOT NULL,
    new_unit_cost DECIMAL(15,4) NOT NULL,
    cost_variance DECIMAL(15,4) NOT NULL,
    qty_affected DECIMAL(15,4) NOT NULL,
    total_adjustment DECIMAL(15,4) NOT NULL,
    sales_adjusted INT DEFAULT 0 COMMENT 'Number of sales transactions adjusted',
    adjusted_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_date (tenant_id, adjustment_date),
    INDEX idx_po (production_order_id),
    INDEX idx_product (product_id)
);

-- 6. Insert default costing method (actual) for existing tenants
INSERT IGNORE INTO system_config (tenant_id, config_key, config_value)
SELECT DISTINCT tenant_id, 'costing_method', 'actual'
FROM production_orders
WHERE tenant_id NOT IN (
    SELECT tenant_id FROM system_config WHERE config_key = 'costing_method'
);

-- 7. Create view for cost variance analysis
CREATE OR REPLACE VIEW v_cost_variance_analysis AS
SELECT 
    ca.tenant_id,
    ca.adjustment_date,
    ca.production_order_id,
    po.order_no,
    ca.product_id,
    p.name as product_name,
    ca.old_unit_cost,
    ca.new_unit_cost,
    ca.cost_variance,
    ca.qty_affected,
    ca.total_adjustment,
    ca.sales_adjusted,
    CASE 
        WHEN ca.cost_variance > 0 THEN 'Unfavorable'
        WHEN ca.cost_variance < 0 THEN 'Favorable'
        ELSE 'No Variance'
    END as variance_type
FROM cost_adjustments ca
INNER JOIN production_orders po ON ca.production_order_id = po.id
INNER JOIN products p ON ca.product_id = p.id;
