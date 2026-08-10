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

$data = json_decode(file_get_contents('php://input'), true);

$customer_name = trim($data['customerName'] ?? '');
$phone = preg_replace('/\D/', '', trim($data['phone'] ?? ''));
$email = trim($data['email'] ?? '');

if (empty($customer_name) || empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Customer name and phone are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT customer_code FROM customer_entry WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $last_code = $row['customer_code'];
        $parts = explode('-', $last_code);
        $next_num = intval($parts[2]) + 1;
        $customer_code = $parts[0] . '-' . $parts[1] . '-' . str_pad($next_num, 2, '0', STR_PAD_LEFT);
    } else {
        $customer_code = 'CUST-' . date('ym') . '-01';
    }
    
    $stmt = $pdo->prepare("INSERT INTO customer_entry (tenant_id, customer_code, customer_name, phone_number, email) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $customer_code, $customer_name, $phone, $email]);
    
    echo json_encode(['success' => true, 'message' => 'Customer added successfully', 'customer_code' => $customer_code]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}