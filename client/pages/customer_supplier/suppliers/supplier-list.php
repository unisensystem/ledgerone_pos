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
$currency_symbol = $currency['symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Suppliers List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/suppliers/supplier-list.css">
    <style>
        .edit-salesman-chip { display:inline-flex; align-items:center; gap:4px; background:var(--primary,#4f46e5); color:#fff; border-radius:999px; padding:2px 10px; font-size:13px; }
        .edit-salesman-chip .chip-remove { cursor:pointer; font-size:16px; line-height:1; opacity:.8; }
        .edit-salesman-chip .chip-remove:hover { opacity:1; }
        .edit-salesman-dropdown-item { padding:8px 12px; cursor:pointer; font-size:14px; }
        .edit-salesman-dropdown-item:hover { background:var(--surface-2,#f3f4f6); }
        .edit-salesman-dropdown-item.disabled { opacity:.4; pointer-events:none; }
    </style>
</head>

<body class="light-mode">
    <div class="container">
        <div class="header">
            <div class="header-actions">
                <a href="supplier-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Supplier
                </a>
            </div>
        </div>

        <div class="page-title">
            <h1>
                <i class="fas fa-users"></i>
                Suppliers Management
            </h1>
            <div class="pagination-info" id="paginationInfo">Showing 1-10 of 45 suppliers</div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value">45</div>
                <div class="stat-label">Total Suppliers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value">42</div>
                <div class="stat-label">Active Suppliers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-value">3</div>
                <div class="stat-label">Blacklisted</div>
            </div>
        </div>

        <div class="card">
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search suppliers...">
                </div>
                <div class="filter-options">
                    <select class="filter-select" id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                    <div class="dropdown">
                        <button class="btn btn-secondary" id="exportBtn">
                            <i class="fas fa-download"></i>
                            Export
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="exportDropdown">
                            <button class="dropdown-item" data-export="print">
                                <i class="fas fa-print"></i> Print List
                            </button>
                            <button class="dropdown-item" data-export="excel">
                                <i class="fas fa-file-excel"></i> Export to Excel
                            </button>
                            <button class="dropdown-item" data-export="json">
                                <i class="fas fa-code"></i> Export JSON
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Supplier Code</th>
                            <th>Brand Name</th>
                            <th>Supplier Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTable">
                        <!-- Supplier rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="tableInfo">Showing 1-10 of 45 suppliers</div>
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
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Supplier</h3>
                <button class="modal-close" id="editModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editSupplierForm">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group col-4">
                            <label class="required">Company</label>
                            <select id="editCompany" required>
                                <option value="">Select Company</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Supplier Code</label>
                            <input type="text" id="editSupplierCode" readonly>
                        </div>
                        <div class="form-group col-4">
                            <label>Salesman</label>
                            <div id="editSalesmanChipsContainer" style="border:1px solid var(--border-default,#ccc);border-radius:8px;padding:6px 8px;background:var(--input-bg,#fff);cursor:text;position:relative;">
                                <div id="editSalesmanChipsList" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:4px;"></div>
                                <input type="text" id="editSalesmanSearch" placeholder="Search salesman..." autocomplete="off" style="border:none;outline:none;background:transparent;width:100%;font-size:14px;padding:2px 4px;">
                            </div>
                            <div id="editSalesmanDropdown" style="display:none;z-index:9999;background:var(--surface-1,#fff);border:1px solid var(--border-default,#ccc);border-radius:8px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                            <input type="hidden" id="editSalesmanIds" value="">
                        </div>
                        <div class="form-group col-4">
                            <label class="required">Supplier Name</label>
                            <input type="text" id="editSupplierName" required>
                        </div>
                        <div class="form-group col-4">
                            <label>Brand Name</label>
                            <input type="text" id="editBrandName">
                        </div>
                        <div class="form-group col-12">
                            <label>Address</label>
                            <textarea id="editAddress"></textarea>
                        </div>
                        <div class="form-group col-6">
                            <label>Primary Phone</label>
                            <input type="text" id="editPrimaryPhone" maxlength="15">
                        </div>
                        <div class="form-group col-6">
                            <label>Secondary Phone</label>
                            <input type="text" id="editSecondaryPhone" maxlength="15">
                        </div>
                        <div class="form-group col-6">
                            <label>Email</label>
                            <input type="email" id="editEmail" maxlength="300">
                        </div>
                        <div class="form-group col-6">
                            <label>Identity Card</label>
                            <input type="text" id="editIdentityCard" maxlength="15">
                        </div>
                        <div class="form-group col-6">
                            <label>Opening Debit</label>
                            <input type="number" id="editOpeningDebit" step="0.01" min="0">
                        </div>
                        <div class="form-group col-6">
                            <label>Opening Credit</label>
                            <input type="number" id="editOpeningCredit" step="0.01" min="0">
                        </div>
                        <div class="form-group col-6">
                            <label>AIT %</label>
                            <input type="number" id="editAitPercent" step="0.01" min="0" max="100">
                        </div>
                        <div class="form-group col-6">
                            <label><i class="fas fa-project-diagram"></i> Project</label>
                            <select id="editProject">
                                <option value="">Select Project</option>
                            </select>
                        </div>
                        <div class="form-group col-12">
                            <label>Sub Accounts</label>
                            <div class="table-container">
                                <table id="editSubAccountsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">S#</th>
                                            <th>Sub Account Name</th>
                                            <th style="width: 150px;">Debit</th>
                                            <th style="width: 150px;">Credit</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="editSubAccountsBody">
                                        <tr>
                                            <td>1</td>
                                            <td><input type="text" class="sub-account-input" placeholder="Enter sub account name"></td>
                                            <td><input type="number" class="sub-account-debit" step="0.01" min="0" placeholder="0.00"></td>
                                            <td><input type="number" class="sub-account-credit" step="0.01" min="0" placeholder="0.00"></td>
                                            <td>
                                                <button type="button" class="btn-icon btn-add" title="Add Row"><i class="fas fa-plus"></i></button>
                                                <button type="button" class="btn-icon btn-remove" title="Remove Row"><i class="fas fa-minus"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="form-group col-12">
                            <div class="checkbox-group">
                                <input type="checkbox" id="editBlacklist">
                                <label for="editBlacklist">Blacklist Supplier</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="editCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSaveBtn">
                        <i class="fas fa-save"></i> Update Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Supplier Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-trash"></i> Delete Supplier</h3>
                <button class="modal-close" id="deleteModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 48px; color: var(--error); margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 style="margin-bottom: 16px; color: var(--heading);">Are you sure?</h4>
                    <p style="color: var(--body); margin-bottom: 24px;">
                        You are about to delete supplier <strong id="deleteSupplierName"></strong>.
                        This action cannot be undone.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="deleteCancelBtn">Cancel</button>
                <button type="button" class="btn" id="deleteConfirmBtn" style="background: var(--error); color: white;">
                    <i class="fas fa-trash"></i> Delete Supplier
                </button>
            </div>
        </div>
    </div>

    <!-- View Supplier Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Supplier Details</h3>
                <button class="modal-close" id="viewModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-4">
                        <label>Supplier Code</label>
                        <div class="view-field" id="viewSupplierCode"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Salesman</label>
                        <div class="view-field" id="viewSalesman"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Brand Name</label>
                        <div class="view-field" id="viewSupplierName"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Supplier Name</label>
                        <div class="view-field" id="viewBrandName"></div>
                    </div>
                    <div class="form-group col-12">
                        <label>Address</label>
                        <div class="view-field" id="viewAddress"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Primary Phone</label>
                        <div class="view-field" id="viewPrimaryPhone"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Secondary Phone</label>
                        <div class="view-field" id="viewSecondaryPhone"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Email</label>
                        <div class="view-field" id="viewEmail"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Identity Card</label>
                        <div class="view-field" id="viewIdentityCard"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Opening Debit</label>
                        <div class="view-field" id="viewOpeningDebit"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Opening Credit</label>
                        <div class="view-field" id="viewOpeningCredit"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>AIT %</label>
                        <div class="view-field" id="viewAitPercent"></div>
                    </div>
                    <div class="form-group col-6">
                        <label><i class="fas fa-project-diagram"></i> Project</label>
                        <div class="view-field" id="viewProject"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Current Balance</label>
                        <div class="view-field" id="viewCurrentBalance"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Status</label>
                        <div class="view-field" id="viewStatus"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Created Date</label>
                        <div class="view-field" id="viewCreatedAt"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Last Updated</label>
                        <div class="view-field" id="viewUpdatedAt"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="viewCloseBtn">Close</button>
                <button type="button" class="btn btn-primary" id="viewEditBtn">
                    <i class="fas fa-edit"></i> Edit Supplier
                </button>
            </div>
        </div>
    </div>

    <div class="notification" id="notification">
        <div class="notification-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title" id="notificationTitle">Success</div>
            <div class="notification-message" id="notificationMessage">Supplier deleted successfully!</div>
        </div>
        <button class="notification-close" id="notificationClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/customer_supplier/suppliers/supplier-list.js?v=<?php echo time(); ?>"></script>
</body>

</html>