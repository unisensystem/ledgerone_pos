<?php
require_once '../../includes/connection.php';
include_once '../../includes/dashboard.php';
date_default_timezone_set('Asia/Karachi');
$settings_file = 'backup_settings.json';

// Load saved settings
$settings = file_exists($settings_file) ? json_decode(file_get_contents($settings_file), true) : [];
$default_path = $settings['backup_path'] ?? getcwd();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backup_path = $_POST['backup_path'] ?? $default_path;
    
    // Save settings
    file_put_contents($settings_file, json_encode(['backup_path' => $backup_path]));
    
    // Generate backup
    $filename = 'fuelingsys_erp_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = rtrim($backup_path, '/\\') . DIRECTORY_SEPARATOR . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($backup_path)) {
        mkdir($backup_path, 0777, true);
    }
    
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $backup = "-- Database Backup: fuelingsys_erp\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $backup .= "DROP TABLE IF EXISTS `$table`;\n";
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $backup .= $create[1] . ";\n\n";
            
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
            if ($rows) {
                $backup .= "INSERT INTO `$table` VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $quoted = array_map(function($val) use ($pdo) { return $val === null ? 'NULL' : $pdo->quote($val); }, array_values($row));
                    $values[] = "(" . implode(",", $quoted) . ")";
                }
                $backup .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        file_put_contents($filepath, $backup);
        $success = "Backup created: $filepath";
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Backup</title>
    <style>
        body { font-family: Arial; margin: 0; padding: 20px; text-align: center; }
        input[type="text"] { width: 400px; padding: 5px; }
        button { padding: 8px 15px; background: #007cba; color: white; border: none; cursor: pointer; }
        .success { color: green; }
        .error { color: red; }
    </style>
    <script>
        function selectFolder() {
            const path = prompt('Enter full backup path (e.g., C:\\xampp\\htdocs\\):', document.getElementById('backup_path').value);
            if (path) document.getElementById('backup_path').value = path;
        }
    </script>
</head>
<body>
    <h2>Database Backup Generator</h2>
    <a href="../../client/pages/Navigation Forms/dashboard.php"><button type="button">Back to Dashboard</button></a><br><br>
    
    <?php if (isset($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <label>Backup Location:</label><br>
        <input type="text" name="backup_path" id="backup_path" value="<?= htmlspecialchars($default_path) ?>" required>
        <button type="button" onclick="selectFolder()">Browse</button><br><br>
        <button type="submit">Generate Backup</button>
    </form>
</body>
</html>