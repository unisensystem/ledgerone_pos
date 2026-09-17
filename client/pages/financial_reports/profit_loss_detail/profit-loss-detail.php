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
$currency_symbol = $currency['symbol'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP | Profit & Loss Detail Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/profit_loss_detail/profit-loss-detail.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1><i class="fas fa-chart-pie"></i> Profit & Loss Detail Report</h1>
                <p>Detailed profit analysis by items and customers</p>
            </div>
            <div class="button-group">
                <button class="btn btn-ghost no-print" id="helpBtn">
                    <i class="fas fa-question-circle"></i> Help
                </button>
                <button class="btn btn-secondary no-print" id="resetBtn">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </div>

        <!-- Validation Messages -->
        <div id="validationMessage" class="validation-message">
            <i class="fas fa-check-circle"></i>
            <span id="validationText"></span>
        </div>

        <!-- Help Section -->
        <div class="help-section no-print" id="helpSection" style="display: none;">
            <div class="help-title">
                <i class="fas fa-info-circle"></i> How to use the P&L Detail Report
            </div>
            <div class="help-content">
                <p>1. Select a date range for the report period.</p>
                <p>2. Choose filters: Company, Branch, Customer, or Product.</p>
                <p>3. Click "Generate Report" to view detailed profit analysis.</p>
                <p>4. Switch between "Item-wise", "Category-wise", "Customer-wise", "Invoice-wise", "Supplier-wise" and "Partner-wise" tabs.</p>
                <p>5. The "Partner-wise" tab shows each partner's capital account, their % share, and their share of the Gross Profit for the selected period. Partners are managed from the Chart of Accounts page ("Add Partner").</p>
                <p>6. Export the report in PDF or CSV format.</p>
            </div>
        </div>

        <!-- Report Parameters Card -->
        <div class="card no-print">
            <div class="card-title">Report Parameters</div>
            
            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate">Start Date</label>
                        <input type="date" id="startDate">
                        <div class="helper-text">Start of reporting period</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="endDate">End Date</label>
                        <input type="date" id="endDate">
                        <div class="helper-text">End of reporting period</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="companyFilter">Company</label>
                        <select id="companyFilter">
                            <option value="">All Companies</option>
                        </select>
                        <div class="helper-text">Filter by company</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="branchFilter">Branch</label>
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
                        <div class="helper-text">Filter by branch</div>
                    </div>

                    <div class="form-group">
                        <label for="categoryFilter">Category</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search category..." id="categoryFilterSearch" autocomplete="off">
                            <div class="dropdown-options" id="categoryFilterOptions"></div>
                            <input type="hidden" id="categoryFilter">
                        </div>
                        <div class="helper-text">Filter by category</div>
                    </div>

                    <div class="form-group">
                        <label for="customerFilter">Customer</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search customer..." id="customerFilterSearch" autocomplete="off">
                            <div class="dropdown-options" id="customerFilterOptions"></div>
                            <input type="hidden" id="customerFilter">
                        </div>
                        <div class="helper-text">Filter by customer</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="supplierFilter">Supplier</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search supplier..." id="supplierFilterSearch" autocomplete="off">
                            <div class="dropdown-options" id="supplierFilterOptions"></div>
                            <input type="hidden" id="supplierFilter">
                        </div>
                        <div class="helper-text">Filter by supplier</div>
                    </div>

                    <div class="form-group">
                        <label for="productFilter">Product</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search product..." id="productFilterSearch" autocomplete="off">
                            <div class="dropdown-options" id="productFilterOptions"></div>
                            <input type="hidden" id="productFilter">
                        </div>
                        <div class="helper-text">Filter by product</div>
                    </div>

                    <div class="form-group">
                        <label for="invoiceFilter">Invoice #</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search invoice..." id="invoiceFilterSearch" autocomplete="off">
                            <div class="dropdown-options" id="invoiceFilterOptions"></div>
                            <input type="hidden" id="invoiceFilter">
                        </div>
                        <div class="helper-text">Filter by invoice</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="currencyFilter">Currency</label>
                        <select id="currencyFilter">
                            <option value="">Loading...</option>
                        </select>
                        <div class="helper-text">Select currency</div>
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
                <div class="summary-card-title">Gross Profit</div>
                <div class="summary-card-value" id="grossProfit"><?php echo $currency_symbol; ?>0.00</div>
            </div>
            <div class="summary-card">
                <div class="summary-card-title">Profit Margin %</div>
                <div class="summary-card-value" id="profitMargin">0.00%</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="card" id="reportResults" style="display: none;">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="itemwise">
                    <i class="fas fa-box"></i> Item-wise P&L
                </button>
                <button class="tab-btn" data-tab="categorywise">
                    <i class="fas fa-layer-group"></i> Category-wise P&L
                </button>
                <button class="tab-btn" data-tab="customerwise">
                    <i class="fas fa-users"></i> Customer-wise P&L
                </button>
                <button class="tab-btn" data-tab="invoicewise">
                    <i class="fas fa-file-invoice"></i> Invoice-wise P&L
                </button>
                <button class="tab-btn" data-tab="supplierwise">
                    <i class="fas fa-truck"></i> Supplier-wise P&L
                </button>
                <button class="tab-btn" data-tab="partnerwise">
                    <i class="fas fa-handshake"></i> Partner-wise P&L
                </button>
            </div>

            <!-- Item-wise Tab -->
            <div class="tab-content active" id="itemwise">
                <div class="card-title">
                    Item-wise Profit & Loss
                    <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriodItem"></span>
                </div>
                
                <div class="table-container">
                    <table id="itemTable">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Product</th>
                                <th style="width: 12%;">Qty Sold</th>
                                <th style="width: 15%;">Revenue</th>
                                <th style="width: 15%;">COGS</th>
                                <th style="width: 15%;">Gross Profit</th>
                                <th style="width: 8%;">Margin %</th>
                            </tr>
                        </thead>
                        <tbody id="itemTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Category-wise Tab -->
            <div class="tab-content" id="categorywise">
                <div class="card-title">
                    Category-wise Profit & Loss
                    <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriodCategory"></span>
                </div>
                
                <div class="table-container">
                    <table id="categoryTable">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Category</th>
                                <th style="width: 10%;">Products</th>
                                <th style="width: 17%;">Revenue</th>
                                <th style="width: 17%;">COGS</th>
                                <th style="width: 17%;">Gross Profit</th>
                                <th style="width: 9%;">Margin %</th>
                            </tr>
                        </thead>
                        <tbody id="categoryTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Customer-wise Tab -->
            <div class="tab-content" id="customerwise">
                <div class="card-title">
                    Customer-wise Profit & Loss
                    <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriodCustomer"></span>
                </div>
                
                <div class="table-container">
                    <table id="customerTable">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Customer</th>
                                <th style="width: 10%;">Invoices</th>
                                <th style="width: 17%;">Revenue</th>
                                <th style="width: 17%;">COGS</th>
                                <th style="width: 17%;">Gross Profit</th>
                                <th style="width: 9%;">Margin %</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoice-wise Tab -->
            <div class="tab-content" id="invoicewise">
                <div class="card-title">
                    Invoice-wise Profit & Loss
                    <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriodInvoice"></span>
                </div>
                
                <div class="table-container">
                    <table id="invoiceTable">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Invoice #</th>
                                <th style="width: 12%;">Date</th>
                                <th style="width: 22%;">Customer</th>
                                <th style="width: 15%;">Revenue</th>
                                <th style="width: 15%;">COGS</th>
                                <th style="width: 15%;">Gross Profit</th>
                                <th style="width: 9%;">Margin %</th>
                            </tr>
                        </thead>
                        <tbody id="invoiceTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Supplier-wise Tab -->
            <div class="tab-content" id="supplierwise">
                <div class="card-title">
                    Supplier-wise Profit & Loss
                    <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriodSupplier"></span>
                </div>

                <div class="table-container">
                    <table id="supplierTable">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Code</th>
                                <th style="width: 28%;">Supplier</th>
                                <th style="width: 10%;">Purchases</th>
                                <th style="width: 17%;">Revenue</th>
                                <th style="width: 17%;">COGS</th>
                                <th style="width: 11%;">Gross Profit</th>
                                <th style="width: 7%;">Margin %</th>
                            </tr>
                        </thead>
                        <tbody id="supplierTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Partner-wise Tab -->
            <div class="tab-content" id="partnerwise">
                <div class="card-title">
                    Partner-wise Profit Distribution
                    <span style="font-size: 14px; font-weight: normal; color: var(--text-subtext); margin-left: 10px;" id="reportPeriodPartner"></span>
                </div>

                <div class="modal-info" style="margin-bottom: 16px;">
                    <p><strong>Note:</strong> Distribution is calculated on the <strong>Net Profit/Loss</strong> of the selected period (Revenue &minus; COGS). Partners and their % share are managed from the Chart of Accounts &rarr; "Add Partner" screen. Any percentage not assigned to a partner is shown as the remaining/owner's share.</p>
                </div>

                <div class="table-container">
                    <table id="partnerTable">
                        <thead>
                            <tr>
                                <th style="width: 45%;">Partner </th>
                                <th style="width: 20%;">Share %</th>
                                <th style="width: 35%;">Profit Share</th>
                            </tr>
                        </thead>
                        <tbody id="partnerTableBody">
                        </tbody>
                    </table>
                </div>
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
            <p>LedgerOne ERP | Profit & Loss Detail Report</p>
        </div>
    </div>

    <script>
        const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/financial_reports/profit_loss_detail/profit-loss-detail.js?v=2"></script>
</body>
</html>