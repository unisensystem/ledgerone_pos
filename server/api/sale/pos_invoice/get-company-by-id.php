<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$company_id = $_GET['id'] ?? null;

if (!$company_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Company ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT company_name, legal_name, email, phone, website, address, city, state, zipcode, country, logo_url FROM companies WHERE id = ? AND tenant_id = ? AND is_active = 1");
    $stmt->execute([$company_id, $tenant_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'company' => $company]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
