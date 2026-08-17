<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM purchase_invoice_items LIKE 'size'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE purchase_invoice_items ADD COLUMN size VARCHAR(100) NULL DEFAULT NULL AFTER foc_quantity");
        echo json_encode(['success' => true, 'message' => 'size column added successfully']);
    } else {
        echo json_encode(['success' => true, 'message' => 'size column already exists']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
