<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

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
    $tax_rate_id = $_POST['id'] ?? '';
    
    if (empty($tax_rate_id)) {
        echo json_encode(['success' => false, 'message' => 'Tax rate ID is required']);
        exit;
    }
    
    // Verify tax rate exists and belongs to tenant
    $stmt = $pdo->prepare("SELECT * FROM tax_rates WHERE id = ?");
    $stmt->execute([$tax_rate_id]);
    $taxRate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taxRate) {
        echo json_encode(['success' => false, 'message' => 'Tax rate not found']);
        exit;
    }
    
    // Check if it's a system record
    if ($taxRate['tenant_id'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete system tax rates']);
        exit;
    }
    
    // Check if it belongs to current tenant
    if ($taxRate['tenant_id'] != $tenant_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Delete tax rate
    $stmt = $pdo->prepare("DELETE FROM tax_rates WHERE id = ?");
    $stmt->execute([$tax_rate_id]);

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Tax Rate', $tax_rate_id, $taxRate['tax_name'],
        "Deleted tax rate \"{$taxRate['tax_name']}\" ({$taxRate['rate_percentage']}%)",
        $taxRate, null
    );

    echo json_encode([
        'success' => true,
        'message' => 'Tax rate deleted successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
