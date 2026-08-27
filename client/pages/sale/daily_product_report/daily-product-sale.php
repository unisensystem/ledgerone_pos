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
    <title>Daily Product Sale Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Reuses the same table/card/form classes as the Stock Position module -->
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_position/stock-position.css">
    <style>
        /*
        |--------------------------------------------------------------------------
        | COLUMN VISIBILITY (toggled by JS via body classes)
        | Example: body.hide-col-foc .col-foc { display: none; }
        |--------------------------------------------------------------------------
        */
       body.hide-col-sno       .col-sno,
body.hide-col-code      .col-code,
body.hide-col-detail    .col-detail,
body.hide-col-batch     .col-batch,
body.hide-col-expiry    .col-expiry,
body.hide-col-price     .col-price,
body.hide-col-qty       .col-qty,
body.hide-col-disc      .col-disc,
body.hide-col-extradisc .col-extradisc,
body.hide-col-foc       .col-foc,
body.hide-col-amount    .col-amount {
    display: none !important;
}

        /*
        |--------------------------------------------------------------------------
        | SEARCHABLE DROPDOWN (Salesman / Supplier Man)
        |--------------------------------------------------------------------------
        */
        .search-dropdown-group {
            position: relative;
        }

        .search-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 50;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 220px;
            overflow-y: auto;
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        }

        .search-dropdown.open {
            display: block;
        }

        .search-dropdown .dropdown-option {
            padding: 8px 12px;
            font-size: 13px;
            color: #222;
            cursor: pointer;
        }

        .search-dropdown .dropdown-option:hover,
        .search-dropdown .dropdown-option.active {
            background: #1a6cdc;
            color: #fff;
        }

        .search-dropdown .dropdown-empty {
            padding: 8px 12px;
            font-size: 13px;
            color: #888;
        }

        /*
        |--------------------------------------------------------------------------
        | COLUMNS MODAL
        |--------------------------------------------------------------------------
        */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            font-family: Arial, sans-serif;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            width: 320px;
            max-width: 90vw;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #6f42c1;
            color: #fff;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 15px;
        }

        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }

        .modal-body {
            padding: 14px 16px;
            max-height: 320px;
            overflow-y: auto;
        }

        .modal-body label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            font-size: 13px;
            color: #222;
            cursor: pointer;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 16px;
            border-top: 1px solid #eee;
        }

        .modal-footer button {
            font-size: 13px;
            padding: 7px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .modal-btn-reset {
            background: #e9ecef;
            color: #333;
        }

        .modal-btn-apply {
            background: #6f42c1;
            color: #fff;
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Daily Product Sale Report</h2>
                <div class="action-buttons">
                    <button class="btn btn-secondary" id="columns-btn" type="button">
                        <i class="fas fa-columns"></i> Columns
                    </button>
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
                <div class="form-group search-dropdown-group">
                    <label class="form-label" for="salesman-search">Salesman</label>
                    <input type="text" class="form-control" id="salesman-search" placeholder="All Salesmen" autocomplete="off">
                    <input type="hidden" id="salesman-id" value="">
                    <div class="search-dropdown" id="salesman-dropdown"></div>
                </div>
                <div class="form-group search-dropdown-group">
                    <label class="form-label" for="supplier-man-search">Supplier Man</label>
                    <input type="text" class="form-control" id="supplier-man-search" placeholder="All Supplier Men" autocomplete="off">
                    <input type="hidden" id="supplier-man-id" value="">
                    <div class="search-dropdown" id="supplier-man-dropdown"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="city">City</label>
                    <input type="text" class="form-control" id="city" list="city-list" placeholder="All Cities">
                    <datalist id="city-list"></datalist>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area">Area</label>
                    <input type="text" class="form-control" id="area" list="area-list" placeholder="All Areas">
                    <datalist id="area-list"></datalist>
                </div>
                <div class="form-group">
    <label class="form-label" for="batch-no">Batch No</label>
    <input
        type="text"
        class="form-control"
        id="batch-no"
        placeholder="Search Batch No">
</div>

<div class="form-group">
    <label class="form-label" for="expiry-date">Expiry Date</label>
    <input
        type="date"
        class="form-control"
        id="expiry-date">
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

            <div class="table-container">
                <table class="table">
                    <thead>
                       <tr>
    <th class="col-sno">Sr#</th>
    <th class="col-code">Product Code</th>
    <th class="col-detail">Details</th>
    <th class="col-batch">Batch No</th>
    <th class="col-expiry">Expiry Date</th>
    <th class="col-price">Price</th>
    <th class="col-qty">Qty</th>
    <th class="col-disc">Disc %</th>
    <th class="col-extradisc">Extra Disc %</th>
    <th class="col-foc">FOC</th>
    <th class="col-amount">Amount</th>
</tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
        </div>
    </main>

    <!-- =========================================================
         COLUMNS MODAL
    ========================================================= -->

    <div class="modal-overlay" id="columns-modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Show / Hide Columns</h3>
                <button class="modal-close" id="columns-modal-close" type="button">&times;</button>
            </div>
            <div class="modal-body" id="columns-modal-body">
                <!-- checkboxes injected by JS -->
            </div>
            <div class="modal-footer">
                <button class="modal-btn-reset" id="columns-reset-btn" type="button">Reset</button>
                <button class="modal-btn-apply" id="columns-apply-btn" type="button">Apply</button>
            </div>
        </div>
    </div>

<script src="../../../assets/js/sale/daily_product_sale_report/daily-product-sale-report.js?v=<?= time() ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>
