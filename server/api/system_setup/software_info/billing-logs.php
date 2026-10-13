<?php
require_once '../../../../includes/connection.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            invoice_number,
            billing_cycle_start,
            billing_cycle_end,
            amount_paid,
            currency,
            payment_method,
            payment_status,
            created_at
        FROM ledgerone_public.billing_logs
        WHERE tenant_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$tenant_id]);
    $logs = $stmt->fetchAll();
    
    $data = array_map(function($log) {
        return [
            'invoice_number' => $log['invoice_number'],
            'billing_period' => date('M d, Y', strtotime($log['billing_cycle_start'])) . ' - ' . date('M d, Y', strtotime($log['billing_cycle_end'])),
            'amount_paid' => number_format($log['amount_paid'], 2),
            'payment_method' => ucfirst($log['payment_method']),
            'payment_status' => $log['payment_status'],
            'created_at' => date('M d, Y', strtotime($log['created_at']))
        ];
    }, $logs);
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
