<?php
require_once '../../../includes/connection.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Large-data backups need more headroom than PHP's defaults (30s / 128M),
// otherwise the script gets killed mid-dump with no catchable error.
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 minutes

$backupName = $_GET['name'] ?? 'backup_' . date('Y_m_d_H_i_s');
$backupName = preg_replace('/[^a-zA-Z0-9_-]/', '', $backupName);
$filename = $backupName . '.sql';

// Buffer a chunk of SQL text and flush it to the client instead of building
// one giant string in memory. This keeps memory usage flat regardless of
// how much data a tenant has.
function backup_write(&$buffer, $text, $flushThreshold = 1048576) { // 1MB chunks
    $buffer .= $text;
    if (strlen($buffer) >= $flushThreshold) {
        echo $buffer;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        $buffer = '';
    }
}

// Correctly quote a value for SQL, writing NULL (not '') for null values.
function backup_quote_value($pdo, $val) {
    if ($val === null) {
        return 'NULL';
    }
    return $pdo->quote($val);
}

try {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    // NOTE: Content-Length intentionally omitted — we no longer know the
    // final size upfront since output is streamed in chunks.

    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    $buffer = '';
    backup_write($buffer, "-- Database Backup for Tenant ID: {$tenant_id}\n");
    backup_write($buffer, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");

    // Only get BASE TABLES, skip views (views cause definer errors)
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        backup_write($buffer, "\n\n-- Table: {$table}\n");
        backup_write($buffer, "DROP TABLE IF EXISTS `{$table}`;\n");
        backup_write($buffer, $row[1] . ";\n\n");

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'tenant_id'");
        $hasTenantId = $stmt->rowCount() > 0;

        if ($hasTenantId) {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
        }

        // Use unbuffered-style row-by-row processing so we never hold the
        // whole table's result set as one big PHP array in memory.
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert = "INSERT INTO `{$table}` (";
            $insert .= implode(', ', array_map(fn($col) => "`{$col}`", array_keys($row)));
            $insert .= ") VALUES (";
            $insert .= implode(', ', array_map(fn($val) => backup_quote_value($pdo, $val), array_values($row)));
            $insert .= ");\n";
            backup_write($buffer, $insert);
        }
    }

    // Flush whatever is left in the buffer
    if ($buffer !== '') {
        echo $buffer;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

} catch (Throwable $e) {
    // Throwable (not just Exception) so we also catch PHP Errors, e.g.
    // a DB error mid-dump. Note: hard timeouts / memory-exhaustion fatals
    // still cannot be caught by any PHP code — the ini_set/set_time_limit
    // above are what actually prevent those, not this catch block.
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
}
exit;