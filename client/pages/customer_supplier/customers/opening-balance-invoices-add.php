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
    <title>LedgerOne ERP - Opening Balance Invoices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customers/opening-balance-invoices-add.css">
</head>
<style>
.switch-wrapper {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: var(--surface-2, #f5f5f5);
    border-radius: 8px;
    margin-top: 10px;
}
.switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
    flex-shrink: 0;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #ccc;
    transition: .3s;
    border-radius: 24px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px; width: 18px;
    left: 3px; bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
.switch input:checked + .slider { background-color: #4CAF50; }
.switch input:checked + .slider:before { transform: translateX(22px); }
</style>

<body class="light-mode">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Opening Balance Invoices - Bulk Entry
                </h2>
                <a href="customer-list.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i>
                    Back to Customers
                </a>
            </div>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <span>Add multiple opening balance invoices at once. Opening Debit will be auto-calculated from invoice totals.</span>
            </div>
            <!-- NAYA TOGGLE -->
<div class="switch-wrapper">
    <div style="display:flex; align-items:center; gap:8px;">
        <i class="fas fa-user-tie"></i>
        <span>Auto-fill Sales Officer for new rows</span>
        <small id="autoFillStatusText" style="color: var(--text-muted, #888);"></small>
    </div>
    <label class="switch">
        <input type="checkbox" id="autoFillOfficerToggle">
        <span class="slider"></span>
    </label>
</div>

            <form id="openingInvoicesForm">
                <div class="table-wrapper">
                    <table class="invoices-table" id="invoicesTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">S.No</th>
                                <th style="width: 20%;"><i class="fas fa-user"></i> Customer</th>
                                <th style="width: 18%;"><i class="fas fa-sitemap"></i> Distribution</th>
                                <th style="width: 18%;"><i class="fas fa-user-tie"></i> Sales Officer</th>
                                <th style="width: 13%;"><i class="fas fa-hashtag"></i> Invoice Number</th>
                                <th style="width: 10%;"><i class="fas fa-calculator"></i> Debit (Dr)</th>
                                <th style="width: 10%;"><i class="fas fa-calculator"></i> Credit (Cr)</th>
                                <th style="width: 10%;"><i class="fas fa-calendar"></i> Invoice Date</th>
                                <th style="width: 8%;"><i class="fas fa-trash"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <!-- Rows will be added here -->
                        </tbody>
                    </table>
                </div>

                <div class="summary-section">
                    <div class="summary-item">
                        <label>Total Opening Debit:</label>
                        <span class="summary-value" id="totalDebit">0.00</span>
                    </div>
                    <div class="summary-item">
                        <label>Total Opening Credit:</label>
                        <span class="summary-value" id="totalCredit">0.00</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save All Invoices
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="history.back()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for delete confirmation -->
    <div id="deleteConfirmModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this row?</p>
            <div class="modal-actions">
                <button id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                <button id="cancelDeleteBtn" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/customer_supplier/customers/opening-balance-invoices-add.js"></script>
</body>

</html>
