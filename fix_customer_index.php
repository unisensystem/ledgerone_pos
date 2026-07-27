<?php
require_once 'includes/connection.php';

try {
    // Drop old unique key (tenant_id, customer_code)
    $pdo->exec("ALTER TABLE customers DROP INDEX uk_customers_tenant_code");
    echo "Old index dropped.<br>";

    // Add new unique key (tenant_id, company_id, customer_code)
    $pdo->exec("ALTER TABLE customers ADD UNIQUE KEY uk_customers_tenant_company_code (tenant_id, company_id, customer_code)");
    echo "New index added: (tenant_id, company_id, customer_code)<br>";

    echo "<b>Done! Each company now has its own customer code sequence.</b>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
