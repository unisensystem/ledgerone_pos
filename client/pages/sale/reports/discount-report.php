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
    <style>
        /* Searchable Dropdown Styles */
        .searchable-select {
            position: relative;
            width: 100%;
        }

        .select-trigger {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            transition: all 0.2s;
        }

        .select-trigger:hover {
            border-color: #999;
        }

        .select-trigger.active {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .select-arrow {
            font-size: 12px;
            transition: transform 0.2s;
        }

        .select-trigger.active .select-arrow {
            transform: rotate(180deg);
        }

        .select-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 300px;
            overflow: hidden;
            display: none;
        }

        .select-dropdown.show {
            display: flex;
            flex-direction: column;
        }

        .select-search {
            padding: 8px;
            border-bottom: 1px solid #eee;
            flex-shrink: 0;
        }

        .select-search input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .select-search input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .select-options {
            overflow-y: auto;
            flex: 1;
            max-height: 260px;
        }

        .select-option {
            padding: 10px 12px;
            cursor: pointer;
            transition: background 0.1s;
            font-size: 14px;
            color: #333;
        }

        .select-option:hover {
            background: #f0f0f0;
        }

        .select-option.selected {
            background: #e0e7ff;
            color: #2563eb;
            font-weight: 500;
        }

        .select-option.hidden {
            display: none;
        }

        .select-no-options {
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 13px;
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
            .columns-panel {
                right: 0;
                width: min(260px, calc(100vw - 40px));
            }

            .select-dropdown {
                max-height: 250px;
            }

            .select-options {
                max-height: 210px;
            }
        }
    </style>
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
                    <div class="searchable-select" data-field="salesman">
                        <div class="select-trigger">
                            <span>All Salesmen</span>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                        <div class="select-dropdown">
                            <div class="select-search">
                                <input type="text" placeholder="Search salesmen..." class="search-input">
                            </div>
                            <div class="select-options"></div>
                            <div class="select-no-options" style="display:none;">No results found</div>
                        </div>
                    </div>
                    <input type="hidden" id="salesmanFilter" class="filter-value">
                </div>

                <div class="filter-group">
                    <label>Supplier</label>
                    <div class="searchable-select" data-field="supplier">
                        <div class="select-trigger">
                            <span>All Suppliers</span>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                        <div class="select-dropdown">
                            <div class="select-search">
                                <input type="text" placeholder="Search suppliers..." class="search-input">
                            </div>
                            <div class="select-options"></div>
                            <div class="select-no-options" style="display:none;">No results found</div>
                        </div>
                    </div>
                    <input type="hidden" id="supplierFilter" class="filter-value">
                </div>

                <div class="filter-group">
                    <label>Branch</label>
                    <div class="searchable-select" data-field="branch">
                        <div class="select-trigger">
                            <span>All Branches</span>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                        <div class="select-dropdown">
                            <div class="select-search">
                                <input type="text" placeholder="Search branches..." class="search-input">
                            </div>
                            <div class="select-options"></div>
                            <div class="select-no-options" style="display:none;">No results found</div>
                        </div>
                    </div>
                    <input type="hidden" id="branchFilter" class="filter-value">
                </div>

                <div class="filter-group">
                    <label>Customer</label>
                    <div class="searchable-select" data-field="customer">
                        <div class="select-trigger">
                            <span>All Customers</span>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                        <div class="select-dropdown">
                            <div class="select-search">
                                <input type="text" placeholder="Search customers..." class="search-input">
                            </div>
                            <div class="select-options"></div>
                            <div class="select-no-options" style="display:none;">No results found</div>
                        </div>
                    </div>
                    <input type="hidden" id="customerFilter" class="filter-value">
                </div>

                <div class="filter-group">
                    <label>Product</label>
                    <div class="searchable-select" data-field="product">
                        <div class="select-trigger">
                            <span>All Products</span>
                            <i class="fas fa-chevron-down select-arrow"></i>
                        </div>
                        <div class="select-dropdown">
                            <div class="select-search">
                                <input type="text" placeholder="Search products..." class="search-input">
                            </div>
                            <div class="select-options"></div>
                            <div class="select-no-options" style="display:none;">No results found</div>
                        </div>
                    </div>
                    <input type="hidden" id="productFilter" class="filter-value">
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

    <script>
        // ============================================
        // SEARCHABLE SELECT CLASS
        // ============================================
        class SearchableSelect {
            constructor(element) {
                this.element = element;
                this.field = element.dataset.field;
                this.trigger = element.querySelector('.select-trigger');
                this.dropdown = element.querySelector('.select-dropdown');
                this.searchInput = element.querySelector('.search-input');
                this.optionsContainer = element.querySelector('.select-options');
                this.noOptions = element.querySelector('.select-no-options');
                this.filterInput = document.getElementById(`${this.field}Filter`);
                this.selectedValue = null;
                this.selectedLabel = `All ${this.field.charAt(0).toUpperCase() + this.field.slice(1)}s`;
                this.allOptions = [];
                
                this.init();
            }

            init() {
                this.trigger.addEventListener('click', () => this.toggle());
                this.searchInput.addEventListener('input', () => this.filterOptions());
                document.addEventListener('click', (e) => this.handleOutsideClick(e));
            }

            toggle() {
                this.dropdown.classList.toggle('show');
                this.trigger.classList.toggle('active');
                if (this.dropdown.classList.contains('show')) {
                    setTimeout(() => this.searchInput.focus(), 0);
                }
            }

            close() {
                this.dropdown.classList.remove('show');
                this.trigger.classList.remove('active');
                this.searchInput.value = '';
                this.filterOptions();
            }

            handleOutsideClick(e) {
                if (!this.element.contains(e.target)) {
                    this.close();
                }
            }

            setOptions(options) {
                this.allOptions = options;
                this.renderOptions(options);
            }

            renderOptions(options) {
                this.optionsContainer.innerHTML = '';
                
                options.forEach(option => {
                    const div = document.createElement('div');
                    div.className = 'select-option';
                    div.textContent = option.label;
                    div.dataset.value = option.value;
                    
                    if (this.selectedValue === option.value) {
                        div.classList.add('selected');
                    }
                    
                    div.addEventListener('click', () => this.selectOption(option));
                    this.optionsContainer.appendChild(div);
                });
            }

            selectOption(option) {
                this.selectedValue = option.value;
                this.selectedLabel = option.label;
                this.filterInput.value = option.value;
                this.trigger.querySelector('span').textContent = option.label;
                
                // Update selected state
                document.querySelectorAll(`[data-field="${this.field}"] .select-option`).forEach(el => {
                    el.classList.remove('selected');
                });
                
                const selected = document.querySelector(`[data-field="${this.field}"] [data-value="${option.value}"]`);
                if (selected) {
                    selected.classList.add('selected');
                }
                
                this.close();
                
                // Trigger report load
                setTimeout(() => loadReport(), 100);
            }

            filterOptions() {
                const searchTerm = this.searchInput.value.toLowerCase();
                const filtered = this.allOptions.filter(opt => 
                    opt.label.toLowerCase().includes(searchTerm)
                );
                
                this.renderOptions(filtered);
                this.noOptions.style.display = filtered.length === 0 ? 'block' : 'none';
            }
        }

        // Store instances
        const selectInstances = {};

        // ============================================
        // REPORT VARIABLES & CONFIGURATION
        // ============================================
        let allReportData = [];
        let currentFilters = {};
        let lastTotals = { gross_amount: 0, discount_amount: 0, extra_discount_amount: 0, tax_amount: 0, net_amount: 0, count: 0 };

        const COLUMNS = [
            { key: 'sno',              label: 'S#',            width: '4%',  align: 'left',  footer: 'label' },
            { key: 'date',             label: 'Date',           width: '7%',  align: 'left',  footer: 'label' },
            { key: 'billno',           label: 'Bill No',        width: '7%',  align: 'left',  footer: 'label' },
            { key: 'customer',         label: 'Customer',       width: '10%', align: 'left',  footer: 'label' },
            { key: 'product',          label: 'Product',        width: '10%', align: 'left',  footer: 'label' },
            { key: 'salesman',         label: 'Salesman',       width: '8%',  align: 'left',  footer: 'label' },
            { key: 'supplier',         label: 'Supplier',       width: '8%',  align: 'left',  footer: 'label' },
            { key: 'qty',              label: 'Qty',            width: '5%',  align: 'right', footer: 'label' },
            { key: 'price',            label: 'Price',          width: '7%',  align: 'right', footer: 'label' },
            { key: 'gross',            label: 'Gross Amt',      width: '8%',  align: 'right', footer: 'footerGross' },
            { key: 'discPercent',      label: 'Disc %',         width: '6%',  align: 'right', footer: 'footerDiscPercent' },
            { key: 'discAmt',          label: 'Disc Amt',       width: '8%',  align: 'right', footer: 'footerDisc' },
            { key: 'extraDiscPercent', label: 'Extra Disc %',   width: '6%',  align: 'right', footer: 'footerExtraDiscPercent' },
            { key: 'extraDiscAmt',     label: 'Extra Disc Amt', width: '8%',  align: 'right', footer: 'footerExtraDisc' },
            { key: 'taxPercent',       label: 'Tax %',          width: '5%',  align: 'right', footer: 'footerTaxPercent' },
            { key: 'net',              label: 'Net Amt',        width: '8%',  align: 'right', footer: 'footerNet' }
        ];

        const COLUMN_STORAGE_KEY = 'discountReportHiddenColumns';

        // ============================================
        // INITIALIZATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize searchable selects
            document.querySelectorAll('.searchable-select').forEach(element => {
                const field = element.dataset.field;
                selectInstances[field] = new SearchableSelect(element);
            });

            setDefaultDates();
            buildColumnsPanel();
            renderTableStructure();
            loadFiltersData();
            loadReport();
            attachFilterListeners();
            document.addEventListener('click', handleOutsideClickForColumnsPanel);
        });

        // ============================================
        // DATE & FILTER FUNCTIONS
        // ============================================
        function setDefaultDates() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

            document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
        }

        // Load filter dropdown data from API
        async function loadFiltersData() {
            try {
                const response = await fetch('../../../../server/api/sale/reports/discount-report-api.php?type=filters');
                const data = await response.json();

                if (data.success) {
                    // Populate searchable selects
                    populateSearchableSelect('branch', data.filters.branches, 'id', 'branch_name');
                    populateSearchableSelect('customer', data.filters.customers, 'id', 'customer_name');
                    populateSearchableSelect('product', data.filters.products, 'id', 'name');
                    populateSearchableSelect('salesman', data.filters.salesmen, 'id', 'full_name');
                    populateSearchableSelect('supplier', data.filters.suppliers, 'id', 'supplier_name');
                }
            } catch (error) {
                console.error('Error loading filter data:', error);
            }
        }

        // Populate searchable select with data
        function populateSearchableSelect(field, data, valueKey, labelKey) {
            if (!selectInstances[field]) return;

            const options = [
                { label: `All ${field.charAt(0).toUpperCase() + field.slice(1)}s`, value: '' },
                ...data.map(item => ({
                    label: item[labelKey],
                    value: item[valueKey]
                }))
            ];

            selectInstances[field].setOptions(options);
        }

        // Attach filter event listeners
        function attachFilterListeners() {
            document.getElementById('startDate').addEventListener('change', () => {
                setTimeout(() => loadReport(), 300);
            });
            document.getElementById('endDate').addEventListener('change', () => {
                setTimeout(() => loadReport(), 300);
            });
            document.getElementById('minDiscount').addEventListener('change', () => {
                setTimeout(() => loadReport(), 300);
            });
            document.getElementById('maxDiscount').addEventListener('change', () => {
                setTimeout(() => loadReport(), 300);
            });
        }

        // Toggle filters visibility
        function toggleFilters() {
            const filtersGrid = document.getElementById('filtersGrid');
            const toggleBtn = document.getElementById('toggleFilters');

            filtersGrid.style.display = filtersGrid.style.display === 'none' ? 'grid' : 'none';
            toggleBtn.innerHTML = filtersGrid.style.display === 'none' ?
                '<i class="fas fa-chevron-down"></i>' :
                '<i class="fas fa-chevron-up"></i>';
        }

        // ============================================
        // COLUMN MANAGEMENT
        // ============================================
        function getHiddenColumns() {
            try {
                const raw = localStorage.getItem(COLUMN_STORAGE_KEY);
                return raw ? JSON.parse(raw) : [];
            } catch (e) {
                return [];
            }
        }

        function setHiddenColumns(hiddenArr) {
            localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(hiddenArr));
        }

        function isColumnVisible(key) {
            return !getHiddenColumns().includes(key);
        }

        function buildColumnsPanel() {
            const panel = document.getElementById('columnsPanel');
            if (!panel) return;

            const hidden = getHiddenColumns();

            panel.innerHTML = `
                <div class="columns-panel-header">
                    <span>Show / Hide Columns</span>
                    <button type="button" class="columns-panel-reset" onclick="resetColumns()">Reset</button>
                </div>
                <div class="columns-panel-list">
                    ${COLUMNS.map(col => `
                        <label class="columns-panel-item">
                            <input type="checkbox" data-col-key="${col.key}" ${hidden.includes(col.key) ? '' : 'checked'} onchange="onColumnToggle('${col.key}', this.checked)">
                            <span>${col.label}</span>
                        </label>
                    `).join('')}
                </div>
            `;
        }

        function toggleColumnsPanel() {
            const panel = document.getElementById('columnsPanel');
            if (!panel) return;
            panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
        }

        function handleOutsideClickForColumnsPanel(e) {
            const panel = document.getElementById('columnsPanel');
            const btn = document.getElementById('columnsBtn');
            if (!panel || panel.style.display !== 'block') return;
            if (panel.contains(e.target) || (btn && btn.contains(e.target))) return;
            panel.style.display = 'none';
        }

        function onColumnToggle(key, checked) {
            let hidden = getHiddenColumns();
            if (checked) {
                hidden = hidden.filter(k => k !== key);
            } else {
                if (!hidden.includes(key)) hidden.push(key);
            }
            setHiddenColumns(hidden);
            renderTableStructure();
            displayReport(allReportData, lastTotals);
        }

        function resetColumns() {
            setHiddenColumns([]);
            buildColumnsPanel();
            renderTableStructure();
            displayReport(allReportData, lastTotals);
        }

        // Build table structure
        function renderTableStructure() {
            const visibleColumns = COLUMNS.filter(col => isColumnVisible(col.key));

            // Header
            const headRow = document.getElementById('tableHeadRow');
            if (headRow) {
                headRow.innerHTML = visibleColumns.map(col =>
                    `<th width="${col.width}" data-col="${col.key}">${col.label}</th>`
                ).join('');
            }

            // Footer
            const footRow = document.getElementById('tableFootRow');
            if (footRow) {
                const labelCols = visibleColumns.filter(c => c.footer === 'label');
                const valueCols = visibleColumns.filter(c => c.footer !== 'label');

                let html = '';
                if (labelCols.length > 0) {
                    html += `<td colspan="${labelCols.length}" style="text-align: right; font-weight: 600;">TOTALS:</td>`;
                }
                valueCols.forEach(col => {
                    html += `<td id="${col.footer}" style="font-weight: 600;">${col.align === 'right' ? '' : ''}</td>`;
                });
                footRow.innerHTML = html;
            }

            const colCount = Math.max(visibleColumns.length, 1);
            const loadingCell = document.querySelector('#tableBody td[colspan]');
            if (loadingCell) loadingCell.setAttribute('colspan', colCount);
        }

        // Get cell content
        function getCellContent(row, key, index) {
            switch (key) {
                case 'sno': return index + 1;
                case 'date': return formatDate(row.sale_date);
                case 'billno': return `<strong>${row.bill_no}</strong>`;
                case 'customer': return `${row.customer_code}<br><small style="color: var(--subtext);">${row.customer_name}</small>`;
                case 'product': return `${row.product_code}<br><small style="color: var(--subtext);">${row.product_name}</small>`;
                case 'salesman': return row.salesman_name || 'Unassigned';
                case 'supplier': return row.supplier_name || '-';
                case 'qty': return parseFloat(row.quantity).toFixed(3);
                case 'price': return `${row.currency_symbol} ${parseFloat(row.sale_price).toFixed(3)}`;
                case 'gross': return `${row.currency_symbol} ${parseFloat(row.gross_amount).toFixed(3)}`;
                case 'discPercent': return `<span class="badge">${parseFloat(row.calculated_disc_percent).toFixed(2)}%</span>`;
                case 'discAmt': return `${row.currency_symbol} ${parseFloat(row.discount_amount).toFixed(3)}`;
                case 'extraDiscPercent': return `<span class="badge" style="background: var(--warning);">${parseFloat(row.calculated_extra_disc_percent).toFixed(2)}%</span>`;
                case 'extraDiscAmt': return `${row.currency_symbol} ${parseFloat(row.extra_discount_amount).toFixed(3)}`;
                case 'taxPercent': return `${parseFloat(row.tax_percent).toFixed(2)}%`;
                case 'net': return `<strong>${row.currency_symbol} ${parseFloat(row.net_amount).toFixed(3)}</strong>`;
                default: return '';
            }
        }

        // ============================================
        // REPORT LOADING & DISPLAY
        // ============================================
        async function loadReport() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const customerId = document.getElementById('customerFilter').value;
            const productId = document.getElementById('productFilter').value;
            const branchId = document.getElementById('branchFilter').value;
            const salesmanId = document.getElementById('salesmanFilter').value;
            const supplierId = document.getElementById('supplierFilter').value;
            const minDiscount = document.getElementById('minDiscount').value;
            const maxDiscount = document.getElementById('maxDiscount').value;

            // Store current filters
            currentFilters = {
                startDate, endDate, customerId, productId, branchId, salesmanId, supplierId, minDiscount, maxDiscount
            };

            // Build query string
            let queryString = '../../../../server/api/sale/reports/discount-report-api.php?';
            if (startDate) queryString += `startDate=${startDate}&`;
            if (endDate) queryString += `endDate=${endDate}&`;
            if (customerId) queryString += `customerId=${customerId}&`;
            if (productId) queryString += `productId=${productId}&`;
            if (branchId) queryString += `branchId=${branchId}&`;
            if (salesmanId) queryString += `salesmanId=${salesmanId}&`;
            if (supplierId) queryString += `supplierId=${supplierId}&`;
            if (minDiscount) queryString += `minDiscount=${minDiscount}&`;
            if (maxDiscount) queryString += `maxDiscount=${maxDiscount}&`;

            try {
                const response = await fetch(queryString);
                const result = await response.json();

                if (result.success) {
                    allReportData = result.data;
                    displayReport(result.data, result.totals);
                } else {
                    console.error('API Error:', result.message);
                    allReportData = [];
                    displayReport([], lastTotals);
                }
            } catch (error) {
                console.error('Error loading report:', error);
                allReportData = [];
                displayReport([], lastTotals);
            }
        }

        // Display report data
        function displayReport(data, totals) {
            lastTotals = totals || lastTotals;

            const tbody = document.getElementById('tableBody');
            const noData = document.getElementById('noDataMessage');
            const reportTable = document.querySelector('.report-table-container');

            if (!data || data.length === 0) {
                tbody.innerHTML = '';
                noData.style.display = 'block';
                reportTable.style.display = 'none';
                updateSummary({ count: 0, gross_amount: 0, discount_amount: 0, extra_discount_amount: 0, net_amount: 0 });
                return;
            }

            noData.style.display = 'none';
            reportTable.style.display = 'block';

            const visibleColumns = COLUMNS.filter(col => isColumnVisible(col.key));

            tbody.innerHTML = data.map((row, index) => `
                <tr>
                    ${visibleColumns.map(col =>
                        `<td data-col="${col.key}" class="${col.align === 'right' ? 'text-right' : ''}">${getCellContent(row, col.key, index)}</td>`
                    ).join('')}
                </tr>
            `).join('');

            updateSummary(lastTotals);
            updateFooter(lastTotals);
        }

        // Update summary cards
        function updateSummary(totals) {
            document.getElementById('totalRecords').textContent = totals.count || 0;
            document.getElementById('totalGross').textContent = '₨ ' + parseFloat(totals.gross_amount || 0).toFixed(3);
            document.getElementById('totalDiscount').textContent = '₨ ' + parseFloat(totals.discount_amount || 0).toFixed(3);
            document.getElementById('totalExtraDiscount').textContent = '₨ ' + parseFloat(totals.extra_discount_amount || 0).toFixed(3);
            document.getElementById('totalNet').textContent = '₨ ' + parseFloat(totals.net_amount || 0).toFixed(3);
        }

        // Update footer totals
        function updateFooter(totals) {
            const avgDisc = totals.gross_amount > 0 ?
                ((totals.discount_amount / totals.gross_amount) * 100).toFixed(2) : '0.00';
            const avgExtraDisc = (totals.gross_amount - totals.discount_amount) > 0 ?
                ((totals.extra_discount_amount / (totals.gross_amount - totals.discount_amount)) * 100).toFixed(2) : '0.00';
            const avgTax = totals.net_amount > 0 ?
                ((totals.tax_amount / totals.net_amount) * 100).toFixed(2) : '0.00';

            setTextIfExists('footerGross', '₨ ' + parseFloat(totals.gross_amount || 0).toFixed(3));
            setTextIfExists('footerDiscPercent', avgDisc + '%');
            setTextIfExists('footerDisc', '₨ ' + parseFloat(totals.discount_amount || 0).toFixed(3));
            setTextIfExists('footerExtraDiscPercent', avgExtraDisc + '%');
            setTextIfExists('footerExtraDisc', '₨ ' + parseFloat(totals.extra_discount_amount || 0).toFixed(3));
            setTextIfExists('footerTaxPercent', avgTax + '%');
            setTextIfExists('footerNet', '₨ ' + parseFloat(totals.net_amount || 0).toFixed(3));
        }

        function setTextIfExists(id, text) {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-PK', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('minDiscount').value = '';
            document.getElementById('maxDiscount').value = '';

            // Reset selects
            Object.keys(selectInstances).forEach(field => {
                selectInstances[field].selectedValue = null;
                selectInstances[field].filterInput.value = '';
                selectInstances[field].trigger.querySelector('span').textContent = 
                    `All ${field.charAt(0).toUpperCase() + field.slice(1)}s`;
            });

            setDefaultDates();
            loadReport();
        }

        // Print report
        function printReport() {
            document.getElementById('printModal').style.display = 'block';
        }

        function confirmPrint() {
            const format = document.querySelector('input[name="printFormat"]:checked').value;
            document.getElementById('printModal').style.display = 'none';

            if (format === 'current') {
                window.print();
            } else {
                const params = new URLSearchParams(currentFilters);
                window.open(`discount-report-print.php?${params.toString()}`, '_blank');
            }
        }

        // Export to CSV
        function exportToCSV() {
            if (allReportData.length === 0) {
                alert('No data to export');
                return;
            }

            const visibleColumns = COLUMNS.filter(col => isColumnVisible(col.key));

            const csvGetters = {
                sno: (row, i) => i + 1,
                date: row => row.sale_date,
                billno: row => row.bill_no,
                customer: row => `${row.customer_code} - ${row.customer_name}`,
                product: row => `${row.product_code} - ${row.product_name}`,
                salesman: row => row.salesman_name || 'Unassigned',
                supplier: row => row.supplier_name || '-',
                qty: row => row.quantity,
                price: row => row.sale_price,
                gross: row => row.gross_amount,
                discPercent: row => row.calculated_disc_percent,
                discAmt: row => row.discount_amount,
                extraDiscPercent: row => row.calculated_extra_disc_percent,
                extraDiscAmt: row => row.extra_discount_amount,
                taxPercent: row => row.tax_percent,
                net: row => row.net_amount
            };

            let csv = visibleColumns.map(c => c.label).join(',') + '\n';

            allReportData.forEach((row, index) => {
                csv += visibleColumns.map(col => {
                    const val = csvGetters[col.key](row, index);
                    return `"${String(val).replace(/"/g, '""')}"`;
                }).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `discount-report-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Additional styles
        const style = document.createElement('style');
        style.textContent = `
            .badge {
                display: inline-block;
                padding: 2px 8px;
                background: var(--success);
                color: white;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
            }

            .text-right {
                text-align: right;
            }

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
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
