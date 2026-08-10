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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['date'], $input['branch_id'], $input['station_id'], $input['product_id'], $input['unit_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id FROM station_daily_usage 
        WHERE tenant_id = ? AND usage_date = ? AND branch_id = ? AND station_id = ? AND product_id = ? AND unit_id = ?
    ");
    $stmt->execute([
        $tenant_id,
        $input['date'],
        $input['branch_id'],
        $input['station_id'],
        $input['product_id'],
        $input['unit_id']
    ]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'exists' => $exists ? true : false, 'invoice_id' => $exists['id'] ?? null]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
