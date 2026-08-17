<?php
/**
 * Migration: Add tax_percent and tax_amount columns to sale_return_items table
 * 
 * This migration adds support for tax tracking in sale returns.
 * Replaces gst_percent and gst_amount with tax_percent and tax_amount.
 */

require_once '../../includes/connection.php';

try {
    $pdo->beginTransaction();

    // Check if columns already exist
    $checkStmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'sale_return_items' 
        AND COLUMN_NAME IN ('tax_percent', 'tax_amount')
    ");
    $checkStmt->execute();
    $existingColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

    // Add tax_percent column if it doesn't exist
    if (!in_array('tax_percent', $existingColumns)) {
        $pdo->exec("
            ALTER TABLE sale_return_items 
            ADD COLUMN tax_percent DECIMAL(5, 2) DEFAULT 0.00 AFTER discount_amount
        ");
        echo "✓ Added tax_percent column\n";
    }

    // Add tax_amount column if it doesn't exist
    if (!in_array('tax_amount', $existingColumns)) {
        $pdo->exec("
            ALTER TABLE sale_return_items 
            ADD COLUMN tax_amount DECIMAL(12, 2) DEFAULT 0.00 AFTER tax_percent
        ");
        echo "✓ Added tax_amount column\n";
    }

    // Drop old GST columns if they exist (optional - comment out if you want to keep them)
    $checkGstStmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'sale_return_items' 
        AND COLUMN_NAME IN ('gst_percent', 'gst_amount')
    ");
    $checkGstStmt->execute();
    $gstColumns = $checkGstStmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('gst_percent', $gstColumns)) {
        $pdo->exec("ALTER TABLE sale_return_items DROP COLUMN gst_percent");
        echo "✓ Dropped gst_percent column\n";
    }

    if (in_array('gst_amount', $gstColumns)) {
        $pdo->exec("ALTER TABLE sale_return_items DROP COLUMN gst_amount");
        echo "✓ Dropped gst_amount column\n";
    }

    $pdo->commit();
    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
