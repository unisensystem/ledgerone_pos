<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
require_once '../../../../includes/encryption.php';

// Get encrypted customer code from URL
$encrypted_id = $_GET['code'] ?? null;
if (!$encrypted_id) {
    die('Invalid access');
}

// Decrypt customer ID - pass pdo and tenant_id
$customer_id = decryptCustomerCode($encrypted_id, $pdo, $_SESSION['tenant_id']);
if (!$customer_id) {
    die('Invalid customer code - decryption failed');
}

// Get customer details
$stmt = $pdo->prepare("SELECT id, customer_code, customer_name FROM customers WHERE tenant_id = ? AND id = ? AND status = 'ACTIVE'");
$stmt->execute([$_SESSION['tenant_id'], $customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    die('Customer not found');
}

// Get base currency symbol
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customer Ledger - <?php echo htmlspecialchars($customer['customer_name']); ?> | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/customer_ledger/customer-ledger.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Customer Ledger Report</h1>
        </div>

        <div class="card" style="background: #f0f9ff; border-left: 4px solid #1f7bff; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 14px;"><strong>Customer:</strong> <?php echo htmlspecialchars($customer['customer_code'] . ' - ' . $customer['customer_name']); ?></p>
        </div>

        <div class="card">
            <h2 class="card-title">Filters</h2>
            <div class="filters">
                <div class="form-group">
                    <label class="form-label" for="from-date">From Date</label>
                    <input type="date" class="form-control" id="from-date">
                    <div class="error-message" id="from-date-error">From Date cannot be greater than To Date</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="to-date">To Date</label>
                    <input type="date" class="form-control" id="to-date">
                    <div class="error-message" id="to-date-error">To Date cannot be less than From Date</div>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="load-ledger-btn">
                    <i class="fas fa-sync-alt"></i> Load Ledger
                </button>
                <button class="btn btn-secondary" id="reset-filters-btn">
                    <i class="fas fa-undo"></i> Reset Filters
                </button>
            </div>
        </div>

        <div class="card" style="background: #f0f9ff; border-left: 4px solid #1f7bff; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 14px; color: #1f7bff;"><strong>Help:</strong> Dr (Debit) = Amount you owe | Cr (Credit) = Amount paid</p>
        </div>

        <div class="card">
            <h2 class="card-title">Detailed Ledger</h2>
            <div class="table-container">
                <table id="detailed-ledger-table">
                    <thead>
                        <tr>
                            <th>Transaction Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Closing Balance (Dr/Cr)</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.currencySymbol = '<?php echo $currency_symbol; ?>';
        window.customerId = '<?php echo $customer['id']; ?>';
        window.customerCode = '<?php echo $customer['customer_code']; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/customer_ledger/view.js"></script>
</body>
</html>
