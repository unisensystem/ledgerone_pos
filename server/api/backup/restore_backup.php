<?php
require_once '../../../includes/connection.php';

header('Content-Type: application/json');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['backup_file'];

if (!str_ends_with($file['name'], '.sql')) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}

try {
    $sql = file_get_contents($file['tmp_name']);
    
    if ($sql === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read file']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Delete only this tenant's data from tables with tenant_id
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'tenant_id'");
        if ($stmt->rowCount() > 0) {
            $pdo->prepare("DELETE FROM `{$table}` WHERE tenant_id = ?")->execute([$tenant_id]);
        }
    }
    
    // Execute SQL statements from backup
    $pdo->exec($sql);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    error_log('Restore Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()]);
}
