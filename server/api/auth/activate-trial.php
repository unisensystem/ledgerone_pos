<?php
session_start();
require_once '../../../includes/connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$tenant_id = $data['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Tenant ID required']);
    exit;
}

try {
    $publicDb = new PDO("mysql:host=$servername;dbname=ledgerone_public", $username, $password);
    $publicDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if trial already used
    $stmt = $publicDb->prepare("SELECT trial_used FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo json_encode(['success' => false, 'message' => 'Tenant not found']);
        exit;
    }
    
    if ($tenant['trial_used']) {
        echo json_encode(['success' => false, 'message' => 'Trial already used']);
        exit;
    }
    
    // Activate trial subscription
    $stmt = $publicDb->prepare("
        INSERT INTO subscriptions (
            tenant_id, plan, billing_cycle, status, payment_status,
            start_date, end_date, is_trial, created_at
        ) VALUES (?, 'trial', 'trial', 'active', 'trial', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 1, NOW())
    ");
    $stmt->execute([$tenant_id]);
    
    // Mark trial as used
    $stmt = $publicDb->prepare("UPDATE tenants SET trial_used = 1 WHERE id = ?");
    $stmt->execute([$tenant_id]);
    
    echo json_encode(['success' => true, 'message' => 'Trial activated']);
} catch (Exception $e) {
    error_log('Trial activation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Activation failed']);
}
