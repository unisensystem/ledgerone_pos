<?php
/**
 * Diagnostic endpoint to check invoice taxes table and data
 * 
 * Usage: /server/api/sale/sale_tax_invoice/diagnose-taxes.php
 */

require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
session_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$diagnostic = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => [],
    'sample_data' => [],
    'errors' => []
];

try {
    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'sale_invoice_taxes'")->fetchAll();
    $diagnostic['database']['table_exists'] = count($tableCheck) > 0;
    
    if ($diagnostic['database']['table_exists']) {
        // Get table structure
        $tableStructure = $pdo->query("DESCRIBE sale_invoice_taxes")->fetchAll(PDO::FETCH_ASSOC);
        $diagnostic['database']['columns'] = array_column($tableStructure, 'Field');
        
        // Get sample data
        $sampleData = $pdo->prepare("
            SELECT * FROM sale_invoice_taxes 
            WHERE tenant_id = ? 
            ORDER BY id DESC 
            LIMIT 5
        ");
        $sampleData->execute([$tenant_id]);
        $diagnostic['sample_data'] = $sampleData->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total taxes
        $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM sale_invoice_taxes WHERE tenant_id = ?");
        $countStmt->execute([$tenant_id]);
        $diagnostic['database']['total_taxes'] = $countStmt->fetch()['count'];
        
        // Check invoices with taxes
        $invoicesStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT sale_invoice_id) as invoice_count 
            FROM sale_invoice_taxes 
            WHERE tenant_id = ?
        ");
        $invoicesStmt->execute([$tenant_id]);
        $diagnostic['database']['invoices_with_taxes'] = $invoicesStmt->fetch()['invoice_count'];
        
    } else {
        $diagnostic['errors'][] = 'sale_invoice_taxes table does not exist!';
        $diagnostic['errors'][] = 'Run migration: create_sale_invoice_taxes_table.sql';
    }
    
    // Check if column exists in sale_invoice
    $columnCheck = $pdo->query("SHOW COLUMNS FROM sale_invoice LIKE 'total_invoice_tax_amount'")->fetchAll();
    $diagnostic['database']['total_invoice_tax_amount_exists'] = count($columnCheck) > 0;
    
    if (!$diagnostic['database']['total_invoice_tax_amount_exists']) {
        $diagnostic['errors'][] = 'Column total_invoice_tax_amount missing from sale_invoice table';
        $diagnostic['errors'][] = 'Run migration: add_invoice_tax_columns.sql';
    }
    
    // Check invoice helper file exists
    $helperPath = __DIR__ . '/invoice-tax-helper.php';
    $diagnostic['database']['invoice_tax_helper_exists'] = file_exists($helperPath);
    
    if (!$diagnostic['database']['invoice_tax_helper_exists']) {
        $diagnostic['errors'][] = 'invoice-tax-helper.php file not found';
    }
    
    // Get latest invoice and check if taxes would be saved
    $latestInvoiceStmt = $pdo->prepare("
        SELECT id, bill_no, net_amount, total_invoice_tax_amount 
        FROM sale_invoice 
        WHERE tenant_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $latestInvoiceStmt->execute([$tenant_id]);
    $diagnostic['latest_invoice'] = $latestInvoiceStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => count($diagnostic['errors']) === 0,
        'data' => $diagnostic
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => $diagnostic
    ], JSON_PRETTY_PRINT);
}
?>
