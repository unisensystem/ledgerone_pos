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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate List Entry | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/master_setup/rate_list/list-add.css">
</head>
<body>
    <div class="container">
        <!-- Form Header -->
        <div class="form-header">
            <h1 class="form-title">Rate List Entry</h1>
        </div>
        
        <!-- Status Message Area -->
        <div id="statusMessage" class="status-message"></div>
        
        <!-- Main Section Card -->
        <div class="card">
            <h2 class="section-title">Main Section</h2>
            
            <div class="form-grid">
                <!-- Rate List Type -->
                <div class="form-field">
                    <label class="field-label">Rate List Type *</label>
                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="rateListType" value="customer" id="typeCustomer" checked>
                            <span>Customer</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="rateListType" value="supplier" id="typeSupplier">
                            <span>Supplier</span>
                        </label>
                    </div>
                </div>
                
                <!-- List Code -->
                <div class="form-field">
                    <label class="field-label" for="listCode">List Code *</label>
                    <input type="text" id="listCode" class="input" placeholder="Auto-generated" readonly>
                </div>
                
                <!-- List Name -->
                <div class="form-field">
                    <label class="field-label" for="listName">List Name *</label>
                    <input type="text" id="listName" class="input" placeholder="Enter list name">
                </div>
                
                <!-- Customer/Supplier Name (Multi-select) -->
                <div class="form-field" style="grid-column: span 3;">
                    <label class="field-label" id="entityLabel">Customer Name *</label>
                    <div class="multiselect-container">
                        <div class="multiselect" id="entitySelect">
                            <span id="entityPlaceholder">Select customer(s)</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="multiselect-dropdown" id="entityDropdown">
                            <!-- Options will be populated by JS -->
                        </div>
                        <div class="multiselect-values" id="selectedEntities">
                            <!-- Selected values will appear here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Items Table Card -->
        <div class="card">
            <h2 class="section-title">Items Table</h2>
            
            <div class="items-table-container">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 60px">S#</th>
                            <th>Product</th>
                            <th style="width: 120px">Sale Price</th>
                            <th style="width: 100px">Quantity</th>
                            <th style="width: 120px">FOC Quantity</th>
                            <th style="width: 140px">Trade Offer Amount</th>
                            <th style="width: 100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <!-- Table rows will be populated by JS -->
                    </tbody>
                </table>
            </div>
            
            <button id="addItemBtn" class="btn btn-ghost" style="margin-top: 16px;">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </div>
        
        <!-- Action Buttons -->
        <div class="button-group">
            <button id="resetBtn" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset List
            </button>
            <button id="postBtn" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Post Rate List
            </button>
        </div>
    </div>

    <script src="../../../assets/js/master_setup/rate_list/list-add.js?v=<?php echo time(); ?>"></script>
</body>
</html>