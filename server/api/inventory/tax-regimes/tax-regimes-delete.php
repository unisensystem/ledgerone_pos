<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    $regime_id = $_POST['id'] ?? '';
    
    if (empty($regime_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax regime ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT tenant_id FROM tax_regimes WHERE id = ?");
    $stmt->execute([$regime_id]);
    $taxRegime = $stmt->fetch();
    
    if (!$taxRegime) {
        echo json_encode(['success' => false, 'message' => 'Tax regime not found']);
        exit;
    }
    
    if ($taxRegime['tenant_id'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete system tax regimes']);
        exit;
    }
    
    if ($taxRegime['tenant_id'] != $tenant_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM tax_regimes WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$regime_id, $tenant_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tax regime deleted successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
