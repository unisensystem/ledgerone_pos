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
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$required_fields = ['date', 'branch_id', 'station_id', 'product_id', 'unit_id', 'opening_reading'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Check if last invoice is completed
$checkStmt = $pdo->prepare("
    SELECT closing_reading FROM station_daily_usage 
    WHERE tenant_id = ? AND branch_id = ? AND station_id = ? 
    ORDER BY usage_date DESC, id DESC LIMIT 1
");
$checkStmt->execute([$tenant_id, $input['branch_id'], $input['station_id']]);
$lastRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($lastRecord && $lastRecord['closing_reading'] === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please complete the last invoice first by adding closing reading']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO station_daily_usage 
        (tenant_id, station_id, branch_id, product_id, unit_id, usage_date, opening_reading, recorded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $tenant_id,
        $input['station_id'],
        $input['branch_id'],
        $input['product_id'],
        $input['unit_id'],
        $input['date'],
        $input['opening_reading'],
        $user_id
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    echo json_encode(['success' => true, 'message' => 'Meter opening reading saved successfully', 'invoice_id' => $invoice_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>