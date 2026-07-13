<?php
require_once '../../../../includes/connection.php';

try {
    $pdo->exec("ALTER TABLE expense_voucher ADD COLUMN IF NOT EXISTS is_cash_flow TINYINT(1) NOT NULL DEFAULT 1");
    echo json_encode(['success' => true, 'message' => 'Column is_cash_flow added successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
