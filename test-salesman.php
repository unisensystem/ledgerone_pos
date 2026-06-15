<?php
// Quick diagnostic — delete this file after use
require_once 'includes/connection.php';

// Check if emulated prepares is active
$emuEnabled = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
echo "EMULATE_PREPARES: " . ($emuEnabled ? 'ON' : 'OFF') . "\n";

// Attempt the update
$supplierId = 174;
$testValue  = '115,116';
try {
    $stmt = $pdo->prepare("UPDATE suppliers SET salesman_id = ? WHERE id = ?");
    $stmt->execute([$testValue, $supplierId]);
    echo "UPDATE OK\n";
} catch (PDOException $e) {
    echo "UPDATE ERROR: " . $e->getCode() . " " . $e->getMessage() . "\n";
}

// Read back
$stmt = $pdo->prepare("SELECT salesman_id FROM suppliers WHERE id = ?");
$stmt->execute([$supplierId]);
$row = $stmt->fetch();
echo "Stored value: " . var_export($row['salesman_id'], true) . "\n";
echo "PHP type: " . gettype($row['salesman_id']) . "\n";
