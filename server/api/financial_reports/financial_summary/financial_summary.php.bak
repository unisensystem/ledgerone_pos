<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || $tenant_id === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login']);
    exit;
}

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

try {
    $data = [];
    
    // Calculate comparison periods
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $last_period_days = (strtotime($date_to) - strtotime($date_from)) / 86400;
    $last_month_from = date('Y-m-d', strtotime($date_from . ' -' . ceil($last_period_days) . ' days'));
    $last_month_to = date('Y-m-d', strtotime($date_to . ' -' . ceil($last_period_days) . ' days'));
    
    // Daily Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND DATE(sale_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND DATE(sale_date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_sales_change'] = $yesterday_sales > 0 ? (($data['daily_sales'] - $yesterday_sales) / $yesterday_sales) * 100 : ($data['daily_sales'] > 0 ? 100 : 0);
    
    // Total Sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_invoice WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_sales = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_sales_change'] = $last_month_sales > 0 ? (($data['total_sales'] - $last_month_sales) / $last_month_sales) * 100 : ($data['total_sales'] > 0 ? 100 : 0);
    
    // Daily Purchase
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND DATE(purchase_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_purchase'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND DATE(purchase_date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_purchase = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_purchase_change'] = $yesterday_purchase > 0 ? (($data['daily_purchase'] - $yesterday_purchase) / $yesterday_purchase) * 100 : ($data['daily_purchase'] > 0 ? 100 : 0);
    
    // Total Purchase
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_purchase'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_invoice WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_purchase = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_purchase_change'] = $last_month_purchase > 0 ? (($data['total_purchase'] - $last_month_purchase) / $last_month_purchase) * 100 : ($data['total_purchase'] > 0 ? 100 : 0);
    
    // Daily Sale Return
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_return WHERE tenant_id = ? AND DATE(sale_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_sale_return'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_return WHERE tenant_id = ? AND DATE(sale_date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_sale_return = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_sale_return_change'] = $yesterday_sale_return > 0 ? (($data['daily_sale_return'] - $yesterday_sale_return) / $yesterday_sale_return) * 100 : ($data['daily_sale_return'] > 0 ? 100 : 0);
    
    // Total Sale Return
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_return WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_sale_return'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM sale_return WHERE tenant_id = ? AND sale_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_sale_return = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_sale_return_change'] = $last_month_sale_return > 0 ? (($data['total_sale_return'] - $last_month_sale_return) / $last_month_sale_return) * 100 : ($data['total_sale_return'] > 0 ? 100 : 0);
    
    // Daily Purchase Return
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND DATE(purchase_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_purchase_return'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND DATE(purchase_date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_purchase_return = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_purchase_return_change'] = $yesterday_purchase_return > 0 ? (($data['daily_purchase_return'] - $yesterday_purchase_return) / $yesterday_purchase_return) * 100 : ($data['daily_purchase_return'] > 0 ? 100 : 0);
    
    // Total Purchase Return
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_purchase_return'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount), 0) as total FROM purchase_return WHERE tenant_id = ? AND purchase_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_purchase_return = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_purchase_return_change'] = $last_month_purchase_return > 0 ? (($data['total_purchase_return'] - $last_month_purchase_return) / $last_month_purchase_return) * 100 : ($data['total_purchase_return'] > 0 ? 100 : 0);
    
    // Daily Recovery
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND DATE(voucher_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_recovery'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND DATE(voucher_date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_recovery = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_recovery_change'] = $yesterday_recovery > 0 ? (($data['daily_recovery'] - $yesterday_recovery) / $yesterday_recovery) * 100 : ($data['daily_recovery'] > 0 ? 100 : 0);
    
    // Total Recovery
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND voucher_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_recovery'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM receive_voucher WHERE tenant_id = ? AND voucher_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_recovery = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_recovery_change'] = $last_month_recovery > 0 ? (($data['total_recovery'] - $last_month_recovery) / $last_month_recovery) * 100 : ($data['total_recovery'] > 0 ? 100 : 0);
    
    // Daily Payment
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND DATE(voucher_date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_payment'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND DATE(voucher_date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_payment = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_payment_change'] = $yesterday_payment > 0 ? (($data['daily_payment'] - $yesterday_payment) / $yesterday_payment) * 100 : ($data['daily_payment'] > 0 ? 100 : 0);
    
    // Total Payment
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND voucher_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_payment'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_voucher WHERE tenant_id = ? AND voucher_date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_payment = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_payment_change'] = $last_month_payment > 0 ? (($data['total_payment'] - $last_month_payment) / $last_month_payment) * 100 : ($data['total_payment'] > 0 ? 100 : 0);
    
    // Daily Expenses
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND DATE(date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $data['daily_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND DATE(date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_expenses_change'] = $yesterday_expenses > 0 ? (($data['daily_expenses'] - $yesterday_expenses) / $yesterday_expenses) * 100 : ($data['daily_expenses'] > 0 ? 100 : 0);
    
    // Total Expenses
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_expenses_change'] = $last_month_expenses > 0 ? (($data['total_expenses'] - $last_month_expenses) / $last_month_expenses) * 100 : ($data['total_expenses'] > 0 ? 100 : 0);
    
    // Daily Cash (payment_method_id = 7)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND DATE(rv.voucher_date) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $data['daily_cash'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND DATE(rv.voucher_date) = ?
    ");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_cash = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_cash_change'] = $yesterday_cash > 0 ? (($data['daily_cash'] - $yesterday_cash) / $yesterday_cash) * 100 : ($data['daily_cash'] > 0 ? 100 : 0);
    
    // Total Cash
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_cash'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_cash = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_cash_change'] = $last_month_cash > 0 ? (($data['total_cash'] - $last_month_cash) / $last_month_cash) * 100 : ($data['total_cash'] > 0 ? 100 : 0);
    
    // Daily Bank (payment_method_id = 5)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND DATE(rv.voucher_date) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $data['daily_bank'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND DATE(rv.voucher_date) = ?
    ");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_bank = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_bank_change'] = $yesterday_bank > 0 ? (($data['daily_bank'] - $yesterday_bank) / $yesterday_bank) * 100 : ($data['daily_bank'] > 0 ? 100 : 0);
    
    // Total Bank
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_bank'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_bank = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_bank_change'] = $last_month_bank > 0 ? (($data['total_bank'] - $last_month_bank) / $last_month_bank) * 100 : ($data['total_bank'] > 0 ? 100 : 0);
    
    // Daily Cheque (payment_method_id = 6)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND DATE(rv.voucher_date) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $data['daily_cheque'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND DATE(rv.voucher_date) = ?
    ");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_cheque = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['daily_cheque_change'] = $yesterday_cheque > 0 ? (($data['daily_cheque'] - $yesterday_cheque) / $yesterday_cheque) * 100 : ($data['daily_cheque'] > 0 ? 100 : 0);
    
    // Total Cheque
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $data['total_cheque'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rv.amount), 0) as total 
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_cheque = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $data['total_cheque_change'] = $last_month_cheque > 0 ? (($data['total_cheque'] - $last_month_cheque) / $last_month_cheque) * 100 : ($data['total_cheque'] > 0 ? 100 : 0);
    
    echo json_encode(['success' => true, 'data' => $data]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
