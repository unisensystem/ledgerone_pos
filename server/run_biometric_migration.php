<?php
/*
 * One-time migration runner: creates the three biometric tables.
 * Hit this URL in a browser once after deploying, then delete the file.
 *
 * Usage: https://yourserver.com/server/run_biometric_migration.php
 */

require_once __DIR__ . '/../includes/connection.php';

$migrationFile = __DIR__ . '/../database/migrations/add_biometric_device_tables.sql';

if (!file_exists($migrationFile)) {
    die('Migration file not found: ' . htmlspecialchars($migrationFile));
}

$sql        = file_get_contents($migrationFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

echo '<pre>';
foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
        echo '✓ ' . htmlspecialchars(substr($stmt, 0, 90)) . "…\n";
    } catch (PDOException $e) {
        echo '✗ Error: ' . htmlspecialchars($e->getMessage()) . "\n";
        echo '  Statement: ' . htmlspecialchars(substr($stmt, 0, 90)) . "\n";
    }
}
echo "\nDone. Delete this file from the server now.\n";
echo '</pre>';
