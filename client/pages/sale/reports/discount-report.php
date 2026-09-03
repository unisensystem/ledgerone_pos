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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Report - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/reports/discount-report.css">
</head>
<body class="light-theme">
    <div class="container">
        <!-- Header -->
        <div class="report-header">
            <div class="header-content">
                <h1><i class="fas fa-chart-bar"></i> Discount Report</h1>
                <p class="subtitle">Track discounts given on sales by product and customer</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" id="printBtn" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button class="btn btn-secondary" id="exportBtn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn btn-primary" id="refreshBtn" onclick="loadReport()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-card">
            <div class="filters-header">
                <h3><i class="fas fa-filter"></i> Filters</h3>
                <button class="btn btn-link" id="toggleFilters" onclick="toggleFilters()">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>

            <div class="filters-grid" id="filtersGrid">
                <div class="filter-group">
                    <label>Date Range - From</label>
                    <input type="date" id="startDate" class="filter-input">
                </div>

                <div class="filter-group">
                    <label>Date Range - To</label>
                    <input type="date" id="endDate" class="filter-input">
                </div>

                              <div class="filter-group">
                    <label>Salesman</label>
                    <select id="salesmanFilter" class="filter-input">
                        <option value="">All Salesmen</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Supplier</label>
                    <select id="supplierFilter" class="filter-input">
                        <option value="">All Suppliers</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Branch</label>
                    <select id="branchFilter" class="filter-input">
                        <option value="">All Branches</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Customer</label>
                    <select id="customerFilter" class="filter-input">
                        <option value="">All Customers</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Product</label>
                    <select id="productFilter" class="filter-input">
                        <option value="">All Products</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Min Discount %</label>
                    <input type="number" id="minDiscount" class="filter-input" min="0" max="100" step="0.01" placeholder="0">
                </div>

                <div class="filter-group">
                    <label>Max Discount %</label>
                    <input type="number" id="maxDiscount" class="filter-input" min="0" max="100" step="0.01" placeholder="100">
                </div>

                <div class="filter-actions">
                    <button class="btn btn-secondary" id="resetBtn" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button class="btn btn-primary" id="applyBtn" onclick="loadReport()">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards" id="summaryCards">
            <div class="summary-card">
                <div class="summary-label">Total Records</div>
                <div class="summary-value" id="totalRecords">0</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Gross Amount</div>
                <div class="summary-value" id="totalGross">₨ 0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Discount</div>
                <div class="summary-value" id="totalDiscount">₨ 0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Extra Discount</div>
                <div class="summary-value" id="totalExtraDiscount">₨ 0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Net Amount</div>
                <div class="summary-value" id="totalNet">₨ 0.00</div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="report-toolbar">
            <div class="report-toolbar-title">
                <i class="fas fa-table-columns"></i>
                <span>Report Details</span>
            </div>

            <div class="columns-control">
                <button
                    type="button"
                    class="btn btn-secondary columns-btn"
                    id="columnsBtn"
                    onclick="toggleColumnsPanel()"
                    aria-haspopup="true"
                    aria-expanded="false">
                    <i class="fas fa-columns"></i>
                    Columns
                </button>

                <div id="columnsPanel" class="columns-panel" style="display:none;"></div>
            </div>
        </div>

        <div class="report-table-container">
            <table class="report-table" id="reportTable">
                <thead>
                    <tr id="tableHeadRow"></tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="16" style="text-align: center; padding: 40px;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--primary);"></i>
                            <p>Loading report data...</p>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr id="tableFootRow" class="totals-row"></tr>
                </tfoot>
            </table>
        </div>

        <!-- No Data Message -->
        <div id="noDataMessage" style="display: none; text-align: center; padding: 60px 20px;">
            <i class="fas fa-inbox" style="font-size: 48px; color: var(--subtext); margin-bottom: 16px; display: block;"></i>
            <p style="font-size: 16px; color: var(--subtext); margin: 0;">No discount records found</p>
        </div>
    </div>

    <!-- Print Modal -->
    <div class="modal" id="printModal">
        <div class="modal-content" style="max-width: 600px;">
            <h3 class="modal-title">Print Options</h3>
            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="radio" name="printFormat" value="current" checked style="width: auto;">
                    <span>Print Current View</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="printFormat" value="detailed" style="width: auto;">
                    <span>Print Detailed Report</span>
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="document.getElementById('printModal').style.display='none'">Cancel</button>
                <button class="btn btn-primary" onclick="confirmPrint()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    <style>
        /* Column show/hide control */
        .report-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 0 0 12px;
            position: relative;
            z-index: 20;
        }

        .report-toolbar-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text, #333);
        }

        .columns-control {
            position: relative;
        }

        .columns-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .columns-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 260px;
            max-height: 430px;
            overflow-y: auto;
            padding: 10px;
            background: var(--card, #fff);
            border: 1px solid var(--border, #ddd);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,.14);
            z-index: 1000;
        }

        .columns-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 4px 4px 10px;
            margin-bottom: 6px;
            border-bottom: 1px solid var(--border, #eee);
            font-weight: 700;
        }

        .columns-panel-reset {
            border: 0;
            background: transparent;
            color: var(--primary, #2563eb);
            font-weight: 600;
            cursor: pointer;
            padding: 4px 6px;
        }

        .columns-panel-list {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .columns-panel-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 6px;
            border-radius: 6px;
            cursor: pointer;
            user-select: none;
        }

        .columns-panel-item:hover {
            background: rgba(127,127,127,.08);
        }

        .columns-panel-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .report-toolbar {
                align-items: flex-start;
            }

            .columns-panel {
                right: 0;
                width: min(260px, calc(100vw - 40px));
            }
        }
    </style>

    <script src="../../../assets/js/sale/reports/discount-report.js?v=<?php echo time(); ?>"></script>
</body>
</html>