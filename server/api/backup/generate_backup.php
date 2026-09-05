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

// How many rows to pack into a single multi-row INSERT statement.
// This is what keeps the restore fast later - 500 rows in one INSERT
// instead of 500 separate round trips to MySQL.
const BATCH_SIZE = 500;

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
    backup_write($buffer, "-- Tenant-scoped Database Backup for Tenant ID: {$tenant_id}\n");
    backup_write($buffer, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    backup_write($buffer, "-- NOTE: This backup contains DATA ROWS ONLY for this tenant.\n");
    backup_write($buffer, "-- It intentionally does NOT drop or recreate any tables, and does\n");
    backup_write($buffer, "-- NOT include shared/global tables that other tenants also use.\n");
    backup_write($buffer, "-- It must be restored into a database that already has the schema.\n\n");

    // Only get BASE TABLES, skip views (views cause definer errors)
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'tenant_id'");
        $hasTenantId = $stmt->rowCount() > 0;

        // Skip tables that aren't tenant-scoped entirely. A tenant backup
        // should never touch shared/global tables (roles, settings, other
        // tenants' data, etc.) - restoring those would either duplicate-key
        // error or silently overwrite data that belongs to everyone.
        if (!$hasTenantId) {
            continue;
        }

        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);

        $columns = null;
        $batch = [];
        $rowCount = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = array_keys($row);
                backup_write($buffer, "\n-- Table: {$table}\n");
            }

            $values = implode(', ', array_map(fn($val) => backup_quote_value($pdo, $val), array_values($row)));
            $batch[] = "({$values})";
            $rowCount++;

            if (count($batch) >= BATCH_SIZE) {
                backup_write($buffer, buildInsert($table, $columns, $batch));
                $batch = [];
            }
        }

        // Flush any remaining rows for this table
        if (!empty($batch)) {
            backup_write($buffer, buildInsert($table, $columns, $batch));
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

/** Builds one multi-row INSERT statement from a batch of pre-quoted value tuples. */
function buildInsert(string $table, array $columns, array $valueTuples): string {
    $colList = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
    return "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $valueTuples) . ";\n";
}