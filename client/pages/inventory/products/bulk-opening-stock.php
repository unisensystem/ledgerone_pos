<?php
require_once '../../../../includes/dashboard.php';
require_once '../../../../includes/connection.php';

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

$tenant_id = $_SESSION['tenant_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;

if (!$tenant_id || !$pdo) {
    die('Database connection error. Please contact administrator.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Bulk Opening Stock</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/products/product-add.css">
    <style>
        .container {
            max-width: 1200px;
        }
        
        .bulk-stock-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        
        .bulk-stock-table thead {
            background-color: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }
        
        .bulk-stock-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
            border-right: 1px solid #ddd;
        }
        
        .bulk-stock-table th:last-child {
            border-right: none;
        }
        
        .bulk-stock-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        .bulk-stock-table tbody tr:hover {
            background-color: #fafafa;
        }
        
        .bulk-stock-table td {
            padding: 12px;
            border-right: 1px solid #eee;
            vertical-align: middle;
        }
        
        .bulk-stock-table td:last-child {
            border-right: none;
        }
        
        .sno-column {
            width: 50px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }
        
        .product-column {
            width: 25%;
        }
        
        .branch-column {
            width: 20%;
        }
        
        .qty-column {
            width: 15%;
        }
        
        .price-column {
            width: 15%;
        }
        
        .action-column {
            width: 10%;
            text-align: center;
        }
        
        .bulk-stock-table input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }
        
        .bulk-stock-table input:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
        }
        
        .bulk-stock-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }
        
        .bulk-stock-table select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
        }
        
        .custom-dropdown {
            position: relative;
        }
        
        .custom-dropdown input[type="text"] {
            width: 100%;
        }
        
        .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .dropdown-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        .dropdown-item:hover {
            background-color: #f5f5f5;
        }
        
        .dropdown-item.selected {
            background-color: #e3f2fd;
            color: #1976d2;
            font-weight: 600;
        }
        
        .dropdown-item.disabled {
            color: #ccc;
            cursor: not-allowed;
            background-color: #fafafa;
        }
        
        .add-row-section {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        
        .summary-section {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #eee;
        }
        
        .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .summary-item label {
            font-weight: 500;
            color: #666;
            font-size: 13px;
        }
        
        .summary-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .remove-btn-small {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }

        .remove-btn-small:hover {
            background-color: #d32f2f;
        }

        table.bulk-stock-table input[type="hidden"] {
            display: none;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-message p {
            font-size: 14px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h1 style="margin-bottom: 0;">Bulk Branch-wise Opening Stock</h1>
            <a href="product-list.php" class="btn btn-secondary" style="height: 36px; line-height: 36px; text-decoration: none; display: inline-flex; align-items: center;">
                ← Back to Products
            </a>
        </div>
        <p class="form-description">Add opening stock for multiple products across branches in a single view</p>
        
        <form id="bulkOpeningStockForm" class="card">
            <!-- Opening Stock Entries Table -->
            <div style="margin-top: 20px;">
                <h3 class="subsection-title">Opening Stock Entries</h3>
                <div class="stock-entries" id="stockEntries">
                    <!-- Rows will be added here dynamically -->
                </div>

                <!-- Add Another Row Button -->
                <button type="button" class="btn btn-secondary" id="addRowBtn" style="margin-top: 20px;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 6px;">
                        <path d="M8 3.33333V12.6667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3.33325 8H12.6666" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Add Another Row
                </button>
            </div>

            <!-- Summary Section -->
            <div class="total-stock-summary" style="margin-top: 30px; margin-bottom: 30px;">
                <h4 class="summary-title">Summary</h4>
                <div class="summary-row">
                    <div class="summary-item">
                        <label>Total Entries</label>
                        <div class="summary-value" id="totalEntries">0</div>
                    </div>
                    <div class="summary-item">
                        <label>Total Quantity</label>
                        <div class="summary-value" id="totalQty">0.00</div>
                    </div>
                    <div class="summary-item">
                        <label>Total Value</label>
                        <div class="summary-value" id="totalValue">0.00</div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-secondary" onclick="location.href='product-list.php'">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Save Opening Stock</button>
            </div>
        </form>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast" style="display: none; position: fixed; bottom: 20px; right: 20px; padding: 15px 20px; border-radius: 4px; color: white; z-index: 2000;"></div>

    <!-- Toast Notification -->
    <div id="toast" class="toast" style="display: none; position: fixed; bottom: 20px; right: 20px; padding: 15px 20px; border-radius: 4px; color: white; z-index: 2000;"></div>

    <script src="../../../assets/js/inventory/products/bulk-opening-stock-table.js"></script>
</body>
</html>

