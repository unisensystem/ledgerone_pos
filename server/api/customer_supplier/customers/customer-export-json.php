<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_H-i-s') . '.json"');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Get all customers
    $stmt = $pdo->prepare("
        SELECT customer_code, customer_name, primary_phone, secondary_phone, 
               email, address, identity_card_no, opening_debit_amount, 
               opening_credit_amount, current_balance, is_blacklisted, 
               created_at, updated_at
        FROM customers 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for JSON export
    $export_data = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'tenant_id' => $tenant_id,
            'total_customers' => count($customers),
            'exported_by' => $user_id
        ],
        'customers' => $customers
    ];

    echo json_encode($export_data, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>