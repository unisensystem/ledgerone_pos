<?php
// Migration: Update cash_opening table to use currency_id instead of currency code

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Step 1: Add currency_id column if it doesn't exist
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `cash_opening` LIKE 'currency_id'");
    if ($checkColumn->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `cash_opening` ADD COLUMN `currency_id` INT NULL AFTER `branch_id`");
        echo "✓ Added currency_id column\n";
    }

    // Step 2: Map currency codes to IDs
    $mappings = [
        'USD' => 1,
        'PKR' => 2,
        'Pak' => 2,  // Handle "Pak" variant
        'EUR' => 3,
        'GBP' => 4,
        'AED' => 5,
        'UAE' => 5   // Handle "UAE" variant
    ];

    // Step 3: Update records with currency_id
    foreach ($mappings as $code => $currencyId) {
        $stmt = $pdo->prepare("UPDATE `cash_opening` SET `currency_id` = ? WHERE `currency` = ?");
        $stmt->execute([$currencyId, $code]);
        $updated = $stmt->rowCount();
        if ($updated > 0) {
            echo "✓ Updated $updated records: $code → currency_id $currencyId\n";
        }
    }

    // Step 4: Add foreign key constraint
    $checkFK = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                            WHERE TABLE_NAME='cash_opening' AND COLUMN_NAME='currency_id' AND REFERENCED_TABLE_NAME='currencies'");
    if ($checkFK->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `cash_opening` ADD CONSTRAINT `fk_cash_opening_currency` 
                    FOREIGN KEY (`currency_id`) REFERENCES `ledgerone_public`.`currencies`(`id`)");
        echo "✓ Added foreign key constraint\n";
    }

    // Step 5: Drop old currency column
    $pdo->exec("ALTER TABLE `cash_opening` DROP COLUMN `currency`");
    echo "✓ Dropped old currency column\n";

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
