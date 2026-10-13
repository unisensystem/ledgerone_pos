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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FuelingSys ERP | Meter Opening Readings</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/meter_invoice/invoice-list.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <div class="logo-icon">FS</div>
                <div class="logo-text">FuelingSys ERP</div>
            </div>
            <div class="header-actions">
                <!-- Placeholder for user menu or other actions -->
            </div>
        </header>
        
        <main>
            <h1 class="page-title">Meter Opening Readings</h1>
            <p class="page-description">View and manage all meter opening reading records</p>
            
            <div class="list-header">
                <div class="list-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by invoice, branch, or station...">
                    </div>
                </div>
                <button class="btn btn-primary" id="addReadingBtn">
                    <i class="fas fa-plus"></i>
                    <span>Add New Reading</span>
                </button>
            </div>
            
            <div class="filters" id="filtersSection">
                <div class="filter-group">
                    <div class="filter-label">Branch</div>
                    <select class="filter-select" id="branchFilter">
                        <option value="">All Branches</option>
                        <option value="1">Downtown Branch</option>
                        <option value="2">Westside Station</option>
                        <option value="3">Eastgate Fuel Center</option>
                        <option value="4">Northpoint Outlet</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <div class="filter-label">Station</div>
                    <select class="filter-select" id="stationFilter">
                        <option value="">All Stations</option>
                        <option value="1">Station A - Main Pumps</option>
                        <option value="2">Station B - Express Lane</option>
                        <option value="3">Station C - Truck Stop</option>
                        <option value="4">Station D - Premium Fuel</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <div class="filter-label">Product</div>
                    <select class="filter-select" id="productFilter">
                        <option value="">All Products</option>
                        <option value="1">Regular Unleaded</option>
                        <option value="2">Premium Unleaded</option>
                        <option value="3">Super Unleaded</option>
                        <option value="4">Diesel</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <div class="filter-label">Date Range</div>
                    <select class="filter-select" id="dateFilter">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <div class="filter-label">Status</div>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
            </div>
            
            <div class="list-card">
                <div class="table-container">
                    <table class="readings-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Branch</th>
                                <th>Station</th>
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Rate</th>
                                <th>Opening Reading</th>
                                <th>Closing Reading</th>
                                <th>Qty Sold</th>
                                <th>Total Revenue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="readingsTableBody">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination">
                    <div class="pagination-info" id="paginationInfo">Showing 1 to 10 of 50 entries</div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="prevPage">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="pagination-btn" id="nextPage">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Complete Invoice Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Complete Invoice</h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body">
                <form id="completeForm">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" id="modalDate" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rate / <span id="unitName">Unit</span></label>
                        <input type="number" id="modalRate" class="form-input" step="0.001" min="0" placeholder="0.000" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Closing Reading</label>
                        <input type="number" id="modalClosingReading" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    
                    <div class="totals-section">
                        <div class="total-item">
                            <span>Total Stock Available:</span>
                            <span><span id="totalStock">Loading...</span> <span id="stockUnit"></span></span>
                        </div>
                        <div class="total-item">
                            <span>Total Qty Dispensed / Sold:</span>
                            <span><span id="totalQty">0.00</span> <span id="qtyUnit"></span></span>
                        </div>
                        <div class="total-item">
                            <span>Total Revenue Generated:</span>
                            <span id="totalRevenue">0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label class="form-label">Revenue Splitting</label>
                        <table class="split-table">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="splitTableBody">
                                <tr>
                                    <td>
                                        <select class="form-input split-payment">
                                            <option value="">Select Payment Method</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-input split-amount" step="0.01" min="0" placeholder="0.00">
                                    </td>
                                    <td>
                                        <button type="button" class="action-btn add-split"><i class="fas fa-plus"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td><strong>Total Split:</strong></td>
                                    <td><strong id="totalSplit">0.00</strong></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Cash:</strong></td>
                                    <td><strong id="totalCash">0.00</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="form-error" id="splitError"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="modalCancel">Close</button>
                <button type="submit" form="completeForm" class="btn btn-primary" id="completeBtn">Complete Invoice</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Invoice</h3>
                <button class="modal-close" id="deleteModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this invoice? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="deleteCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmDelete" style="background-color: var(--error);">Delete</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Invoice Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Invoice</h3>
                <button class="modal-close" id="editModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" id="editDate" class="form-input" required>
                    </div>
                    

                    
                    <div class="form-group">
                        <label class="form-label">Opening Reading</label>
                        <input type="number" id="editOpeningReading" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rate / <span id="editUnitName">Unit</span></label>
                        <input type="number" id="editRate" class="form-input" step="0.001" min="0" placeholder="0.000" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Closing Reading</label>
                        <input type="number" id="editClosingReading" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                    
                    <div class="totals-section">
                        <div class="total-item">
                            <span>Total Qty Dispensed / Sold:</span>
                            <span><span id="editTotalQty">0.00</span> <span id="editQtyUnit"></span></span>
                        </div>
                        <div class="total-item">
                            <span>Total Revenue Generated:</span>
                            <span id="editTotalRevenue">0.00</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="editCancel">Cancel</button>
                <button type="submit" form="editForm" class="btn btn-primary" id="updateBtn">Update Invoice</button>
            </div>
        </div>
    </div>
    
    <div id="notification" class="notification"></div>
    
    <script src="../../../assets/js/sale/meter_invoice/invoice-list.js"></script>
</body>
</html>