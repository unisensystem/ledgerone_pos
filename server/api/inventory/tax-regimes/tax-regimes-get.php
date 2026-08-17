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
    $regime_id = $_GET['id'] ?? '';
    
    if (empty($regime_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax regime ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT tr.*, c.country_name
        FROM tax_regimes tr
        LEFT JOIN countries c ON tr.country_id = c.id
        WHERE tr.id = ? AND (tr.tenant_id = ? OR tr.tenant_id = 0)
    ");
    $stmt->execute([$regime_id, $tenant_id]);
    $taxRegime = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taxRegime) {
        echo json_encode(['success' => false, 'message' => 'Tax regime not found']);
        exit;
    }
    
    $taxRegime['is_system_record'] = ($taxRegime['tenant_id'] == 0);
    $taxRegime['is_editable'] = ($taxRegime['tenant_id'] != 0);
    $taxRegime['is_deletable'] = ($taxRegime['tenant_id'] != 0);
    
    echo json_encode(['success' => true, 'tax_regime' => $taxRegime]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
