<?php
require_once '../../../includes/connection.php';

header('Content-Type: application/json');

// Large restores can take a while and use more memory than default limits.
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');

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

/**
 * Splits a raw SQL dump into individual executable statements.
 * Handles standard `;` terminated statements as well as DELIMITER
 * blocks (used by mysqldump for triggers/procedures, e.g. `DELIMITER ;;`).
 * Skips blank lines and full-line comments (-- or #).
 */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $delimiter  = ';';
    $buffer     = '';

    // Normalize line endings
    $sql = str_replace("\r\n", "\n", $sql);
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        // Handle "DELIMITER xxx" directive used around triggers/procedures
        if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $m)) {
            $delimiter = $m[1];
            continue;
        }

        $buffer .= $line . "\n";

        $bufTrim  = rtrim($buffer);
        $delimLen = strlen($delimiter);

        if ($delimLen > 0 && substr($bufTrim, -$delimLen) === $delimiter) {
            $statement = trim(substr($bufTrim, 0, -$delimLen));
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $remaining = trim($buffer);
    if ($remaining !== '') {
        $statements[] = $remaining;
    }

    return $statements;
}

/**
 * Rewrites DEFINER on every VIEW and TRIGGER in the current database
 * to CURRENT_USER, so nothing depends on a user account that may not
 * exist on this server. Safe to run repeatedly (idempotent).
 */
function repairBrokenDefiners(PDO $pdo): array {
    $log = [];

    // --- Fix VIEWS ---
    $views = $pdo->query("
        SELECT TABLE_NAME
        FROM information_schema.VIEWS
        WHERE TABLE_SCHEMA = DATABASE()
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($views as $viewName) {
        try {
            $row = $pdo->query("SHOW CREATE VIEW `{$viewName}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $row['Create View'] ?? null;
            if (!$createSql) continue;

            $fixedSql = preg_replace(
                '/DEFINER\s*=\s*`[^`]*`\s*@\s*`[^`]*`/i',
                'DEFINER=CURRENT_USER',
                $createSql
            );
            $fixedSql = preg_replace('/^CREATE\b/i', 'CREATE OR REPLACE', $fixedSql, 1);

            $pdo->exec($fixedSql);
        } catch (Exception $e) {
            $log[] = "View `{$viewName}` not repaired: " . $e->getMessage();
            error_log("repairBrokenDefiners: view {$viewName} -> " . $e->getMessage());
        }
    }

    // --- Fix TRIGGERS ---
    $triggers = $pdo->query("
        SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION, ACTION_STATEMENT
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = DATABASE()
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($triggers as $t) {
        try {
            $pdo->exec("DROP TRIGGER IF EXISTS `{$t['TRIGGER_NAME']}`");
            $pdo->exec("
                CREATE DEFINER=CURRENT_USER TRIGGER `{$t['TRIGGER_NAME']}`
                {$t['ACTION_TIMING']} {$t['EVENT_MANIPULATION']}
                ON `{$t['EVENT_OBJECT_TABLE']}`
                FOR EACH ROW
                {$t['ACTION_STATEMENT']}
            ");
        } catch (Exception $e) {
            $log[] = "Trigger `{$t['TRIGGER_NAME']}` not repaired: " . $e->getMessage();
            error_log("repairBrokenDefiners: trigger {$t['TRIGGER_NAME']} -> " . $e->getMessage());
        }
    }

    return $log;
}

/** Safely rolls back a transaction without letting a dead connection crash the script. */
function safeRollBack(?PDO $pdo): void {
    if (!$pdo) return;
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable $e) {
        // Connection is likely already gone (e.g. "MySQL server has gone away").
        // MySQL itself auto-rolls-back an open transaction when the connection
        // drops, so there's nothing more to do here — just log it.
        error_log('Restore: rollback skipped, connection already lost - ' . $e->getMessage());
    }
}

$inTransaction = false;
$failedStatementPreview = null;

try {
    $sql = file_get_contents($file['tmp_name']);

    if ($sql === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to read file']);
        exit;
    }

    // Refuse dumps that try to drop/recreate schema. A tenant restore must
    // never touch table structure - only this tenant's rows. A dump like
    // that would also imply it may contain OTHER tenants' data (a full
    // DROP+CREATE dump strips per-tenant rows only from tables that had
    // tenant_id, but still wipes the whole table for everyone). Reject it
    // outright rather than risk destroying other tenants' data.
    if (preg_match('/^\s*DROP\s+TABLE\b/im', $sql) || preg_match('/^\s*CREATE\s+TABLE\b/im', $sql)) {
        throw new RuntimeException(
            'This backup file contains table structure (DROP/CREATE TABLE) statements. ' .
            'Tenant restores only accept data-only backups generated by the current backup tool, ' .
            'to avoid wiping other tenants\' data. Please generate a fresh backup and try again.'
        );
    }

    // Strip DEFINER clauses from the incoming dump so nothing new gets
    // created with a definer that doesn't exist on this server.
    $sql = preg_replace('/DEFINER\s*=\s*`[^`]*`\s*@\s*`[^`]*`/i', '', $sql);
    $sql = preg_replace('/DEFINER\s*=\s*[^\s]+@[^\s]+/i', '', $sql);

    // Repair any pre-existing broken definers before touching data.
    repairBrokenDefiners($pdo);

    $statements = splitSqlStatements($sql);

    // Figure out exactly which tables this backup touches, straight from
    // its INSERT statements, instead of looping over every table in the
    // whole database. Every one of these MUST have a tenant_id column -
    // the fixed backup generator only ever dumps tenant-scoped tables,
    // but we double-check here as a safety net against hand-edited or
    // older-format files.
    $dumpTables = [];
    foreach ($statements as $statement) {
        if (preg_match('/^\s*INSERT\s+INTO\s+`([^`]+)`/i', $statement, $m)) {
            $dumpTables[$m[1]] = true;
        }
    }
    $dumpTables = array_keys($dumpTables);

    foreach ($dumpTables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'tenant_id'");
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                "Backup references table `{$table}` which has no tenant_id column. " .
                "Refusing to restore it to avoid touching data shared by other tenants."
            );
        }
    }

    $pdo->beginTransaction();
    $inTransaction = true;
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("SET UNIQUE_CHECKS=0");

    // Delete only this tenant's existing rows, only in the tables this
    // backup actually contains data for - then insert the backup's rows.
    // This gives a true, exact restore for this tenant (matches the
    // backup precisely) without touching any other tenant's data.
    foreach ($dumpTables as $table) {
        $pdo->prepare("DELETE FROM `{$table}` WHERE tenant_id = ?")->execute([$tenant_id]);
    }

    // Execute the dump statement-by-statement (each one is already a
    // batched multi-row INSERT from the backup generator, so this is
    // typically only tens to low-hundreds of round trips, not one per row).
    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            $failedStatementPreview = substr($statement, 0, 200);
            throw new RuntimeException(
                "Statement #{$index} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    $pdo->exec("SET UNIQUE_CHECKS=1");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $pdo->commit();
    $inTransaction = false;

    // Repair again in case the dump re-created any view/trigger with a bad definer.
    repairBrokenDefiners($pdo);

    echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
} catch (Throwable $e) {
    safeRollBack($inTransaction ? $pdo : null);

    try { $pdo->exec("SET UNIQUE_CHECKS=1"); } catch (Throwable $ignore) {}
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Throwable $ignore) {}

    http_response_code(500);
    error_log('Restore Error: ' . $e->getMessage());

    $message = 'Restore failed: ' . $e->getMessage();
    if ($failedStatementPreview !== null) {
        $message .= ' | Near: ' . $failedStatementPreview;
    }

    echo json_encode(['success' => false, 'message' => $message]);
}