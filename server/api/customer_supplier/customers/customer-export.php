<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_H-i-s') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo "Unauthorized access";
    exit;
}

try {
    // Get base currency symbol
    $stmt = $pdo->prepare("
        SELECT c.symbol 
        FROM tenant_currencies tc 
        JOIN fuelingsys_public.currencies c ON tc.currency_id = c.id 
        WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
    ");
    $stmt->execute([$tenant_id]);
    $currency = $stmt->fetch();
    $currency_symbol = $currency['symbol'] ?? '$';

    // Get all customers
    $stmt = $pdo->prepare("
        SELECT customer_code, customer_name, primary_phone, secondary_phone, 
               email, address, identity_card_no, opening_debit_amount, 
               opening_credit_amount, current_balance, is_blacklisted, created_at
        FROM customers 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output Excel content
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Customer Code</th>';
    echo '<th>Customer Name</th>';
    echo '<th>Primary Phone</th>';
    echo '<th>Secondary Phone</th>';
    echo '<th>Email</th>';
    echo '<th>Address</th>';
    echo '<th>Identity Card</th>';
    echo '<th>Opening Debit</th>';
    echo '<th>Opening Credit</th>';
    echo '<th>Current Balance</th>';
    echo '<th>Status</th>';
    echo '<th>Created Date</th>';
    echo '</tr>';

    foreach ($customers as $customer) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($customer['customer_code']) . '</td>';
        echo '<td>' . htmlspecialchars($customer['customer_name']) . '</td>';
        echo '<td>' . htmlspecialchars($customer['primary_phone'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($customer['secondary_phone'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($customer['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($customer['address'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($customer['identity_card_no'] ?? '') . '</td>';
        echo '<td>' . $currency_symbol . number_format($customer['opening_debit_amount'], 2) . '</td>';
        echo '<td>' . $currency_symbol . number_format($customer['opening_credit_amount'], 2) . '</td>';
        echo '<td>' . $currency_symbol . number_format($customer['current_balance'], 2) . '</td>';
        echo '<td>' . ($customer['is_blacklisted'] ? 'Blacklisted' : 'Active') . '</td>';
        echo '<td>' . date('Y-m-d H:i:s', strtotime($customer['created_at'])) . '</td>';
        echo '</tr>';
    }

    echo '</table>';

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>