<?php
// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'fuelingsys_tenant');
define('DB_PASS', 'M36VQg42Uv)PF&RCm%*@');
define('DB_TENANT', 'fuelingsys_tenant');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_TENANT . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Read and execute migration file
    $migrationFile = __DIR__ . '/migrations/update_purchase_decimal_precision.sql';
    
    if (!file_exists($migrationFile)) {
        die("Migration file not found: " . $migrationFile);
    }

    $sql = file_get_contents($migrationFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 80) . "...\n";
        }
    }
    
    echo "\n✓ All migrations completed successfully!\n";
    echo "Columns updated from decimal(15,2) to decimal(15,3)\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit(1);
}
?>
