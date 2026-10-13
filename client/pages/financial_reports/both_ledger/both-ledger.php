<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol
    FROM tenant_currencies tc
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Both (Customer + Supplier) Ledger | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/customer_ledger/customer-ledger.css">
    <style>
        .txn-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .txn-type-opening-customer, .txn-type-opening-supplier { background: rgba(31,123,255,0.12); color: #1f7bff; }
        .txn-type-sale { background: rgba(47,191,113,0.12); color: #1a8f4c; }
        .txn-type-sale-return { background: rgba(227,79,79,0.12); color: #c23636; }
        .txn-type-receive { background: rgba(47,191,113,0.12); color: #1a8f4c; }
        .txn-type-purchase { background: rgba(232,178,63,0.15); color: #b8860b; }
        .txn-type-purchase-return { background: rgba(227,79,79,0.12); color: #c23636; }
        .txn-type-payment { background: rgba(227,79,79,0.12); color: #c23636; }
        .txn-type-adjustment { background: rgba(150,150,150,0.15); color: #666; }
        .txn-type-other { background: rgba(150,150,150,0.1); color: #888; }
        .side-tag { font-size: 11px; color: var(--subtext, #888); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Both (Customer + Supplier) Ledger</h1>
            <div class="ledger-type">
                <div class="ledger-type-btn active" id="summary-ledger-btn">Summary Ledger</div>
                <div class="ledger-type-btn" id="detailed-ledger-btn">Detailed Ledger</div>
            </div>
        </div>

        <div class="card" style="background: #f0f9ff; border-left: 4px solid #1f7bff; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 14px; color: #1f7bff;"><strong>Tip:</strong> This report shows a party's Customer ledger AND Supplier ledger together. It only lists parties registered as "Both" (Customer + Supplier). Individual Customer Ledger / Supplier Ledger reports are unaffected by this page.</p>
        </div>

        <div class="card">
            <h2 class="card-title">Filters</h2>
            <div class="filters">
                <div class="form-group">
                    <label class="form-label" for="company">Company</label>
                    <select class="form-control" id="company">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency-filter">Currency</label>
                    <select class="form-control" id="currency-filter">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="party-code">Party</label>
                    <input type="text" class="form-control" id="party-search" placeholder="Search Both parties..." autocomplete="off">
                    <select class="form-control" id="party-code" style="display: none;">
                        <option value="">Select Party</option>
                    </select>
                    <div id="party-dropdown" class="customer-dropdown"></div>
                </div>
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
            <p style="margin: 0; font-size: 14px; color: #1f7bff;"><strong>Help:</strong> "Type" shows what each entry is - Opening (Customer/Supplier), Sale, Sale Return, Receive, Purchase, Purchase Return, Payment, Adjustment. Customer Bal = Dr means the customer owes you; Supplier Bal = Cr means you owe the supplier.</p>
        </div>

        <div class="card">
            <h2 class="card-title" id="ledger-title">Summary Ledger</h2>
            <div class="table-container">
                <table id="summary-ledger-table">
                    <thead>
                        <tr>
                            <th>Party Name</th>
                            <th>Cust. Opening</th>
                            <th>Cust. Debit</th>
                            <th>Cust. Credit</th>
                            <th>Cust. Closing</th>
                            <th>Supp. Opening</th>
                            <th>Supp. Debit</th>
                            <th>Supp. Credit</th>
                            <th>Supp. Closing</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <table id="detailed-ledger-table" style="display: none;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Customer Bal</th>
                            <th>Supplier Bal</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.currencySymbol = <?php echo json_encode($currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="../../../assets/js/financial_reports/both_ledger/both-ledger.js"></script>
</body>
</html>
