<?php
require_once '../../../../includes/connection.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo "Unauthorized access";
    exit;
}

try {
    // Get user's full name and base currency
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch();
    $user_name = $user['full_name'] ?? 'System User';

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
        SELECT customer_code, customer_name, primary_phone, email, 
               current_balance, is_blacklisted, created_at
        FROM customers 
        WHERE tenant_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create simple PDF using basic PDF structure
    $pdf_content = generateSimplePDF($user_name, $currency_symbol, $customers);
    
    // Set PDF headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_H-i-s') . '.pdf"');
    header('Content-Length: ' . strlen($pdf_content));
    
    echo $pdf_content;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

function generateSimplePDF($user_name, $currency_symbol, $customers) {
    // Basic PDF structure
    $pdf = "%PDF-1.4\n";
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    
    // Create content stream
    $content = "BT\n/F1 16 Tf\n50 750 Td\n(FuelingSys ERP - Customers List) Tj\n";
    $content .= "0 -20 Td\n/F1 10 Tf\n(Generated: " . date('F j, Y g:i A') . " | User: " . $user_name . ") Tj\n";
    $content .= "0 -30 Td\n(Customer Code    Customer Name    Phone    Email    Balance    Status) Tj\n";
    
    $y = 680;
    foreach ($customers as $customer) {
        if ($y < 50) break; // Prevent overflow
        $balance = floatval($customer['current_balance']);
        $balanceText = $currency_symbol . number_format($balance, 2);
        $status = $customer['is_blacklisted'] ? 'Blacklisted' : 'Active';
        
        $line = substr($customer['customer_code'], 0, 12) . "    " . 
                substr($customer['customer_name'], 0, 20) . "    " . 
                substr($customer['primary_phone'] ?? '-', 0, 12) . "    " . 
                substr($customer['email'] ?? '-', 0, 20) . "    " . 
                $balanceText . "    " . $status;
        
        $content .= "0 -15 Td\n(" . $line . ") Tj\n";
        $y -= 15;
    }
    
    $content .= "ET";
    
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $pdf .= "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";
    $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    
    $pdf .= "xref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000125 00000 n \n0000000230 00000 n \n0000000300 00000 n \n";
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n350\n%%EOF";
    
    return $pdf;
}
?>
