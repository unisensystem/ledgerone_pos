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

$backupName = $_GET['name'] ?? 'backup_' . date('Y_m_d_H_i_s');
$backupName = preg_replace('/[^a-zA-Z0-9_-]/', '', $backupName);
$filename = $backupName . '.sql';

try {
    $output = "-- Database Backup for Tenant ID: {$tenant_id}\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

    // Only get BASE TABLES, skip views (views cause definer errors)
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $output .= "\n\n-- Table: {$table}\n";
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $row[1] . ";\n\n";

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'tenant_id'");
        $hasTenantId = $stmt->rowCount() > 0;

        if ($hasTenantId) {
            $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $output .= "INSERT INTO `{$table}` (";
            $output .= implode(', ', array_map(fn($col) => "`{$col}`", array_keys($row)));
            $output .= ") VALUES (";
            $output .= implode(', ', array_map(fn($val) => $pdo->quote($val), array_values($row)));
            $output .= ");\n";
        }
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($output));
    header('Pragma: no-cache');
    echo $output;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
}
exit;
