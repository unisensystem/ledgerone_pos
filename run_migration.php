<?php
require_once __DIR__ . '/includes/connection.php';

$migrationFile = __DIR__ . '/database/migrations/update_sale_invoice_decimal_precision.sql';
$sql = file_get_contents($migrationFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement) && !str_starts_with(ltrim($statement), '--')) {
        try {
            $pdo->exec($statement);
            echo "OK: " . substr(trim($statement), 0, 100) . "\n";
        } catch (PDOException $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nDone.\n";
