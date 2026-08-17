<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $tax_rate_id = $_GET['id'] ?? '';
    
    if (empty($tax_rate_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax rate ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT *
        FROM tax_rates
        WHERE id = ? AND (tenant_id = ? OR tenant_id = 0)
    ");
    $stmt->execute([$tax_rate_id, $tenant_id]);
    $taxRate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taxRate) {
        echo json_encode(['success' => false, 'message' => 'Tax rate not found']);
        exit;
    }
    
    // Add metadata
    $taxRate['is_system_record'] = ($taxRate['tenant_id'] == 0);
    $taxRate['is_editable'] = ($taxRate['tenant_id'] != 0);
    $taxRate['is_deletable'] = ($taxRate['tenant_id'] != 0);
    
    echo json_encode(['success' => true, 'tax_rate' => $taxRate]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
