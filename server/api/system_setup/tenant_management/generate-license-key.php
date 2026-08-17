<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    require_once '../../../../includes/connection.php';
    
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $billing_cycle = $_GET['billing_cycle'] ?? 'monthly';
    
    // Get the last license key
    $stmt = $pdo->query("SELECT license_key FROM fuelingsys_public.subscriptions WHERE license_key IS NOT NULL AND license_key != '' ORDER BY id DESC LIMIT 1");
    $lastKey = $stmt->fetch();
    
    $nextNumber = 1;
    if ($lastKey && !empty($lastKey['license_key']) && preg_match('/LIC-\d{6}-[MA]-(\d+)/', $lastKey['license_key'], $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    }
    
    // Format: LIC-YYYYMM-CYCLE-NNNNNN
    $yearMonth = date('Ym', strtotime($start_date));
    $cycleCode = strtoupper(substr($billing_cycle, 0, 1));
    $licenseKey = 'LIC-' . $yearMonth . '-' . $cycleCode . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    
    echo json_encode(['success' => true, 'license_key' => $licenseKey]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
