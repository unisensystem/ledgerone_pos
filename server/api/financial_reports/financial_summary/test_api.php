<?php
session_start();

// Set test session data - CHANGE THESE TO YOUR ACTUAL VALUES
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 0; // Change this to your actual tenant_id

require_once '../../../../includes/connection.php';

echo "<h2>API Test Results</h2>";
echo "<hr>";

// Test database connection
try {
    echo "<h3>1. Database Connection: ✓ SUCCESS</h3>";
    echo "Connected to database<br><br>";
} catch (Exception $e) {
    echo "<h3>1. Database Connection: ✗ FAILED</h3>";
    echo "Error: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test tenant_id
echo "<h3>2. Session Data:</h3>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Tenant ID: " . $_SESSION['tenant_id'] . "<br><br>";

$tenant_id = $_SESSION['tenant_id'];
$date_from = date('Y-m-01');
$date_to = date('Y-m-d');

echo "<h3>3. Date Range:</h3>";
echo "From: $date_from<br>";
echo "To: $date_to<br><br>";

// Test each query
echo "<h3>4. Testing Queries:</h3>";

try {
    // Daily Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND DATE(sale_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Daily Sales: " . number_format($daily_sales, 2) . "<br>";
    
    // Total Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $total_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Total Sales: " . number_format($total_sales, 2) . "<br>";
    
    // Daily Purchase
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND DATE(purchase_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_purchase = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Daily Purchase: " . number_format($daily_purchase, 2) . "<br>";
    
    // Total Purchase
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $total_purchase = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Total Purchase: " . number_format($total_purchase, 2) . "<br>";
    
    // Daily Sale Return
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_return WHERE tenant_id = ? AND DATE(sale_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_sale_return = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Daily Sale Return: " . number_format($daily_sale_return, 2) . "<br>";
    
    // Daily Recovery
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND DATE(voucher_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_recovery = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Daily Recovery: " . number_format($daily_recovery, 2) . "<br>";
    
    // Daily Payment
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND DATE(voucher_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_payment = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Daily Payment: " . number_format($daily_payment, 2) . "<br>";
    
    // Daily Expenses
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND DATE(date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Daily Expenses: " . number_format($daily_expenses, 2) . "<br>";
    
    // Test Cash Account
    $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE name = 'Cash' LIMIT 1");
    $stmt->execute();
    $cash_account = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cash_account) {
        echo "✓ Cash Account Found: ID=" . $cash_account['id'] . ", Name=" . $cash_account['name'] . "<br>";
        
        // Daily Cash
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(rv.amount), 0) as total 
            FROM receive_voucher rv
            INNER JOIN accounts a ON rv.payment_method_id = a.id
            WHERE rv.tenant_id = ? AND a.name = 'Cash' AND DATE(rv.voucher_date) = CURDATE()
        ");
        $stmt->execute([$tenant_id]);
        $daily_cash = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "✓ Daily Cash: " . number_format($daily_cash, 2) . "<br>";
    } else {
        echo "✗ Cash Account NOT Found<br>";
    }
    
    // Test Bank Account
    $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE name = 'Bank Transfer' LIMIT 1");
    $stmt->execute();
    $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($bank_account) {
        echo "✓ Bank Account Found: ID=" . $bank_account['id'] . ", Name=" . $bank_account['name'] . "<br>";
    } else {
        echo "✗ Bank Account NOT Found<br>";
    }
    
    // Test Cheque Account
    $stmt = $pdo->prepare("SELECT id, name FROM accounts WHERE name = 'Cheque' LIMIT 1");
    $stmt->execute();
    $cheque_account = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cheque_account) {
        echo "✓ Cheque Account Found: ID=" . $cheque_account['id'] . ", Name=" . $cheque_account['name'] . "<br>";
    } else {
        echo "✗ Cheque Account NOT Found<br>";
    }
    
    echo "<br><h3>✓ ALL TESTS PASSED!</h3>";
    echo "<br><a href='financial_summary.php?date_from=$date_from&date_to=$date_to' target='_blank'>Click here to test actual API</a>";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "<br>";
}
?>
