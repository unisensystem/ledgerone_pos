<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/mobile_auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$auth = resolveMobileAuth($pdo);
if (!$auth) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$tenant_id = $auth['tenant_id'];

try {
    $stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE tenant_id = ? AND is_active = 1 ORDER BY company_name");
    $stmt->execute([$tenant_id]);
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'companies' => $companies]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
