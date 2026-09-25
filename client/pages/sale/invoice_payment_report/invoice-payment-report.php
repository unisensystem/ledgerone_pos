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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Invoice Payment Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Reuses the same table/card/form classes as the Stock Position module -->
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_position/stock-position.css">
    <style>
        .balance-positive { color: #2fbf71; font-weight: 600; }
        .balance-due { color: #dc3545; font-weight: 600; }
        .method-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .method-cash { background: #e7f6ec; color: #1a7a35; }
        .method-bank { background: #e7f0fe; color: #1a4fd6; }
        .method-none { background: #f1f1f1; color: #888; }

        .source-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .source-invoice { background: #fdeecb; color: #9a6300; }
        .source-voucher { background: #e2f5ff; color: #0b6e99; }
        .source-none { background: #f1f1f1; color: #888; }

        .fill-badge { font-size: 11px; color: var(--text-subtext); }
        .fill-auto { color: #b3261e; font-weight: 600; }

        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Invoice Payment Report</h2>
                <div class="action-buttons no-print">
                    <button class="btn btn-secondary" id="exportCSVBtn" type="button">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <button class="btn btn-primary" id="printBtn" type="button">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="form-row no-print">
                <div class="form-group">
                    <label class="form-label" for="from-date">From Date</label>
                    <input type="date" class="form-control" id="from-date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="to-date">To Date</label>
                    <input type="date" class="form-control" id="to-date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="customer">Customer</label>
                    <input type="text" class="form-control" id="customer" list="customer-list" placeholder="All Customers">
                    <datalist id="customer-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="company">Company</label>
                    <select class="form-control" id="company">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="branch">Branch</label>
                    <select class="form-control" id="branch">
                        <option value="">All Branches</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="payment-method">Payment Method</label>
                    <select class="form-control" id="payment-method">
                        <option value="">All Methods</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="payment-source">Payment Source</label>
                    <select class="form-control" id="payment-source">
                        <option value="">All Sources</option>
                        <option value="invoice">From Invoice (at creation)</option>
                        <option value="receive_voucher">From Receive Voucher (separate entry)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="only-paid">&nbsp;</label>
                    <label style="display:flex; align-items:center; gap:6px; height:38px; font-size:13px; font-weight:500;">
                        <input type="checkbox" id="only-paid"> Only invoices with a payment
                    </label>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                    <button class="btn btn-primary" id="applyFilters">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button class="btn btn-secondary" id="clearFilters">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>

            <div class="no-print" id="summaryBar" style="display:flex; gap:24px; flex-wrap:wrap; padding:10px 0; font-size:13px; color:var(--text-subtext);"></div>

            <div class="no-print" style="font-size:12px; color:var(--text-subtext); padding-bottom:8px;">
                <i class="fas fa-circle-info"></i>
                "Source" is a best-effort guess, not a stored fact: a payment recorded within 5 minutes of the invoice being saved is labelled <strong>Invoice</strong> (created together with it, e.g. via the Amount Paid box / Auto-fill); anything entered later is labelled <strong>Receive Voucher</strong> (a separate entry made afterwards). "Fill" shows whether Amount Paid was left on Auto or typed in Manually on the invoice form itself.
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sr#</th>
                            <th>Bill No</th>
                            <th>Date</th>
                            <th>Party (Customer)</th>
                            <th>Net Amount</th>
                            <th>Paid Amount</th>
                            <th>Balance</th>
                            <th>Source</th>
                            <th>Fill</th>
                            <th>Method</th>
                            <th>Bank</th>
                            <th>Voucher No</th>
                            <th>Paid On</th>
                        </tr>
                    </thead>
                    <tbody id="reportBody"></tbody>
                </table>
                <div class="pagination-container no-print">
                    <div class="pagination-info">
                        <span id="pagination-info">Showing 0 - 0 of 0 entries</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="btn btn-secondary" id="prev-btn" disabled>Previous</button>
                        <span id="page-info">Page 1 of 1</span>
                        <button class="btn btn-secondary" id="next-btn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../../../assets/js/sale/invoice_payment_report/invoice-payment-report.js?v=<?= time() ?>"></script>
</body>
</html>
