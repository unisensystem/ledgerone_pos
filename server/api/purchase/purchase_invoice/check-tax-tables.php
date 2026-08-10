<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');

try {
    // Show all tax related tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $taxTables = array_filter($tables, fn($t) => stripos($t, 'tax') !== false || stripos($t, 'invoice_level') !== false);

    $result = [];
    foreach ($taxTables as $table) {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $result[$table] = $cols;
    }

    // Also check purchase_invoice columns
    $piCols = $pdo->query("DESCRIBE purchase_invoice")->fetchAll(PDO::FETCH_ASSOC);
    $result['purchase_invoice'] = $piCols;

    echo json_encode(['success' => true, 'data' => $result], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
