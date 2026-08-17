<?php
/**
 * Migration Runner: Add Country ID to Tax Regimes
 * Description: Executes migration to add country_id column to tax_regimes and tax_rates tables
 * Date: 2024
 */

require_once __DIR__ . '/../../includes/db.php';

class TaxRegimeCountryMigration {
    private $connection;
    private $errors = [];
    private $success = [];

    public function __construct($connection) {
        $this->connection = $connection;
    }

    public function run() {
        try {
            echo "Starting Tax Regime Country Migration...\n";
            
            // Step 1: Add country_id to tax_regimes
            $this->addCountryIdToTaxRegimes();
            
            // Step 2: Add tenant_id to tax_regimes
            $this->addTenantIdToTaxRegimes();
            
            // Step 3: Add country_id to tax_rates
            $this->addCountryIdToTaxRates();
            
            // Step 4: Add tenant_id to tax_rates
            $this->addTenantIdToTaxRates();
            
            // Step 5: Create indexes
            $this->createIndexes();
            
            $this->displayResults();
            return count($this->errors) === 0;
        } catch (Exception $e) {
            $this->errors[] = "Migration failed: " . $e->getMessage();
            $this->displayResults();
            return false;
        }
    }

    private function addCountryIdToTaxRegimes() {
        try {
            $sql = "ALTER TABLE `tax_regimes` ADD COLUMN IF NOT EXISTS `country_id` INT NULL COMMENT 'Foreign key to countries table' AFTER `regime_code`";
            $this->connection->query($sql);
            $this->success[] = "✓ Added country_id column to tax_regimes table";
            
            // Add foreign key
            $sql = "ALTER TABLE `tax_regimes` ADD CONSTRAINT `fk_tax_regimes_country` 
                    FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE";
            $this->connection->query($sql);
            $this->success[] = "✓ Added foreign key constraint for country_id in tax_regimes";
        } catch (Exception $e) {
            $this->errors[] = "Error adding country_id to tax_regimes: " . $e->getMessage();
        }
    }

    private function addTenantIdToTaxRegimes() {
        try {
            $sql = "ALTER TABLE `tax_regimes` ADD COLUMN IF NOT EXISTS `tenant_id` INT NULL COMMENT 'Tenant ID for multi-tenancy' AFTER `id`";
            $this->connection->query($sql);
            $this->success[] = "✓ Added tenant_id column to tax_regimes table";
            
            // Add foreign key
            $sql = "ALTER TABLE `tax_regimes` ADD CONSTRAINT `fk_tax_regimes_tenant` 
                    FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE";
            $this->connection->query($sql);
            $this->success[] = "✓ Added foreign key constraint for tenant_id in tax_regimes";
        } catch (Exception $e) {
            $this->errors[] = "Error adding tenant_id to tax_regimes: " . $e->getMessage();
        }
    }

    private function addCountryIdToTaxRates() {
        try {
            $sql = "ALTER TABLE `tax_rates` ADD COLUMN IF NOT EXISTS `country_id` INT NULL COMMENT 'Country ID for tax rate' AFTER `tax_regime_id`";
            $this->connection->query($sql);
            $this->success[] = "✓ Added country_id column to tax_rates table";
            
            // Add foreign key
            $sql = "ALTER TABLE `tax_rates` ADD CONSTRAINT `fk_tax_rates_country` 
                    FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE";
            $this->connection->query($sql);
            $this->success[] = "✓ Added foreign key constraint for country_id in tax_rates";
        } catch (Exception $e) {
            $this->errors[] = "Error adding country_id to tax_rates: " . $e->getMessage();
        }
    }

    private function addTenantIdToTaxRates() {
        try {
            $sql = "ALTER TABLE `tax_rates` ADD COLUMN IF NOT EXISTS `tenant_id` INT NULL COMMENT 'Tenant ID for multi-tenancy' AFTER `id`";
            $this->connection->query($sql);
            $this->success[] = "✓ Added tenant_id column to tax_rates table";
            
            // Add foreign key
            $sql = "ALTER TABLE `tax_rates` ADD CONSTRAINT `fk_tax_rates_tenant` 
                    FOREIGN KEY (`tenant_id`) REFERENCES `ledgerone_public`.`tenants` (`id`) ON DELETE CASCADE";
            $this->connection->query($sql);
            $this->success[] = "✓ Added foreign key constraint for tenant_id in tax_rates";
        } catch (Exception $e) {
            $this->errors[] = "Error adding tenant_id to tax_rates: " . $e->getMessage();
        }
    }

    private function createIndexes() {
        try {
            $indexes = [
                "CREATE INDEX IF NOT EXISTS `idx_tax_regimes_country_id` ON `tax_regimes` (`country_id`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_regimes_country_active` ON `tax_regimes` (`country_id`, `is_active`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_regimes_country_authority_active` ON `tax_regimes` (`country_id`, `tax_authority`, `is_active`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_regimes_tenant_id` ON `tax_regimes` (`tenant_id`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_regimes_tenant_country` ON `tax_regimes` (`tenant_id`, `country_id`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_rates_country_id` ON `tax_rates` (`country_id`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_rates_country_type_active` ON `tax_rates` (`country_id`, `tax_type`, `is_active`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_rates_tenant_id` ON `tax_rates` (`tenant_id`)",
                "CREATE INDEX IF NOT EXISTS `idx_tax_rates_tenant_country_active` ON `tax_rates` (`tenant_id`, `country_id`, `is_active`)"
            ];
            
            foreach ($indexes as $index) {
                $this->connection->query($index);
            }
            $this->success[] = "✓ Created " . count($indexes) . " indexes for optimal query performance";
        } catch (Exception $e) {
            $this->errors[] = "Error creating indexes: " . $e->getMessage();
        }
    }

    private function displayResults() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "MIGRATION RESULTS\n";
        echo str_repeat("=", 60) . "\n\n";
        
        if (!empty($this->success)) {
            echo "SUCCESS:\n";
            foreach ($this->success as $msg) {
                echo "  " . $msg . "\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "ERRORS:\n";
            foreach ($this->errors as $msg) {
                echo "  ✗ " . $msg . "\n";
            }
            echo "\n";
        }
        
        echo str_repeat("=", 60) . "\n";
        echo "Status: " . (count($this->errors) === 0 ? "SUCCESS ✓" : "FAILED ✗") . "\n";
        echo str_repeat("=", 60) . "\n";
    }
}

// Execute migration if run directly
if (php_sapi_name() === 'cli') {
    $migration = new TaxRegimeCountryMigration($connection);
    $success = $migration->run();
    exit($success ? 0 : 1);
}
?>
