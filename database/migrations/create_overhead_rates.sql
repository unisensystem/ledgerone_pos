CREATE TABLE overhead_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    rate_type ENUM('per_unit', 'per_hour', 'percentage_of_material'),
    rate_value DECIMAL(15,4),
    description VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
