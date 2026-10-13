<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/ledgerone_erp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_report/report.css">
</head>
<body>
<div class="main-content">
<div class="report-wrap">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>Production Report</h1>
            <p class="page-sub">Comprehensive overview of the full production workflow</p>
        </div>
        <button class="btn btn-secondary btn-print" onclick="window.print()">
            <i class="las la-print"></i> Print
        </button>
    </div>

    <!-- ── Filter Bar ─────────────────────────────────────────────────── -->
    <div class="filter-card">
        <div class="filter-row">
            <div class="filter-group">
                <label class="filter-label">Date Range</label>
                <div class="date-range">
                    <input type="date" class="form-control" id="filterDateFrom">
                    <span class="date-sep">to</span>
                    <input type="date" class="form-control" id="filterDateTo">
                </div>
            </div>
            <div class="filter-group">
                <label class="filter-label">Quick Select</label>
                <div class="preset-pills">
                    <button class="preset-pill" data-preset="today">Today</button>
                    <button class="preset-pill" data-preset="week">This Week</button>
                    <button class="preset-pill active" data-preset="month">This Month</button>
                    <button class="preset-pill" data-preset="last_month">Last Month</button>
                    <button class="preset-pill" data-preset="quarter">This Quarter</button>
                </div>
            </div>
        </div>
        <div class="filter-row">
            <div class="filter-group">
                <label class="filter-label">Branch</label>
                <select class="form-control" id="filterBranch">
                    <option value="">All Branches</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Product</label>
                <input type="text" class="form-control" id="filterProduct" list="productFilterList" placeholder="All products">
                <datalist id="productFilterList"></datalist>
                <input type="hidden" id="filterProductId">
            </div>
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <div class="status-pills">
                    <button class="status-pill active" data-status="all">All</button>
                    <button class="status-pill" data-status="Planned">Planned</button>
                    <button class="status-pill" data-status="In Progress">In Progress</button>
                    <button class="status-pill" data-status="Completed">Completed</button>
                    <button class="status-pill" data-status="Cancelled">Cancelled</button>
                </div>
            </div>
            <div class="filter-group filter-actions">
                <button class="btn btn-primary" id="applyFiltersBtn"><i class="las la-filter"></i> Apply</button>
                <button class="btn btn-secondary" id="resetFiltersBtn"><i class="las la-redo-alt"></i> Reset</button>
            </div>
        </div>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────── -->
    <div class="tab-nav">
        <button class="tab-btn active" data-tab="overview"><i class="las la-tachometer-alt"></i> Overview</button>
        <button class="tab-btn" data-tab="materials"><i class="las la-cubes"></i> Material Usage</button>
        <button class="tab-btn" data-tab="goods"><i class="las la-boxes"></i> Finished Goods</button>
        <button class="tab-btn" data-tab="costs"><i class="las la-coins"></i> Cost Breakdown</button>
    </div>

    <!-- ── Tab: Overview ─────────────────────────────────────────────── -->
    <div id="tab-overview" class="tab-panel active">
        <!-- KPI Cards -->
        <div class="kpi-grid" id="kpiGrid">
            <div class="kpi-card skeleton"></div>
            <div class="kpi-card skeleton"></div>
            <div class="kpi-card skeleton"></div>
            <div class="kpi-card skeleton"></div>
            <div class="kpi-card skeleton"></div>
            <div class="kpi-card skeleton"></div>
        </div>

        <!-- Charts -->
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>Orders by Status</h3>
                </div>
                <div class="chart-wrap"><canvas id="chartStatus"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>Production Trend</h3>
                    <div class="period-toggle">
                        <button class="period-btn" data-period="day">Day</button>
                        <button class="period-btn active" data-period="week">Week</button>
                        <button class="period-btn" data-period="month">Month</button>
                    </div>
                </div>
                <div class="chart-wrap"><canvas id="chartTrend"></canvas></div>
            </div>
        </div>

        <!-- PO Table -->
        <div class="report-card">
            <div class="report-card-header">
                <h3>Production Orders</h3>
                <span class="row-count" id="overviewCount">—</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Ordered</th>
                            <th>Produced</th>
                            <th>Completion</th>
                            <th>Wastage</th>
                            <th>Total Cost</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="overviewTableBody">
                        <tr><td colspan="10" class="loading-cell">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Tab: Material Usage ────────────────────────────────────────── -->
    <div id="tab-materials" class="tab-panel">
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-card-header"><h3>Top 10 Materials by Consumption</h3></div>
                <div class="chart-wrap chart-wrap-tall"><canvas id="chartMatTop"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header"><h3>Required vs Wasted (Top 8)</h3></div>
                <div class="chart-wrap chart-wrap-tall"><canvas id="chartMatWaste"></canvas></div>
            </div>
        </div>
        <div class="report-card">
            <div class="report-card-header">
                <h3>Material Consumption Detail</h3>
                <span class="row-count" id="materialsCount">—</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>UOM</th>
                            <th>Required</th>
                            <th>Issued (WIP)</th>
                            <th>Wasted</th>
                            <th>Net Consumed</th>
                            <th>Wastage %</th>
                            <th># Orders</th>
                        </tr>
                    </thead>
                    <tbody id="materialsTableBody">
                        <tr><td colspan="8" class="loading-cell">Select filters and apply to load data</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Tab: Finished Goods ────────────────────────────────────────── -->
    <div id="tab-goods" class="tab-panel">
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-card-header"><h3>Ordered vs Produced by Product</h3></div>
                <div class="chart-wrap"><canvas id="chartGoodsBar"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header"><h3>Overall Output Efficiency</h3></div>
                <div class="chart-wrap chart-wrap-doughnut"><canvas id="chartGoodsDoughnut"></canvas></div>
            </div>
        </div>
        <div class="report-card">
            <div class="report-card-header">
                <h3>Finished Goods Summary</h3>
                <span class="row-count" id="goodsCount">—</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th># Orders</th>
                            <th>Total Ordered</th>
                            <th>Total Produced</th>
                            <th>FG Wastage</th>
                            <th>Net Output</th>
                            <th>Completion %</th>
                        </tr>
                    </thead>
                    <tbody id="goodsTableBody">
                        <tr><td colspan="7" class="loading-cell">Select filters and apply to load data</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Tab: Cost Breakdown ────────────────────────────────────────── -->
    <div id="tab-costs" class="tab-panel">
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-card-header"><h3>Material vs Overhead Cost (Top 10 POs)</h3></div>
                <div class="chart-wrap"><canvas id="chartCostBar"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-card-header"><h3>Cost Composition</h3></div>
                <div class="chart-wrap chart-wrap-doughnut"><canvas id="chartCostDoughnut"></canvas></div>
            </div>
        </div>
        <div class="cost-summary-strip" id="costSummaryStrip" style="display:none;">
            <div class="cost-summary-item">
                <div class="cs-label">Total Material Cost</div>
                <div class="cs-value" id="csMaterial">—</div>
            </div>
            <div class="cost-summary-divider"></div>
            <div class="cost-summary-item">
                <div class="cs-label">Total Overhead</div>
                <div class="cs-value" id="csOverhead">—</div>
            </div>
            <div class="cost-summary-divider"></div>
            <div class="cost-summary-item">
                <div class="cs-label">Total Production Cost</div>
                <div class="cs-value cs-total" id="csTotal">—</div>
            </div>
            <div class="cost-summary-divider"></div>
            <div class="cost-summary-item">
                <div class="cs-label">Blended Cost / Unit</div>
                <div class="cs-value" id="csPerUnit">—</div>
            </div>
        </div>
        <div class="report-card">
            <div class="report-card-header">
                <h3>Cost Breakdown by Order</h3>
                <span class="row-count" id="costsCount">—</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Material Cost</th>
                            <th>Overhead</th>
                            <th>Total Cost</th>
                            <th>Units Produced</th>
                            <th>Cost / Unit</th>
                        </tr>
                    </thead>
                    <tbody id="costsTableBody">
                        <tr><td colspan="8" class="loading-cell">Select filters and apply to load data</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /report-wrap -->
</div><!-- /main-content -->

<!-- ── Drill-down Panel ───────────────────────────────────────────────── -->
<div id="drillBackdrop" class="drill-backdrop"></div>
<div id="drillPanel" class="drill-panel">
    <div class="drill-header">
        <div>
            <div class="drill-title" id="drillTitle">Loading...</div>
            <div id="drillStatusBadge"></div>
        </div>
        <button class="drill-close" id="drillClose"><i class="las la-times"></i></button>
    </div>
    <div class="drill-body" id="drillBody">
        <div class="loading-cell" style="padding:40px; text-align:center;">Loading details...</div>
    </div>
</div>

<script>const BASE_URL = '<?php echo $base_url; ?>';</script>
<script src="../../../assets/js/manufacturing/production_report/report.js"></script>
</body>
</html>
