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
$initialSearch = $_GET['search'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Sale Invoices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_invoice/purchase-list.css">
    <link rel="stylesheet" href="../../../assets/css/sale/daily_sale_report/daily-sale-report.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Sale Invoices</h1>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border-default);">
                <h2 class="card-title" style="margin:0; padding:0; border:none;">Filters & Search</h2>
                <button class="btn btn-secondary" id="customizeFiltersBtn" style="height:34px; padding:0 14px; font-size:13px;">
                    <i class="fas fa-sliders-h"></i> Customize Filters
                </button>
            </div>
            <div class="form-row">
                <div class="form-group" data-filter-key="dateFrom">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-input" id="dateFrom">
                </div>
                <div class="form-group" data-filter-key="dateTo">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-input" id="dateTo">
                </div>
                <div class="form-group" data-filter-key="company">
                    <label class="form-label">Company</label>
                    <div class="multi-select-dropdown" id="company-dropdown">
                        <div class="multi-select-trigger" id="company-trigger">
                            <span id="company-label">All Companies</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="company-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="customer">
                    <label class="form-label">Customer</label>
                    <div class="multi-select-dropdown" id="customer-dropdown">
                        <div class="multi-select-trigger" id="customer-trigger">
                            <span id="customer-label">All Customers</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="customer-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="salesOfficer">
                    <label class="form-label">Sales Officer</label>
                    <div class="multi-select-dropdown" id="sales-officer-dropdown">
                        <div class="multi-select-trigger" id="sales-officer-trigger">
                            <span id="sales-officer-label">All Sales Officers</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="sales-officer-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="supplierMan">
                    <label class="form-label">Supplier Man</label>
                    <div class="multi-select-dropdown" id="supplier-man-dropdown">
                        <div class="multi-select-trigger" id="supplier-man-trigger">
                            <span id="supplier-man-label">All Supplier Men</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="supplier-man-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="invoiceType">
                    <label class="form-label">Invoice Type</label>
                    <div class="multi-select-dropdown" id="invoice-type-dropdown">
                        <div class="multi-select-trigger" id="invoice-type-trigger">
                            <span id="invoice-type-label">All Types</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="invoice-type-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="vendor">
                    <label class="form-label">Vendor</label>
                    <div class="multi-select-dropdown" id="vendor-dropdown">
                        <div class="multi-select-trigger" id="vendor-trigger">
                            <span id="vendor-label">All Vendors</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="vendor-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="city">
                    <label class="form-label">City</label>
                    <div class="multi-select-dropdown" id="city-filter-dropdown">
                        <div class="multi-select-trigger" id="city-filter-trigger">
                            <span id="city-filter-label">All Cities</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="city-filter-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="cityZone">
                    <label class="form-label">City Zone</label>
                    <div class="multi-select-dropdown" id="city-zone-filter-dropdown">
                        <div class="multi-select-trigger" id="city-zone-filter-trigger">
                            <span id="city-zone-filter-label">All City Zones</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="city-zone-filter-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="area">
                    <label class="form-label">Area</label>
                    <div class="multi-select-dropdown" id="area-filter-dropdown">
                        <div class="multi-select-trigger" id="area-filter-trigger">
                            <span id="area-filter-label">All Areas</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multi-select-options" id="area-filter-options"></div>
                    </div>
                </div>
                <div class="form-group" data-filter-key="project">
                    <label class="form-label">Project</label>
                    <select class="form-input" id="projectFilter">
                        <option value="">All Projects</option>
                    </select>
                </div>
            </div>

            <div class="actions">
                <div style="display:flex; align-items:center; gap:0; border:1.5px solid var(--border-default); border-radius:var(--radius); overflow:hidden; background:var(--surface-0); width:320px;">
                    <i class="fas fa-search" style="padding:0 12px; color:var(--subtext); flex-shrink:0;"></i>
                    <input type="text" id="searchInput" placeholder="Search invoice no, customer..." style="border:none; border-radius:0; height:38px; padding:0; flex:1; background:transparent; box-shadow:none;" value="<?= htmlspecialchars($initialSearch) ?>">
                    <button class="btn btn-primary" id="searchBtn" style="border-radius:0; height:38px; padding:0 16px; flex-shrink:0; border:none;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" id="applyFiltersBtn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button class="btn btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                    <button class="btn btn-success" id="printSelectedBtn" style="display:none;">
                        <i class="fas fa-print"></i> Print Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-danger" id="deleteSelectedBtn" style="display:none;">
                        <i class="fas fa-trash"></i> Delete Selected (<span id="selectedDeleteCount">0</span>)
                    </button>
                    <button class="btn btn-primary" id="newInvoiceBtn">
                        <i class="fas fa-plus"></i> New Invoice
                    </button>
                </div>
            </div>

            <div class="summary-section">
                <div class="summary-card">
                    <div class="summary-item">
                        <span class="summary-label">Total Invoices</span>
                        <span class="summary-value" id="totalInvoiceCount">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value" id="totalAmountSum">₹ 0.00</span>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="3%"><input type="checkbox" id="selectAllCheckbox" title="Select All"></th>
                            <th width="10%">Invoice No</th>
                            <th width="9%">Date</th>
                            <th width="16%">Customer</th>
                            <th width="10%">Sales Officer</th>
                            <th width="5%">Items</th>
                            <th width="10%">Total Amount</th>
                            <th width="8%">DC Status</th>
                            <th width="10%">Created By</th>
                            <th width="19%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
                    <select id="pageLimitSelect" style="height:34px; padding:0 10px; font-size:13px; width:auto;">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="250">250</option>
                    </select>
                    <span style="font-size:13px; color:var(--subtext);">per page</span>
                </div>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
        </div>
    </div>

    <!-- Customize Filters Modal -->
    <div class="modal" id="customizeFiltersModal">
        <div class="modal-content" style="width:460px;">
            <h3 class="modal-title"><i class="fas fa-sliders-h" style="margin-right:8px;"></i>Customize Filters</h3>
            <p style="font-size:13px; color:var(--subtext); margin-bottom:16px;">Select which filters to show or hide.</p>
            <div id="filterToggleList" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px;"></div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="filterCustomizeSelectAll" style="margin-right:auto;"><i class="fas fa-check-double"></i> Show All</button>
                <button class="btn btn-secondary" id="closeCustomizeFiltersBtn">Cancel</button>
                <button class="btn btn-primary" id="saveCustomizeFiltersBtn"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h3 class="modal-title">Confirm Delete</h3>
            <p>Are you sure you want to delete this sale invoice? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- DC Fulfillment Modal -->
    <div class="modal" id="dcFulfillmentModal">
        <div class="modal-content" style="width:800px; max-width:95%;">
            <h3 class="modal-title">Delivery Chalan Status</h3>
            <div class="table-container" style="max-height:400px; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="padding:8px; border-bottom:1px solid var(--border-default);">Product</th>
                            <th style="padding:8px; border-bottom:1px solid var(--border-default); text-align:right;">Invoiced</th>
                            <th style="padding:8px; border-bottom:1px solid var(--border-default); text-align:right;">Delivered</th>
                            <th style="padding:8px; border-bottom:1px solid var(--border-default); text-align:right;">Remaining</th>
                            <th style="padding:8px; border-bottom:1px solid var(--border-default); text-align:right;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="dcFulfillmentTableBody">
                        <tr><td colspan="5" style="text-align:center; padding:20px;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeDcFulfillmentBtn">Close</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/sale/traaders_pos_invoice/pos-list.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/sale/traaders_pos_invoice/pos-list.js') ?>"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
</body>
</html>
