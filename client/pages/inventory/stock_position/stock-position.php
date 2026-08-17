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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stock Management - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_position/stock-position.css">
    
</head>
<body>
    <!-- Main Content -->
    <main class="container">
        <!-- Dashboard Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-value">0.00</div>
                <div class="stat-label">Total Stock Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value">8 Products</div>
                <div class="stat-label">In Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value">2 Products</div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon error">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value">1 Product</div>
                <div class="stat-label">Out of Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(138, 43, 226, 0.1); color: #8a2be2;">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-value">0 Products</div>
                <div class="stat-label">Overstock</div>
            </div>
        </div>

        <!-- Stock Reports Tabs -->
        <div class="card">
            <div class="tabs">
                <div class="tab active" data-tab="position">Stock Position</div>
                <div class="tab" data-tab="item-ledger">Item-wise Ledger</div>
                <div class="tab" data-tab="branch-ledger">Branch-wise Ledger</div>
                <div class="tab" data-tab="detailed-ledger">Detailed Ledger</div>
                <div class="tab" data-tab="customer-ledger">Customer-wise Ledger</div>
            </div>

            <!-- Stock Position Tab -->
            <div class="tab-content active" id="position">
                <div class="card-header">
                    <h2 class="card-title">Stock Position Report</h2>
                    <div class="action-buttons">
                        <button class="btn btn-secondary" id="expandAllBtn" style="margin-right: 8px;">
                            <i class="fas fa-plus-square"></i> Expand All
                        </button>
                        <button class="btn btn-secondary" id="collapseAllBtn" style="margin-right: 8px;">
                            <i class="fas fa-minus-square"></i> Collapse All
                        </button>
                        <div class="dropdown" style="margin-right: 8px;">
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-eye"></i> Columns
                            </button>
                            <div class="dropdown-menu" style="padding: 12px; min-width: 200px;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-branch" checked style="width: 16px; height: 16px;">
                                    <span>Branch</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-inventory-type" checked style="width: 16px; height: 16px;">
                                    <span>Inventory Type</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-opening-balance" checked style="width: 16px; height: 16px;">
                                    <span>Opening Balance</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-qty-in" checked style="width: 16px; height: 16px;">
                                    <span>Total Qty In</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-qty-out" checked style="width: 16px; height: 16px;">
                                    <span>Total Qty Out</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-current-stock" checked style="width: 16px; height: 16px;">
                                    <span>Current Stock</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-unit-cost" checked style="width: 16px; height: 16px;">
                                    <span>Unit Cost</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                    <input type="checkbox" id="toggle-stock-value" checked style="width: 16px; height: 16px;">
                                    <span>Stock Value</span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="toggle-status" checked style="width: 16px; height: 16px;">
                                    <span>Status</span>
                                </label>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" onclick="printList()"><i class="fas fa-print"></i> Print List</a>
                                <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                                <a class="dropdown-item" href="#" onclick="exportToJSON()"><i class="fas fa-file-code"></i> Export JSON</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
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
                        <label class="form-label" for="distribution">Distribution</label>
                        <select class="form-control" id="distribution">
                            <option value="">All Distributions</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="stock-status">Inventory Status</label>
                        <select class="form-control" id="stock-status">
                            <option value="">All Status</option>
                            <option value="sellable">Sellable Stock</option>
                            <option value="damaged">Damaged Stock</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="inventory-type">Inventory Type</label>
                        <select class="form-control" id="inventory-type">
                            <option value="">All Inventory Types</option>
                        </select>
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
                        <label class="form-label" for="status">Stock Status</label>
                        <select class="form-control" id="status">
                            <option value="">All Statuses</option>
                            <option value="in-stock">In Stock</option>
                            <option value="low-stock">Low Stock</option>
                            <option value="out-of-stock">Out of Stock</option>
                            <option value="overstock">Overstock</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="stock-level-filter">Stock Level</label>
                        <select class="form-control" id="stock-level-filter">
                            <option value="">All Levels</option>
                            <option value="zero-or-less">Current Stock = 0 or Less</option>
                            <option value="greater-than-zero">Current Stock > 0</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="valuation-method">Valuation Method</label>
                        <select class="form-control" id="valuation-method">
                            <option value="AVCO">AVCO (Average Cost)</option>
                            <option value="FIFO">FIFO (First In First Out)</option>
                            <option value="LIFO">LIFO (Last In First Out)</option>
                            <option value="TRADE_PRICE">Market Value - Trade Price</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="show-base-units" style="width: 18px; height: 18px;">
                            <span>Show in Base Units</span>
                        </label>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                        <button class="btn btn-primary" id="applyPositionFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="btn btn-secondary" id="clearPositionFilters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <div id="trade-price-note" style="display: none; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-left: 4px solid #f39c12; padding: 16px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.15);">
                        <div style="display: flex; align-items: start; gap: 12px;">
                            <i class="fas fa-info-circle" style="color: #f39c12; font-size: 20px; margin-top: 2px;"></i>
                            <div>
                                <div style="font-weight: 600; color: #856404; margin-bottom: 4px; font-size: 14px;">Market Value Estimation</div>
                                <div style="color: #856404; font-size: 13px; line-height: 1.6;">
                                    This report shows estimated market value based on trade price. It is not an accounting valuation and should not be used for financial reporting.
                                </div>
                            </div>
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="col-branch">Branch</th>
                                <th class="col-inventory-type">Inventory Type</th>
                                <th class="col-opening-balance">Opening Balance</th>
                                <th class="col-qty-in">Total Qty In</th>
                                <th class="col-qty-out">Total Qty Out</th>
                                <th class="col-current-stock">Current Stock</th>
                                <th class="col-unit-cost">Unit Cost</th>
                                <th class="col-stock-value">Stock Value</th>
                                <th class="col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span id="position-pagination-info">Showing 0 - 0 of 0 entries</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="btn btn-secondary" id="position-prev-btn" disabled>Previous</button>
                            <span id="position-page-info">Page 1 of 1</span>
                            <button class="btn btn-secondary" id="position-next-btn" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item-wise Ledger Tab -->
            <div class="tab-content" id="item-ledger">
                <div class="card-header">
                    <h2 class="card-title">Item-wise Stock Ledger</h2>
                    <div class="action-buttons">
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" onclick="printList()"><i class="fas fa-print"></i> Print List</a>
                                <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                                <a class="dropdown-item" href="#" onclick="exportToJSON()"><i class="fas fa-file-code"></i> Export JSON</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="item">Item</label>
                        <input type="text" class="form-control" id="item" list="item-list" placeholder="Select Item">
                        <datalist id="item-list"></datalist>
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
                        <button class="btn btn-secondary" id="clearItemFilters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction</th>
                                <th>Reference</th>
                                <th>Inventory Item</th>
                                <th>Customer</th>
                                <th>Supplier</th>
                                <th>Qty Owned</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span id="item-pagination-info">Showing 0 - 0 of 0 entries</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="btn btn-secondary" id="item-prev-btn" disabled>Previous</button>
                            <span id="item-page-info">Page 1 of 1</span>
                            <button class="btn btn-secondary" id="item-next-btn" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branch-wise Ledger Tab -->
            <div class="tab-content" id="branch-ledger">
                <div class="card-header">
                    <h2 class="card-title">Branch-wise Stock Ledger</h2>
                    <div class="action-buttons">
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" onclick="printList()"><i class="fas fa-print"></i> Print List</a>
                                <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                                <a class="dropdown-item" href="#" onclick="exportToJSON()"><i class="fas fa-file-code"></i> Export JSON</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="branch-ledger">Branch</label>
                        <input type="text" class="form-control" id="branch-ledger" list="branch-ledger-list" placeholder="Select Branch">
                        <datalist id="branch-ledger-list"></datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="from-date-branch">From Date</label>
                        <input type="date" class="form-control" id="from-date-branch">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="to-date-branch">To Date</label>
                        <input type="date" class="form-control" id="to-date-branch">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                        <button class="btn btn-primary" id="applyBranchFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="btn btn-secondary" id="clearBranchFilters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Transaction</th>
                                <th>Qty In</th>
                                <th>Qty Out</th>
                                <th>Balance</th>
                                <th>Unit Cost</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span id="branch-pagination-info">Showing 0 - 0 of 0 entries</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="btn btn-secondary" id="branch-prev-btn" disabled>Previous</button>
                            <span id="branch-page-info">Page 1 of 1</span>
                            <button class="btn btn-secondary" id="branch-next-btn" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer-wise Ledger Tab -->
<div class="tab-content" id="customer-ledger">
    <div class="card-header">
        <h2 class="card-title">Customer-wise Stock Ledger</h2>
        <div class="action-buttons">
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                    <i class="fas fa-file-export"></i> Export
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#" onclick="printList()"><i class="fas fa-print"></i> Print List</a>
                    <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                    <a class="dropdown-item" href="#" onclick="exportToJSON()"><i class="fas fa-file-code"></i> Export JSON</a>
                </div>
            </div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="customer-ledger-input">Customer</label>
            <input type="text" class="form-control" id="customer-ledger-input" list="customer-ledger-list" placeholder="Select Customer">
            <datalist id="customer-ledger-list"></datalist>
        </div>
        <div class="form-group">
            <label class="form-label" for="from-date-customer">From Date</label>
            <input type="date" class="form-control" id="from-date-customer">
        </div>
        <div class="form-group">
            <label class="form-label" for="to-date-customer">To Date</label>
            <input type="date" class="form-control" id="to-date-customer">
        </div>
        <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
            <button class="btn btn-primary" id="applyCustomerFilters">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <button class="btn btn-secondary" id="clearCustomerFilters">
                <i class="fas fa-times"></i> Clear
            </button>
        </div>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Branch</th>
                    <th>Transaction</th>
                    <th>Qty In</th>
                    <th>Qty Out</th>
                    <th>Balance</th>
                    <th>Unit Cost</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <div class="pagination-container">
            <div class="pagination-info">
                <span id="customer-pagination-info">Showing 0 - 0 of 0 entries</span>
            </div>
            <div class="pagination-controls">
                <button class="btn btn-secondary" id="customer-prev-btn" disabled>Previous</button>
                <span id="customer-page-info">Page 1 of 1</span>
                <button class="btn btn-secondary" id="customer-next-btn" disabled>Next</button>
            </div>
        </div>
    </div>
</div>
            <!-- Detailed Ledger Tab -->
            <div class="tab-content" id="detailed-ledger">
                <div class="card-header">
                    <h2 class="card-title">Detailed Stock Ledger</h2>
                    <div class="action-buttons">
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" onclick="printList()"><i class="fas fa-print"></i> Print List</a>
                                <a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export To Excel</a>
                                <a class="dropdown-item" href="#" onclick="exportToJSON()"><i class="fas fa-file-code"></i> Export JSON</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="detailed-branch">Branch</label>
                        <input type="text" class="form-control" id="detailed-branch" list="detailed-branch-list" placeholder="All Branches">
                        <datalist id="detailed-branch-list"></datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="detailed-product">Product</label>
                        <input type="text" class="form-control" id="detailed-product" list="detailed-product-list" placeholder="All Products">
                        <datalist id="detailed-product-list"></datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="detailed-from-date">From Date</label>
                        <input type="date" class="form-control" id="detailed-from-date">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="detailed-to-date">To Date</label>
                        <input type="date" class="form-control" id="detailed-to-date">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 8px;">
                        <button class="btn btn-primary" id="applyDetailedFilters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="btn btn-secondary" id="clearDetailedFilters">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Branch</th>
                                <th>Transaction</th>
                                <th>Qty In</th>
                                <th>Qty Out</th>
                                <th>Balance</th>
                                <th>Unit Cost</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span id="detailed-pagination-info">Showing 0 - 0 of 0 entries</span>
                        </div>
                        <div class="pagination-controls">
                            <button class="btn btn-secondary" id="detailed-prev-btn" disabled>Previous</button>
                            <span id="detailed-page-info">Page 1 of 1</span>
                            <button class="btn btn-secondary" id="detailed-next-btn" disabled>Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../../../assets/js/inventory/stock_position/stock-position.js?v=<?= time() ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>