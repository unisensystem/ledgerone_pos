<?php

class Migration_2024_add_tenant_id_to_tax_rates {
    
    public function up($pdo) {
        try {
            $sql = "ALTER TABLE tax_rates ADD COLUMN tenant_id INT DEFAULT 0 AFTER id";
            $pdo->exec($sql);
            
            // Add index for tenant_id
            $sql = "ALTER TABLE tax_rates ADD INDEX idx_tenant_id (tenant_id)";
            $pdo->exec($sql);
            
            return true;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
    
    public function down($pdo) {
        try {
            $sql = "ALTER TABLE tax_rates DROP INDEX idx_tenant_id";
            $pdo->exec($sql);
            
            $sql = "ALTER TABLE tax_rates DROP COLUMN tenant_id";
            $pdo->exec($sql);
            
            return true;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
}
?>
