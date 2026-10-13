<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../../includes/connection.php';
require_once 'currency-converter.php';

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
$target_currency_id = $_GET['currency_id'] ?? null;

try {
    // Initialize currency converter
    $converter = new CurrencyConverter($pdo, $tenant_id);
    
    // Get base currency
    $stmt = $pdo->prepare("SELECT currency_id FROM tenant_currencies WHERE tenant_id = ? AND is_base_currency = 1");
    $stmt->execute([$tenant_id]);
    $baseCurrency = $stmt->fetch(PDO::FETCH_ASSOC);
    $base_currency_id = $baseCurrency['currency_id'];
    
    // If no target currency specified, use base currency
    if (!$target_currency_id) {
        $target_currency_id = $base_currency_id;
    }
    
    // Helper function to convert amounts with currency_id
    function convertAmount($pdo, $converter, $table, $amount_field, $tenant_id, $date_field, $date_value, $base_currency_id, $target_currency_id, $is_range = false) {
        $date_condition = $is_range ? "$date_field BETWEEN ? AND ?" : "DATE($date_field) = ?";
        
        $stmt = $pdo->prepare("SELECT $amount_field as amount, currency_id FROM $table WHERE tenant_id = ? AND $date_condition");
        if ($is_range) {
            $stmt->execute([$tenant_id, $date_value[0], $date_value[1]]);
        } else {
            $stmt->execute([$tenant_id, $date_value]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = 0;
        foreach ($rows as $row) {
            $amount = $row['amount'] ?? 0;
            $from_currency = $row['currency_id'] ?? $base_currency_id;
            if ($from_currency != $target_currency_id) {
                $amount = $converter->convert($amount, $from_currency, $target_currency_id);
            }
            $total += $amount;
        }
        return $total;
    }
    
    $data = [];
    
    // Calculate comparison periods
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $last_period_days = (strtotime($date_to) - strtotime($date_from)) / 86400;
    $last_month_from = date('Y-m-d', strtotime($date_from . ' -' . ceil($last_period_days) . ' days'));
    $last_month_to = date('Y-m-d', strtotime($date_to . ' -' . ceil($last_period_days) . ' days'));
    
    // Daily Sales
    $data['daily_sales'] = convertAmount($pdo, $converter, 'sale_invoice', 'net_amount', $tenant_id, 'sale_date', date('Y-m-d'), $base_currency_id, $target_currency_id);
    $yesterday_sales = convertAmount($pdo, $converter, 'sale_invoice', 'net_amount', $tenant_id, 'sale_date', $yesterday, $base_currency_id, $target_currency_id);
    $data['daily_sales_change'] = $yesterday_sales > 0 ? (($data['daily_sales'] - $yesterday_sales) / $yesterday_sales) * 100 : ($data['daily_sales'] > 0 ? 100 : 0);
    
    // Total Sales
    $data['total_sales'] = convertAmount($pdo, $converter, 'sale_invoice', 'net_amount', $tenant_id, 'sale_date', [$date_from, $date_to], $base_currency_id, $target_currency_id, true);
    $last_month_sales = convertAmount($pdo, $converter, 'sale_invoice', 'net_amount', $tenant_id, 'sale_date', [$last_month_from, $last_month_to], $base_currency_id, $target_currency_id, true);
    $data['total_sales_change'] = $last_month_sales > 0 ? (($data['total_sales'] - $last_month_sales) / $last_month_sales) * 100 : ($data['total_sales'] > 0 ? 100 : 0);
    
    // Daily Purchase
    $data['daily_purchase'] = convertAmount($pdo, $converter, 'purchase_invoice', 'net_amount', $tenant_id, 'purchase_date', date('Y-m-d'), $base_currency_id, $target_currency_id);
    $yesterday_purchase = convertAmount($pdo, $converter, 'purchase_invoice', 'net_amount', $tenant_id, 'purchase_date', $yesterday, $base_currency_id, $target_currency_id);
    $data['daily_purchase_change'] = $yesterday_purchase > 0 ? (($data['daily_purchase'] - $yesterday_purchase) / $yesterday_purchase) * 100 : ($data['daily_purchase'] > 0 ? 100 : 0);
    
    // Total Purchase
    $data['total_purchase'] = convertAmount($pdo, $converter, 'purchase_invoice', 'net_amount', $tenant_id, 'purchase_date', [$date_from, $date_to], $base_currency_id, $target_currency_id, true);
    $last_month_purchase = convertAmount($pdo, $converter, 'purchase_invoice', 'net_amount', $tenant_id, 'purchase_date', [$last_month_from, $last_month_to], $base_currency_id, $target_currency_id, true);
    $data['total_purchase_change'] = $last_month_purchase > 0 ? (($data['total_purchase'] - $last_month_purchase) / $last_month_purchase) * 100 : ($data['total_purchase'] > 0 ? 100 : 0);
    
    // Daily Sale Return
    $data['daily_sale_return'] = convertAmount($pdo, $converter, 'sale_return', 'net_amount', $tenant_id, 'sale_date', date('Y-m-d'), $base_currency_id, $target_currency_id);
    $yesterday_sale_return = convertAmount($pdo, $converter, 'sale_return', 'net_amount', $tenant_id, 'sale_date', $yesterday, $base_currency_id, $target_currency_id);
    $data['daily_sale_return_change'] = $yesterday_sale_return > 0 ? (($data['daily_sale_return'] - $yesterday_sale_return) / $yesterday_sale_return) * 100 : ($data['daily_sale_return'] > 0 ? 100 : 0);
    
    // Total Sale Return
    $data['total_sale_return'] = convertAmount($pdo, $converter, 'sale_return', 'net_amount', $tenant_id, 'sale_date', [$date_from, $date_to], $base_currency_id, $target_currency_id, true);
    $last_month_sale_return = convertAmount($pdo, $converter, 'sale_return', 'net_amount', $tenant_id, 'sale_date', [$last_month_from, $last_month_to], $base_currency_id, $target_currency_id, true);
    $data['total_sale_return_change'] = $last_month_sale_return > 0 ? (($data['total_sale_return'] - $last_month_sale_return) / $last_month_sale_return) * 100 : ($data['total_sale_return'] > 0 ? 100 : 0);
    
    // Daily Purchase Return
    $data['daily_purchase_return'] = convertAmount($pdo, $converter, 'purchase_return', 'net_amount', $tenant_id, 'purchase_date', date('Y-m-d'), $base_currency_id, $target_currency_id);
    $yesterday_purchase_return = convertAmount($pdo, $converter, 'purchase_return', 'net_amount', $tenant_id, 'purchase_date', $yesterday, $base_currency_id, $target_currency_id);
    $data['daily_purchase_return_change'] = $yesterday_purchase_return > 0 ? (($data['daily_purchase_return'] - $yesterday_purchase_return) / $yesterday_purchase_return) * 100 : ($data['daily_purchase_return'] > 0 ? 100 : 0);
    
    // Total Purchase Return
    $data['total_purchase_return'] = convertAmount($pdo, $converter, 'purchase_return', 'net_amount', $tenant_id, 'purchase_date', [$date_from, $date_to], $base_currency_id, $target_currency_id, true);
    $last_month_purchase_return = convertAmount($pdo, $converter, 'purchase_return', 'net_amount', $tenant_id, 'purchase_date', [$last_month_from, $last_month_to], $base_currency_id, $target_currency_id, true);
    $data['total_purchase_return_change'] = $last_month_purchase_return > 0 ? (($data['total_purchase_return'] - $last_month_purchase_return) / $last_month_purchase_return) * 100 : ($data['total_purchase_return'] > 0 ? 100 : 0);
    
    // Daily Recovery
    $data['daily_recovery'] = convertAmount($pdo, $converter, 'receive_voucher', 'amount', $tenant_id, 'voucher_date', date('Y-m-d'), $base_currency_id, $target_currency_id);
    $yesterday_recovery = convertAmount($pdo, $converter, 'receive_voucher', 'amount', $tenant_id, 'voucher_date', $yesterday, $base_currency_id, $target_currency_id);
    $data['daily_recovery_change'] = $yesterday_recovery > 0 ? (($data['daily_recovery'] - $yesterday_recovery) / $yesterday_recovery) * 100 : ($data['daily_recovery'] > 0 ? 100 : 0);
    
    // Total Recovery
    $data['total_recovery'] = convertAmount($pdo, $converter, 'receive_voucher', 'amount', $tenant_id, 'voucher_date', [$date_from, $date_to], $base_currency_id, $target_currency_id, true);
    $last_month_recovery = convertAmount($pdo, $converter, 'receive_voucher', 'amount', $tenant_id, 'voucher_date', [$last_month_from, $last_month_to], $base_currency_id, $target_currency_id, true);
    $data['total_recovery_change'] = $last_month_recovery > 0 ? (($data['total_recovery'] - $last_month_recovery) / $last_month_recovery) * 100 : ($data['total_recovery'] > 0 ? 100 : 0);
    
    // Daily Payment
    $data['daily_payment'] = convertAmount($pdo, $converter, 'payment_voucher', 'amount', $tenant_id, 'voucher_date', date('Y-m-d'), $base_currency_id, $target_currency_id);
    $yesterday_payment = convertAmount($pdo, $converter, 'payment_voucher', 'amount', $tenant_id, 'voucher_date', $yesterday, $base_currency_id, $target_currency_id);
    $data['daily_payment_change'] = $yesterday_payment > 0 ? (($data['daily_payment'] - $yesterday_payment) / $yesterday_payment) * 100 : ($data['daily_payment'] > 0 ? 100 : 0);
    
    // Total Payment
    $data['total_payment'] = convertAmount($pdo, $converter, 'payment_voucher', 'amount', $tenant_id, 'voucher_date', [$date_from, $date_to], $base_currency_id, $target_currency_id, true);
    $last_month_payment = convertAmount($pdo, $converter, 'payment_voucher', 'amount', $tenant_id, 'voucher_date', [$last_month_from, $last_month_to], $base_currency_id, $target_currency_id, true);
    $data['total_payment_change'] = $last_month_payment > 0 ? (($data['total_payment'] - $last_month_payment) / $last_month_payment) * 100 : ($data['total_payment'] > 0 ? 100 : 0);
    
    // Daily Expenses (base currency - no currency_id in expense_voucher)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND DATE(date) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $daily_exp = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($base_currency_id != $target_currency_id) {
        $daily_exp = $converter->convert($daily_exp, $base_currency_id, $target_currency_id);
    }
    $data['daily_expenses'] = $daily_exp;
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND DATE(date) = ?");
    $stmt->execute([$tenant_id, $yesterday]);
    $yesterday_exp = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($base_currency_id != $target_currency_id) {
        $yesterday_exp = $converter->convert($yesterday_exp, $base_currency_id, $target_currency_id);
    }
    $yesterday_expenses = $yesterday_exp;
    $data['daily_expenses_change'] = $yesterday_expenses > 0 ? (($data['daily_expenses'] - $yesterday_expenses) / $yesterday_expenses) * 100 : ($data['daily_expenses'] > 0 ? 100 : 0);
    
    // Total Expenses (base currency)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $total_exp = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($base_currency_id != $target_currency_id) {
        $total_exp = $converter->convert($total_exp, $base_currency_id, $target_currency_id);
    }
    $data['total_expenses'] = $total_exp;
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM expense_voucher WHERE tenant_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $last_month_exp = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($base_currency_id != $target_currency_id) {
        $last_month_exp = $converter->convert($last_month_exp, $base_currency_id, $target_currency_id);
    }
    $last_month_expenses = $last_month_exp;
    $data['total_expenses_change'] = $last_month_expenses > 0 ? (($data['total_expenses'] - $last_month_expenses) / $last_month_expenses) * 100 : ($data['total_expenses'] > 0 ? 100 : 0);
    
    // Daily Cash - convert from receive_voucher currency
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND DATE(rv.voucher_date) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $cash_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $daily_cash_total = 0;
    foreach ($cash_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $daily_cash_total += $amount;
    }
    $data['daily_cash'] = $daily_cash_total;
    
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND DATE(rv.voucher_date) = ?
    ");
    $stmt->execute([$tenant_id, $yesterday]);
    $cash_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $yesterday_cash_total = 0;
    foreach ($cash_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $yesterday_cash_total += $amount;
    }
    $yesterday_cash = $yesterday_cash_total;
    $data['daily_cash_change'] = $yesterday_cash > 0 ? (($data['daily_cash'] - $yesterday_cash) / $yesterday_cash) * 100 : ($data['daily_cash'] > 0 ? 100 : 0);
    
    // Total Cash
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $cash_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_cash_amt = 0;
    foreach ($cash_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $total_cash_amt += $amount;
    }
    $data['total_cash'] = $total_cash_amt;
    
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cash' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $cash_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $last_month_cash_amt = 0;
    foreach ($cash_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $last_month_cash_amt += $amount;
    }
    $last_month_cash = $last_month_cash_amt;
    $data['total_cash_change'] = $last_month_cash > 0 ? (($data['total_cash'] - $last_month_cash) / $last_month_cash) * 100 : ($data['total_cash'] > 0 ? 100 : 0);
    
    // Daily Bank
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND DATE(rv.voucher_date) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $bank_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $daily_bank_total = 0;
    foreach ($bank_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $daily_bank_total += $amount;
    }
    $data['daily_bank'] = $daily_bank_total;
    
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND DATE(rv.voucher_date) = ?
    ");
    $stmt->execute([$tenant_id, $yesterday]);
    $bank_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $yesterday_bank_total = 0;
    foreach ($bank_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $yesterday_bank_total += $amount;
    }
    $yesterday_bank = $yesterday_bank_total;
    $data['daily_bank_change'] = $yesterday_bank > 0 ? (($data['daily_bank'] - $yesterday_bank) / $yesterday_bank) * 100 : ($data['daily_bank'] > 0 ? 100 : 0);
    
    // Total Bank
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $bank_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_bank_amt = 0;
    foreach ($bank_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $total_bank_amt += $amount;
    }
    $data['total_bank'] = $total_bank_amt;
    
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Bank Transfer' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $bank_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $last_month_bank_amt = 0;
    foreach ($bank_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $last_month_bank_amt += $amount;
    }
    $last_month_bank = $last_month_bank_amt;
    $data['total_bank_change'] = $last_month_bank > 0 ? (($data['total_bank'] - $last_month_bank) / $last_month_bank) * 100 : ($data['total_bank'] > 0 ? 100 : 0);
    
    // Daily Cheque
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND DATE(rv.voucher_date) = CURDATE()
    ");
    $stmt->execute([$tenant_id]);
    $cheque_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $daily_cheque_total = 0;
    foreach ($cheque_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $daily_cheque_total += $amount;
    }
    $data['daily_cheque'] = $daily_cheque_total;
    
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND DATE(rv.voucher_date) = ?
    ");
    $stmt->execute([$tenant_id, $yesterday]);
    $cheque_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $yesterday_cheque_total = 0;
    foreach ($cheque_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $yesterday_cheque_total += $amount;
    }
    $yesterday_cheque = $yesterday_cheque_total;
    $data['daily_cheque_change'] = $yesterday_cheque > 0 ? (($data['daily_cheque'] - $yesterday_cheque) / $yesterday_cheque) * 100 : ($data['daily_cheque'] > 0 ? 100 : 0);
    
    // Total Cheque
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $cheque_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_cheque_amt = 0;
    foreach ($cheque_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $total_cheque_amt += $amount;
    }
    $data['total_cheque'] = $total_cheque_amt;
    
    $stmt = $pdo->prepare("
        SELECT rv.amount, rv.currency_id
        FROM receive_voucher rv
        INNER JOIN accounts a ON rv.payment_method_id = a.id
        WHERE rv.tenant_id = ? AND a.name = 'Cheque' AND rv.voucher_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $last_month_from, $last_month_to]);
    $cheque_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $last_month_cheque_amt = 0;
    foreach ($cheque_rows as $row) {
        $amount = $row['amount'];
        $from_currency = $row['currency_id'] ?? $base_currency_id;
        if ($from_currency != $target_currency_id) {
            $amount = $converter->convert($amount, $from_currency, $target_currency_id);
        }
        $last_month_cheque_amt += $amount;
    }
    $last_month_cheque = $last_month_cheque_amt;
    $data['total_cheque_change'] = $last_month_cheque > 0 ? (($data['total_cheque'] - $last_month_cheque) / $last_month_cheque) * 100 : ($data['total_cheque'] > 0 ? 100 : 0);
    
    // Get currency symbol
    $stmt = $pdo->prepare("SELECT symbol FROM ledgerone_public.currencies WHERE id = ?");
    $stmt->execute([$target_currency_id]);
    $currency = $stmt->fetch();
    $currency_symbol = $currency['symbol'] ?? 'Rs';
    
    echo json_encode(['success' => true, 'data' => $data, 'currency_symbol' => $currency_symbol]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
