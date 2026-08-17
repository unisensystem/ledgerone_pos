<?php
session_start();
require_once '../../../includes/connection.php';

header('Content-Type: application/json');

$tenant_id = $_GET['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Tenant ID required']);
    exit;
}

if (isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] == $tenant_id) {
} elseif (isset($_SESSION['pending_checkout_tenant_id']) && $_SESSION['pending_checkout_tenant_id'] == $tenant_id) {
    if (!isset($_SESSION['checkout_expires']) || $_SESSION['checkout_expires'] < time()) {
        unset($_SESSION['pending_checkout_tenant_id']);
        unset($_SESSION['checkout_expires']);
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $publicDb = new PDO("mysql:host=$servername;dbname=ledgerone_public", $username, $password);
    $publicDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $publicDb->prepare("SELECT business_name FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tenant_name' => $tenant['business_name'] ?? 'Your Company'
    ]);
} catch (Exception $e) {
    error_log('Validate checkout DB error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'tenant_name' => 'Your Company']);
}