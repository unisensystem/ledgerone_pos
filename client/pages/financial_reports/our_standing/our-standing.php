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
    <title>Outstanding Report | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1F7BFF;
            --primary-hover: #1A6CDC;
            --surface-card: #FFFFFF;
            --surface-1: #F7F9FC;
            --border-default: #E1E6EE;
            --border-strong: #C9CFDA;
            --text-heading: #0E1A2B;
            --text-body: #2F3B4C;
            --subtext: #6B7280;
            --radius: 8px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--surface-1);
            color: var(--text-body);
            margin: 0;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            margin-bottom: 20px;
        }

        .page-title {
            font-family: inherit;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0;
        }

        .card {
            background: var(--surface-card);
            border: 1px solid var(--border-default);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(14, 26, 43, 0.04);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-heading);
            margin: 0 0 16px 0;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-body);
        }

        .form-control {
            padding: 9px 12px;
            border: 1px solid var(--border-default);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-body);
            background: var(--surface-card);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color 0.15s;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover { background: var(--primary-hover); }

        .btn-secondary {
            background: var(--surface-1);
            color: var(--text-body);
            border-color: var(--border-default);
        }

        .btn-secondary:hover { background: var(--border-default); }

        .btn-ghost {
            background: transparent;
            color: var(--text-body);
            border-color: var(--border-default);
        }

        .btn-ghost:hover { background: var(--surface-1); }

        .table-container {
            overflow-x: auto;
        }

        .report-head {
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--text-body);
        }

        .report-head div {
            margin-bottom: 2px;
        }

        #outstanding-table {
            width: 100%;
            border-collapse: collapse;
        }

        #outstanding-table th,
        #outstanding-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-default);
            font-size: 14px;
        }

        #outstanding-table thead th {
            color: var(--subtext);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .area-header-row td {
            background: var(--surface-1);
            font-weight: 600;
        }

        .area-total-row td {
            border-top: 1px dashed var(--border-strong);
        }

        .grand-total-row td {
            border-top: 2px solid var(--border-strong);
            font-size: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Outstanding Report</h1>
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
                    <label class="form-label" for="dsr-select">DSR (Sales Officer)</label>
                    <select class="form-control" id="dsr-select">
                        <option value="">Select DSR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="city-select">City</label>
                    <select class="form-control" id="city-select">
                        <option value="">All Cities</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area-select">Area</label>
                    <select class="form-control" id="area-select">
                        <option value="">All Areas</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="customer-name">Customer / Shop Name</label>
                    <input type="text" class="form-control" id="customer-name" placeholder="Search by name...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="from-date">From Date</label>
                    <input type="date" class="form-control" id="from-date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="to-date">To Date</label>
                    <input type="date" class="form-control" id="to-date">
                </div>
                <div class="form-group">
                    <label class="form-label" for="sort-select">Sort Balance</label>
                    <select class="form-control" id="sort-select">
                        <option value="high_to_low">High to Low</option>
                        <option value="low_to_high">Low to High</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="load-btn">
                    <i class="fas fa-sync-alt"></i> Load Outstanding
                </button>
                <button class="btn btn-secondary" id="reset-btn">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button class="btn btn-ghost" id="print-btn">
                    <i class="fas fa-print"></i> Thermal Print
                </button>
            </div>
        </div>

        <div class="card" id="results-card" style="display: none;">
            <div class="report-head" id="report-head"></div>
            <div class="table-container">
                <table id="outstanding-table">
                    <thead>
                        <tr>
                            <th>S.#</th>
                            <th>Shop Name</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody id="outstanding-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.currencySymbol = 'Rs.';
    </script>
    <script src="../../../assets/js/financial_reports/outstanding/outstanding.js"></script>
</body>
</html>