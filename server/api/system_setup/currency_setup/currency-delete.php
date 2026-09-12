<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Currency ID required']);
        exit;
    }
    
    // Check if currency is base currency (grab full row for the Activity Log)
    $stmt = $pdo->prepare("SELECT tc.*, c.code FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.id = ? AND tc.tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    $currency = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currency) {
        echo json_encode(['success' => false, 'message' => 'Currency not found']);
        exit;
    }
    
    if ($currency['is_base_currency']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete base currency']);
        exit;
    }
    
    // Delete currency
    $stmt = $pdo->prepare("DELETE FROM tenant_currencies WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);

    logActivity(
        $pdo, $tenant_id, $user_id, 'delete', 'Currency', $id, $currency['code'],
        "Deleted currency \"{$currency['code']}\"",
        $currency, null
    );

    echo json_encode(['success' => true, 'message' => 'Currency deleted successfully']);
} catch (Exception $e) {
    error_log('Currency delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}