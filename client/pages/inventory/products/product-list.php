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
    <title>LedgerOne ERP - Products List</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/products/product-list.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/products/unit-modal.css">
</head>
<style>
    #productsTable thead th {
    white-space: nowrap;
}
</style>

<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Products</h1>
                <p class="page-description">Manage your products and services inventory</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" id="aiqBtn">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 4H14M2 8H14M2 12H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Labels
                </button>
                <div class="dropdown" id="columnsDropdownWrapper">
                    <button class="btn btn-secondary dropdown-toggle" id="columnsBtn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 3H14M2 8H14M2 13H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Columns
                    </button>
                    <div class="dropdown-menu" id="columnsDropdown" style="padding: 8px; min-width: 200px;">
                        <!-- Checkboxes JS se inject honge -->
                    </div>
                </div>

                <button class="btn btn-secondary" id="assignUomBtn" style="display: none;">
                    ...
                    Assign UOM (<span id="selectedCount">0</span>)
                </button>
                <button class="btn btn-secondary" id="deleteSelectedBtn" style="display: none; font-size: 11px;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 4H3.33333H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5.33337 4V2.66667C5.33337 2.31305 5.47385 1.97391 5.7239 1.72386C5.97395 1.47381 6.31309 1.33334 6.66671 1.33334H9.33337C9.68699 1.33334 10.0261 1.47381 10.2762 1.72386C10.5262 1.97391 10.6667 2.31305 10.6667 2.66667V4M12.6667 4V13.3333C12.6667 13.687 12.5262 14.0261 12.2762 14.2761C12.0261 14.5262 11.687 14.6667 11.3334 14.6667H4.66671C4.31309 14.6667 3.97395 14.5262 3.7239 14.2761C3.47385 14.0261 3.33337 13.687 3.33337 13.3333V4H12.6667Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Delete Selected <span id="deleteSelectedCount">0</span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" id="exportBtn">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 10V12.6667C14 13.0203 13.8595 13.3594 13.6095 13.6095C13.3594 13.8595 13.0203 14 12.6667 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4.66675 6.66667L8.00008 10L11.3334 6.66667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M8 10V2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Export
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-left: 4px;">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="exportDropdown">
                        <button class="dropdown-item" id="printList">Print List</button>
                        <button class="dropdown-item" id="exportExcel">Export To Excel</button>
                        <button class="dropdown-item" id="exportJson">Export JSON</button>
                    </div>
                </div>
                <button class="btn btn-secondary" id="bulkOpeningStockBtn" title="Add opening stock for multiple products">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 4H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M3 2H13C13.5304 2 14.0391 2.21071 14.4142 2.58579C14.7893 2.96086 15 3.46957 15 4V12C15 12.5304 14.7893 13.0391 14.4142 13.4142C14.0391 13.7893 13.5304 14 13 14H3C2.46957 14 1.96086 13.7893 1.58579 13.4142C1.21071 13.0391 1 12.5304 1 12V4C1 3.46957 1.21071 2.96086 1.58579 2.58579C1.96086 2.21071 2.46957 2 3 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5 7H11M5 10H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Bulk Opening Stock
                </button>
                <button class="btn btn-secondary" id="bulkRateUpdateBtn" onclick="location.href='bulk-rate-update.php'" title="Update rates for multiple products">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 8h12M8 2v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                    Bulk Rate Update
                </button>
                <button class="btn btn-primary" id="addProductBtn">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 3.33333V12.6667" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M3.33325 8H12.6666" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Add Product
                </button>

                <a href="product-import.php" class="btn btn-secondary btn-sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-file-import"></i>
                    Import Products
                </a>
            </div>
        </div>

        <div class="card">
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" placeholder="Product name or code..." value="<?= htmlspecialchars($initialSearch) ?>">
                </div>
                <div class="filter-group">
                    <label>Price Type</label>
                    <div style="display: flex; gap: 16px; margin-top: 6px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal;">
                            <input type="radio" name="priceType" value="tp" checked>
                            Use Trade Price (TP)
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: normal;">
                            <input type="radio" name="priceType" value="mrp">
                            Use Maximum Retail Price (MRP)
                        </label>
                    </div>
                </div>
                <div class="filter-group">
                    <label for="uomFilter">UOM Type</label>
                    <select id="uomFilter">
                        <option value="">All UOM Types</option>
                        <option value="unit">Default Unit</option>
                        <option value="group">UOM Group</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="category">Category</label>
                    <select id="category">
                        <option value="">All Categories</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="type">Product Type</label>
                    <select id="type">
                        <option value="">All Types</option>
                        <option value="product">Physical Product</option>
                        <option value="service">Service</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="vendor">Vendor</label>
                    <select id="vendor">
                        <option value="">All Vendors</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="itemCompany">Item Company</label>
                    <select id="itemCompany">
                        <option value="">All Item Companies</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-secondary" id="applyFilters">Apply Filters</button>
                    <button class="btn btn-ghost" id="resetFilters">Reset</button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="table-container">
                <table id="productsTable">
                   <thead>
    <tr>
        <th style="width: 40px;">
            <input type="checkbox" id="selectAll">
        </th>
        <th data-col="code">Product Code</th>
        <th data-col="name">Name</th>
        <th data-col="type">Type</th>
        <th data-col="uom">UOM</th>
        <th data-col="category">Category</th>
<th data-col="vendor">Vendor</th>
<th data-col="itemCompany">Item Company</th>
        <th data-col="price">TP</th>
       <th data-col="purchasePrice">Purchase Price</th>
<th data-col="batchNo">Batch No</th>
<th data-col="batchExpiryDate">Batch Expiry Date</th>
<th data-col="stock">Stock</th>
<th data-col="status">Status</th>
        <th>Actions</th>
    </tr>
</thead>
                    <tbody id="tableBody">
                        <!-- Table rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State (hidden by default) -->
            <div id="emptyState" class="empty-state" style="display: none;">
                <div class="empty-state-icon">≡ƒôª</div>
                <h3>No products found</h3>
                <p>Try adjusting your search or filters, or add a new product.</p>
                <button class="btn btn-primary" id="addFirstProduct" style="margin-top: 16px;">Add Your First Product</button>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 0-0 of 0 products</div>

                <div style="display:flex; align-items:center; gap:16px;">
                    <div class="page-size-selector" style="display:flex; align-items:center; gap:6px;">
                        <label for="pageSize" style="font-weight:normal; margin:0;">Show:</label>
                        <select id="pageSize" style="padding:4px 8px;">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                            <option value="40">40</option>
                            <option value="50">50</option>
                            <option value="60">60</option>
                            <option value="70">70</option>
                            <option value="80">80</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <div class="pagination-controls">
                        <button class="pagination-btn" id="prevPage" disabled>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <button class="pagination-btn" id="nextPage">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'delete-modal.php'; ?>
    <?php include 'print-code-modal.php'; ?>
    <?php include 'bulk-uom-modal.php'; ?>
    <?php include 'aiq-modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="../../../assets/js/inventory/products/product-list.js?v=2"></script>
    <?php if ($initialSearch): ?>
        <script>
            window.addEventListener('load', function() {
                const applyBtn = document.getElementById('applyFilters');
                if (applyBtn) applyBtn.click();
            });
        </script>
    <?php endif; ?>
</body>

</html>