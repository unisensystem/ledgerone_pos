<?php
require_once 'includes/connection.php';

try {
    $pdo->exec("ALTER TABLE expense_voucher ADD COLUMN IF NOT EXISTS vendor_id INT NULL AFTER container_id");
    $pdo->exec("ALTER TABLE expense_voucher ADD COLUMN IF NOT EXISTS type ENUM('CR','DR') NOT NULL DEFAULT 'CR' AFTER vendor_id");
    echo "Migration successful: vendor_id and type columns added.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
