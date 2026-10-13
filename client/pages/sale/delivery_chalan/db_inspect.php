<?php
/**
 * db_inspect.php
 * -----------------------------------------------------------------
 * TEMPORARY debug page — upload this to the SAME folder as your
 * api file (the one that does: require_once '../../../../includes/connection.php';)
 * so the relative path to connection.php stays correct.
 *
 * Open it in the browser like:
 *   https://yourdomain.com/path/to/db_inspect.php?key=letme123&table=delivery_chalan
 *
 * ⚠️ DELETE THIS FILE FROM THE SERVER AS SOON AS YOU'RE DONE.
 * It exposes your DB structure/data to anyone who guesses the URL,
 * so it must not be left online.
 * -----------------------------------------------------------------
 */

// ---- simple protection so random visitors can't open it ----
$SECRET_KEY = 'letme123'; // change this to anything you like before uploading

if (($_GET['key'] ?? '') !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Add ?key=YOUR_SECRET to the URL.');
}

require_once '../../../../includes/connection.php'; // must give you $pdo

echo "<html><head><meta charset='utf-8'><title>DB Inspector</title>";
echo "<style>
body{font-family:Arial,sans-serif;margin:20px;background:#f7f7f7;}
h2{color:#333;}
table{border-collapse:collapse;margin-bottom:30px;background:#fff;width:100%;}
th,td{border:1px solid #ccc;padding:6px 10px;font-size:13px;text-align:left;}
th{background:#333;color:#fff;}
.section{margin-bottom:40px;}
.small{color:#777;font-size:12px;}
</style></head><body>";

echo "<h1>DB Inspector</h1>";
echo "<p class='small'>⚠️ Delete this file after use.</p>";

// ---- list all tables (useful if you want to browse everything) ----
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='section'><h2>All Tables</h2><p>";
    foreach ($tables as $t) {
        $url = '?key=' . urlencode($SECRET_KEY) . '&table=' . urlencode($t);
        echo "<a href='" . htmlspecialchars($url) . "'>" . htmlspecialchars($t) . "</a> | ";
    }
    echo "</p></div>";
} catch (Exception $e) {
    echo "<p>Could not list tables: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ---- table to inspect (default: delivery_chalan) ----
$table = $_GET['table'] ?? 'delivery_chalan';

// only allow safe table names (letters, numbers, underscore)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    die('Invalid table name.');
}

echo "<div class='section'><h2>Structure: " . htmlspecialchars($table) . "</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($cols as $c) {
        echo "<tr>
            <td>{$c['Field']}</td>
            <td>{$c['Type']}</td>
            <td>{$c['Null']}</td>
            <td>{$c['Key']}</td>
            <td>" . htmlspecialchars((string)$c['Default']) . "</td>
            <td>{$c['Extra']}</td>
        </tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// ---- CREATE TABLE statement (shows ENUM values etc clearly) ----
echo "<div class='section'><h2>SHOW CREATE TABLE</h2>";
try {
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    echo "<pre style='background:#fff;border:1px solid #ccc;padding:10px;white-space:pre-wrap;'>"
        . htmlspecialchars($create['Create Table'] ?? '') . "</pre>";
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

// ---- sample rows (last 10) ----
echo "<div class='section'><h2>Sample Data (latest 10 rows)</h2>";
try {
    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "<p>No rows found.</p>";
    } else {
        echo "<table><tr>";
        foreach (array_keys($rows[0]) as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars((string)$val) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

echo "</body></html>";
