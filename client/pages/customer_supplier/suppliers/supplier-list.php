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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/suppliers/supplier-list.css">
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
                <i class="fas fa-truck"></i>
                Suppliers Management
            </h1>
            <div class="pagination-info" id="paginationInfo">Loading...</div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
                <div class="stat-value" id="statTotal">0</div>
                <div class="stat-label">Total Suppliers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value" id="statActive">0</div>
                <div class="stat-label">Active Suppliers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-value" id="statBlacklisted">0</div>
                <div class="stat-label">Blacklisted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-value" id="statPayables">0</div>
                <div class="stat-label">Total Payables</div>
            </div>
        </div>

        <div class="card">
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by any field...">
                </div>
                <div class="filter-options">
                    <select class="filter-select" id="rowsPerPage">
                        <option value="10">Show 10</option>
                        <option value="25">Show 25</option>
                        <option value="50">Show 50</option>
                        <option value="100">Show 100</option>
                        <option value="500">Show 500</option>
                        <option value="all">Show All</option>
                    </select>
                    <select class="filter-select" id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Sr#</th>
                            <th>Supplier Code</th>
                            <th>Brand Name</th>
                            <th>Category</th>
                            <th>Supplier Name</th>
                            <th>City</th>
                            <th>Phone</th>
                            <th>Credit Days</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suppliersTable">
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="tableInfo">Loading...</div>
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

    <!-- View Supplier Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Supplier Details</h3>
                <button class="modal-close" id="viewModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-4">
                        <label>Company</label>
                        <div class="view-field" id="viewCompany"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Supplier Code</label>
                        <div class="view-field" id="viewSupplierCode"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Supplier Name</label>
                        <div class="view-field" id="viewSupplierName"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Brand Name</label>
                        <div class="view-field" id="viewBrandName"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Supplier Category</label>
                        <div class="view-field" id="viewSupplierCategory"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>City</label>
                        <div class="view-field" id="viewCity"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Party Type</label>
                        <div class="view-field" id="viewPartyType"></div>
                    </div>
                    <div class="form-group col-12">
                        <label>Address</label>
                        <div class="view-field" id="viewAddress"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Primary Phone</label>
                        <div class="view-field" id="viewPrimaryPhone"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Secondary Phone</label>
                        <div class="view-field" id="viewSecondaryPhone"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Email</label>
                        <div class="view-field" id="viewEmail"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Identity Card</label>
                        <div class="view-field" id="viewIdentityCard"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Credit Days</label>
                        <div class="view-field" id="viewCreditDays"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>AIT %</label>
                        <div class="view-field" id="viewAitPercent"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Opening Debit</label>
                        <div class="view-field" id="viewOpeningDebit"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Opening Credit</label>
                        <div class="view-field" id="viewOpeningCredit"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Current Balance</label>
                        <div class="view-field" id="viewCurrentBalance"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Status</label>
                        <div class="view-field" id="viewStatus"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Created Date</label>
                        <div class="view-field" id="viewCreatedAt"></div>
                    </div>

                    <!-- Sub Accounts -->
                    <div class="form-group col-12">
                        <label>Sub Accounts</label>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
                                <thead style="background: var(--surface-2);">
                                    <tr>
                                        <th style="padding: 12px; border: 1px solid var(--border-default);">S#</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default);">Sub Account Name</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default);">Debit (Dr)</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default);">Credit (Cr)</th>
                                    </tr>
                                </thead>
                                <tbody id="viewSubAccountsTableBody">
                                    <tr><td colspan="4" style="padding:12px; text-align:center; color: var(--subtext);">No sub accounts</td></tr>
                                </tbody>
                            </table>
                        </div>
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

    <!-- Edit Supplier Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Supplier</h3>
                <button class="modal-close" id="editModalClose"><i class="fas fa-times"></i></button>
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
                            <label class="required">Supplier Name</label>
                            <input type="text" id="editSupplierName" required>
                        </div>
                        <div class="form-group col-4">
                            <label>Brand Name</label>
                            <input type="text" id="editBrandName" maxlength="200">
                        </div>
                        <div class="form-group col-4">
                            <label>Supplier Category</label>
                            <select id="editSupplierCategory">
                                <option value="">Select Supplier Category</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>City</label>
                            <input type="text" id="editCity" maxlength="100">
                        </div>
                        <div class="form-group col-4">
                            <label>Party Type</label>
                            <select id="editPartyType">
                                <option value="">Select Party Type</option>
                                <option value="manufacturer">Manufacturer</option>
                                <option value="distributor">Distributor</option>
                                <option value="trader">Trader</option>
                                <option value="service_provider">Service Provider</option>
                            </select>
                        </div>
                        <div class="form-group col-12">
                            <label>Address</label>
                            <textarea id="editAddress"></textarea>
                        </div>
                        <div class="form-group col-4">
                            <label>Primary Phone</label>
                            <input type="text" id="editPrimaryPhone" maxlength="15">
                        </div>
                        <div class="form-group col-4">
                            <label>Secondary Phone</label>
                            <input type="text" id="editSecondaryPhone" maxlength="15">
                        </div>
                        <div class="form-group col-4">
                            <label>Email</label>
                            <input type="email" id="editEmail" maxlength="300">
                        </div>
                        <div class="form-group col-4">
                            <label>Identity Card</label>
                            <input type="text" id="editIdentityCard" maxlength="15">
                        </div>
                        <div class="form-group col-4">
                            <label>Salesman</label>
                            <select id="editSalesman">
                                <option value="">Select Salesman</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Credit Days</label>
                            <input type="number" id="editCreditDays" min="0">
                        </div>
                        <div class="form-group col-4">
                            <label>AIT %</label>
                            <input type="number" id="editAitPercent" step="0.01" min="0" max="100">
                        </div>
                        <div class="form-group col-4">
                            <label>Opening Debit</label>
                            <input type="number" id="editOpeningDebit" step="0.01" min="0">
                        </div>
                        <div class="form-group col-4">
                            <label>Opening Credit</label>
                            <input type="number" id="editOpeningCredit" step="0.01" min="0">
                        </div>

                        <!-- Sub Accounts -->
                        <div class="form-group col-12">
                            <label>Sub Accounts</label>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
                                    <thead style="background: var(--surface-2);">
                                        <tr>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); width:60px;">S#</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default);">Sub Account Name</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); width:150px;">Debit (Dr)</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); width:150px;">Credit (Cr)</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); width:80px; text-align:center;">Del</th>
                                        </tr>
                                    </thead>
                                    <tbody id="editSubAccountsTableBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" style="padding: 12px; border: 1px solid var(--border-default);">
                                                <button type="button" class="btn btn-secondary btn-sm" id="editAddSubAccountBtn">
                                                    <i class="fas fa-plus"></i> Add Sub Account
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="form-group col-4">
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
                <button class="modal-close" id="deleteModalClose"><i class="fas fa-times"></i></button>
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

    <div class="notification" id="notification">
        <div class="notification-icon"><i class="fas fa-check"></i></div>
        <div class="notification-content">
            <div class="notification-title" id="notificationTitle">Success</div>
            <div class="notification-message" id="notificationMessage"></div>
        </div>
        <button class="notification-close" id="notificationClose"><i class="fas fa-times"></i></button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/customer_supplier/suppliers/supplier-list.js"></script>
</body>
</html>