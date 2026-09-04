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

// Check if file was even received - if not, likely a post_max_size / upload_max_filesize issue
if (!isset($_FILES['backup_file'])) {
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 'unknown';
    $postMaxSize = ini_get('post_max_size');
    error_log("Restore: No file in \$_FILES. Content-Length: {$contentLength}, post_max_size: {$postMaxSize}");
    echo json_encode([
        'success' => false,
        'message' => "No file received. This usually means the file is bigger than the server's upload limit (post_max_size: {$postMaxSize})."
    ]);
    exit;
}

// Check for specific upload errors instead of a generic message
if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['backup_file']['error'];
    $errMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the form',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk (permissions issue)',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
    ];
    $errMsg = $errMessages[$errCode] ?? "Unknown upload error (code {$errCode})";
    error_log("Restore: Upload error code {$errCode} - {$errMsg}");
    echo json_encode(['success' => false, 'message' => $errMsg]);
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