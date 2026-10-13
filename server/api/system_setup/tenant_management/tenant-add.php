<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    require_once '../../../../includes/connection.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $business_name = $data['business_name'] ?? '';
    $legal_name = $data['legal_name'] ?? null;
    $tax_id = $data['tax_id'] ?? null;
    $subdomain = $data['subdomain'] ?? '';
    $contact_email = $data['contact_email'] ?? null;
    $contact_phone = $data['contact_phone'] ?? null;
    $status = $data['status'] ?? 'pending';
    $onboarding_stage = $data['onboarding_stage'] ?? 'initial';
    $language = $data['language'] ?? 'en';
    
    if (empty($business_name) || empty($subdomain)) {
        echo json_encode(['success' => false, 'message' => 'Business name and subdomain are required']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO fuelingsys_public.tenants (business_name, legal_name, tax_id, subdomain, contact_email, contact_phone, status, onboarding_stage, language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$business_name, $legal_name, $tax_id, $subdomain, $contact_email, $contact_phone, $status, $onboarding_stage, $language])) {
        echo json_encode(['success' => true, 'message' => 'Tenant added successfully', 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add tenant']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
