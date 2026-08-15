<?php
// Path convention: pages/sales/daily_product_sale_report/index.php
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
    <title>Sale Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Reuses the same card/filter/button classes as the Stock Position module -->
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_position/stock-position.css">
    <style>
        /* Sale-register layout: one block per sale, header strip + nested item table */
        .sale-block {
            border: 1px solid var(--border-default);
            border-radius: 8px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .sale-block-header {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px 16px;
            padding: 12px 16px;
            background: var(--table-header);
            border-bottom: 1px solid var(--border-default);
        }
        .sale-block-header .field label {
            display: block;
            font-size: 11px;
            color: var(--subtext);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .sale-block-header .field span {
            font-weight: 600;
            color: var(--heading);
            font-size: 13px;
        }
        .status-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-Paid { background: rgba(47, 191, 113, 0.15); color: var(--success); }
        .status-Partial { background: rgba(232, 178, 63, 0.18); color: var(--warning); }
        .status-Unpaid { background: rgba(227, 79, 79, 0.15); color: var(--error); }
        .sale-items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sale-items-table th, .sale-items-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-default);
            font-size: 13px;
            text-align: left;
        }
        .sale-items-table th {
            background: var(--surface-1);
            color: var(--subtext);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
        }
        .sale-items-table td.num, .sale-items-table th.num { text-align: right; }
        .sale-block-footer {
            display: flex;
            justify-content: flex-end;
            gap: 32px;
            padding: 10px 16px;
            background: var(--surface-1);
        }
        .sale-block-footer .totals-line { font-size: 13px; color: var(--body); }
        .sale-block-footer .totals-line strong { color: var(--heading); }
        .grand-total-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: var(--table-header);
            border: 1px solid var(--border-default);
            border-radius: 8px;
            margin-top: 8px;
            font-weight: 700;
            color: var(--heading);
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Sale Report</h2>
                <div class="action-buttons">
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-file-export"></i> Export
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="printReport()"><i class="fas fa-print"></i> Print</a>
                            <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer">Customer</label>
                    <input type="text" class="form-control" id="customer" list="customer-list" placeholder="All Customers">
                    <datalist id="customer-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="branch">Branch</label>
                    <input type="text" class="form-control" id="branch" list="branch-list" placeholder="All Branches">
                    <datalist id="branch-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="product">Product</label>
                    <input type="text" class="form-control" id="product" list="product-list" placeholder="All Products">
                    <datalist id="product-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="from-date">From Date</label>
                    <input type="date" class="form-control" id="from-date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="to-date">To Date</label>
                    <input type="date" class="form-control" id="to-date">
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

            <div id="reportContainer">
                <!-- Sale blocks rendered here by JS -->
            </div>

            <div class="grand-total-bar">
                <span id="grand-total-info">Loading...</span>
            </div>

            <div class="pagination-container">
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
    </main>

<script src="../../../assets/js/sale/daily_product_sale_report/daily-product-sale-report.js?v=<?= time() ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>
