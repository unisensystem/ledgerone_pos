<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    require_once '../../../../includes/connection.php';
    
    $stmt = $pdo->query("SELECT id, business_name, legal_name, subdomain, contact_email, contact_phone, status, onboarding_stage, language, created_at FROM fuelingsys_public.tenants ORDER BY created_at DESC");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $tenants]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
