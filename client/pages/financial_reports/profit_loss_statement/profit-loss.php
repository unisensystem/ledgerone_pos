<?php
require_once '../../../../includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}

// Get base currency symbol
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP | Profit & Loss Statement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/profit_loss_statement/profit-loss.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1><i class="fas fa-chart-line"></i> Profit & Loss Statement</h1>
                <p>Analyze revenue, costs, and expenses for a selected period</p>
            </div>
            <div class="button-group">
                <button class="btn btn-ghost no-print" id="helpBtn">
                    <i class="fas fa-question-circle"></i> Help
                </button>
                <button class="btn btn-secondary no-print" id="resetBtn">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button class="btn btn-secondary no-print" id="zakatBtn">
                    <i class="fas fa-calculator"></i> Zakat
                </button>
                <button class="btn btn-secondary no-print" onclick="window.location.href='../profit_loss_detail/profit-loss-detail.php'">
                    <i class="fas fa-chart-pie"></i> Detailed P&L
                </button>
                <button class="btn btn-primary" id="generateBtn">
                    <i class="fas fa-file-alt"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Validation Messages -->
        <div id="validationMessage" class="validation-message">
            <i class="fas fa-check-circle"></i>
            <span id="validationText"></span>
        </div>

        <!-- Help Section -->
        <div class="help-section no-print" id="helpSection">
            <div class="help-title">
                <i class="fas fa-info-circle"></i> How to use the P&L Statement Report
            </div>
            <div class="help-content">
                <p>1. Select a date range for the report period (default is current month).</p>
                <p>2. Choose reporting currency and whether to include comparative data.</p>
                <p>3. Click "Generate Report" to calculate and display the Profit & Loss statement.</p>
                <p>4. Review the summary cards for key financial metrics.</p>
                <p>5. Use the "Export" button to download the report in PDF or CSV format.</p>
                <p><strong>Tip:</strong> Hover over any <span class="help-tip">?</span> icon for additional information about that field.</p>
            </div>
        </div>

        <!-- Report Parameters Card -->
        <div class="card no-print">
            <div class="card-title">Report Parameters</div>
            
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate">Start Date <span class="help-tip" title="The beginning date for the reporting period">?</span></label>
                        <div class="input-wrapper">
                            <input type="date" id="startDate">
                        </div>
                        <div class="helper-text">Start of the reporting period</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="endDate">End Date <span class="help-tip" title="The ending date for the reporting period">?</span></label>
                        <div class="input-wrapper">
                            <input type="date" id="endDate">
                        </div>
                        <div class="helper-text">End of the reporting period</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="companyFilter">Company <span class="help-tip" title="Filter by company">?</span></label>
                        <select id="companyFilter">
                            <option value="">All Companies</option>
                        </select>
                        <div class="helper-text">Filter report by company</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="currencyFilter">Currency <span class="help-tip" title="Select reporting currency">?</span></label>
                        <select id="currencyFilter">
                            <option value="">Loading...</option>
                        </select>
                        <div class="helper-text">Select reporting currency</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="projectFilter">Project <span class="help-tip" title="Filter by project">?</span></label>
                        <select id="projectFilter">
                            <option value="">All Projects</option>
                        </select>
                        <div class="helper-text">Filter report by project</div>
                    </div>
                </div>
                
                <div class="form-row">
                        <label for="branchFilter">Branch <span class="help-tip" title="Filter by branch">?</span></label>
                        <select id="branchFilter">
                            <option value="all">All Branches</option>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT id, branch_name FROM branches WHERE tenant_id = ? AND parent_branch_id IS NULL ORDER BY branch_name");
                                $stmt->execute([$_SESSION['tenant_id']]);
                                $branches = $stmt->fetchAll();
                                foreach ($branches as $branch) {
                                    echo '<option value="' . $branch['id'] . '">' . htmlspecialchars($branch['branch_name']) . '</option>';
                                }
                            } catch (Exception $e) {
                                echo '<option value="">Error loading branches</option>';
                            }
                            ?>
                        </select>
                        <div class="helper-text">Filter report by branch</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="comparativePeriod">Comparative Period <span class="help-tip" title="Include data from previous period for comparison">?</span></label>
                        <select id="comparativePeriod">
                            <option value="none">No Comparison</option>
                            <option value="previousMonth">Previous Month</option>
                            <option value="previousQuarter">Previous Quarter</option>
                            <option value="previousYear">Previous Year</option>
                        </select>
                        <div class="helper-text">Optional: Compare with a previous period</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="detailLevel">Detail Level <span class="help-tip" title="Level of detail in the report">?</span></label>
                        <select id="detailLevel">
                            <option value="summary">Summary</option>
                            <option value="detailed" selected>Detailed</option>
                            <option value="category">Category Only</option>
                        </select>
                        <div class="helper-text">Controls how much detail is shown</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="includeGraphs">Include Visualizations <span class="help-tip" title="Show charts and graphs in the report">?</span></label>
                        <select id="includeGraphs">
                            <option value="yes" selected>Yes</option>
                            <option value="no">No</option>
                        </select>
                        <div class="helper-text">Show/hide charts in the report</div>
                    </div>
                </div>
            </div>
            
            <div class="button-group">
                <button class="btn btn-ghost" id="exportPDFBtn">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn btn-ghost" id="exportCSVBtn">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-primary" id="generateReportBtn">
                    <i class="fas fa-calculator"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards" id="summaryCards" style="display: none;">
            <div class="summary-card">
                <div class="summary-card-title">Total Revenue</div>
                <div class="summary-card-value" id="totalRevenue"><?php echo $currency_symbol; ?>0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total COGS</div>
                <div class="summary-card-value" id="totalCOGS"><?php echo $currency_symbol; ?>0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Profit Margin %</div>
                <div class="summary-card-value" id="profitMargin">0.00%</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Gross Profit</div>
                <div class="summary-card-value" id="grossProfit"><?php echo $currency_symbol; ?>0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Total Expenses</div>
                <div class="summary-card-value" id="totalExpenses"><?php echo $currency_symbol; ?>0.00</div>
            </div>
            <div class="summary-card summary-card-profit" id="netProfitCard">
                <div class="summary-card-title">Net Profit/Loss</div>
                <div class="summary-card-value" id="netProfit"><?php echo $currency_symbol; ?>0.00</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="card" id="chartsSection" style="display: none;">
            <div class="card-title">Visual Analysis</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 20px;">
                <div>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div>
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Report Results Card -->
        <div class="card" id="reportResults" style="display: none;">
            <div class="card-title">
                Profit & Loss Statement
                <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriod">Period: Oct 1, 2023 - Oct 31, 2023</span>
            </div>
            
            <div class="table-container">
                <table id="plTable">
                    <thead>
                        <tr>
                            <th width="50%">Account</th>
                            <th width="25%">Amount</th>
                            <th width="25%">% of Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="plTableBody">
                        <!-- Table will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="button-group no-print">
                <button class="btn btn-ghost" id="printBtn">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button class="btn btn-secondary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer no-print">
            <p>FuelingSys ERP | Profit & Loss Statement Report</p>
        </div>
    </div>

    <!-- Zakat Calculator Modal -->
    <div id="zakatModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calculator"></i> Zakat Calculator</h2>
                <span class="close" onclick="closeZakatModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="zakat-row">
                    <label>Net Profit:</label>
                    <input type="text" id="zakatNetProfit" readonly>
                </div>
                <div class="zakat-row">
                    <label>Zakat %:</label>
                    <input type="number" id="zakatPercentage" value="2.5" min="0" max="100" step="0.1">
                </div>
                <div class="zakat-row">
                    <label>Zakat Amount:</label>
                    <input type="text" id="zakatAmount" readonly>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/profit_loss_statement/profit-loss.js"></script>
</body>
</html>