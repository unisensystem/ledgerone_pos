<?php
error_log("Script started");
session_start();
error_log("Session started");
require_once '../../../../includes/connection.php';
error_log("Connection included");
header('Content-Type: application/json');
error_log("Headers sent");

try {
    $stmt = $pdo->prepare("SELECT id, code, name, symbol FROM fuelingsys_public.currencies WHERE is_active = 1 ORDER BY name LIMIT 1");
    $stmt->execute();
    $currency = $stmt->fetch();
    $output = json_encode(['test' => 'working', 'session' => isset($_SESSION['tenant_id']), 'db' => $currency]);
    error_log("Output: " . $output);
    echo $output;
} catch (Exception $e) {
    $output = json_encode(['test' => 'working', 'session' => isset($_SESSION['tenant_id']), 'error' => $e->getMessage()]);
    error_log("Error output: " . $output);
    echo $output;
}
error_log("Script ending");
exit;
