<?php
header('Content-Type: application/json');
session_start();
require_once '../includes/connection.php';

// Check if PDO object is valid after including config.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get tenant_id from session
if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Tenant ID not found in session']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];

$transactionStarted = false; // Flag to track transaction status

try {
    // Disable foreign key checks before transaction
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Start transaction
    $pdo->beginTransaction();
    $transactionStarted = true;

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all views to exclude them
    $views = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);

    // Define protected tables that should not be cleared
    $protected_tables = ['accounts', 'sub_accounts', 'accounts_head', 'users', 'user_roles', 'role_permissions'];

    // Clear tenant-specific data from tables
    foreach ($tables as $table) {
        // Skip views and protected tables
        if (in_array($table, $views) || in_array($table, $protected_tables)) {
            continue;
        }
        // Check if table has tenant_id column
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('tenant_id', $columns)) {
            $pdo->prepare("DELETE FROM `$table` WHERE tenant_id = ? AND tenant_id != 0")->execute([$tenant_id]);
        }
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Commit transaction
    $pdo->commit();

    // Return JSON response
    echo json_encode(['success' => true, 'message' => 'Database formatted successfully']);
    exit;
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    if ($transactionStarted) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'PDO Error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    if ($transactionStarted) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
?>