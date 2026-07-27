<?php
require_once 'includes/connection.php';
try {
    $stmt = $pdo->query("SHOW INDEX FROM suppliers");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($indexes as $idx) {
        echo "Key: {$idx['Key_name']} | Non_unique: {$idx['Non_unique']} | Column: {$idx['Column_name']}\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
