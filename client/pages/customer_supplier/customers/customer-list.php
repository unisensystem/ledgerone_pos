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
    <title>LedgerOne ERP - Customers List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customers/customer-list.css">
</head>
<body class="light-mode">
    <div class="container">
        <div class="header">
            <div class="header-actions">
                <a href="customer-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Customer
                </a>
                <a href="opening-balance-invoices-add.php" class="btn btn-success">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Add Opening Invoices
                </a>
            </div>
        </div>

        <div class="page-title">
            <h1>
                <i class="fas fa-users"></i>
                Customers Management
            </h1>
            <div class="pagination-info" id="paginationInfo">Showing 1-10 of 45 customers</div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value">45</div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value">42</div>
                <div class="stat-label">Active Customers</div>
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
                            <th>Customer Code</th>
                            <th>Customer Name</th>
                            <th>Customer Group</th>
                            <th>Customer Category</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTable">
                        <!-- Customer rows will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="tableInfo">Showing 1-10 of 45 customers</div>
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

    <!-- Edit Customer Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Customer</h3>
                <button class="modal-close" id="editModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editCustomerForm">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group col-4">
                            <label class="required">Company</label>
                            <select id="editCompany" required>
                                <option value="">Select Company</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Customer Code</label>
                            <input type="text" id="editCustomerCode" readonly>
                        </div>
                        <div class="form-group col-4">
                            <label>Customer Group</label>
                            <select id="editCustomerType">
                                <option value="">Select Customer Group</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Customer Category</label>
                            <select id="editCustomerCategory">
                                <option value="">Select Customer Category</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Brand Name</label>
                            <select id="editBrandName">
                                <option value="">Select Brand</option>
                            </select>
                        </div>
                        <div class="form-group col-12">
                            <label class="required">Customer Name</label>
                            <input type="text" id="editCustomerName" required>
                        </div>
                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="editOutStation">
                                <label for="editOutStation">Out Station</label>
                            </div>
                        </div>
                        <div class="form-group col-12">
                            <label>Address</label>
                            <textarea id="editAddress"></textarea>
                        </div>
                        <div class="form-group col-6">
                            <label>P.O. Box No</label>
                            <input type="text" id="editPoBoxNo" maxlength="50">
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
                            <label>Sales Officer</label>
                            <select id="editSalesOfficer">
                                <option value="">Select Sales Officer</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label>Supplier Man</label>
                            <select id="editSupplierMan">
                                <option value="">Select Supplier Man</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label><i class="fas fa-project-diagram"></i> Project</label>
                            <select id="editProject">
                                <option value="">Select Project</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Country</label>
                            <select id="editCountry">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>Region</label>
                            <select id="editRegion" disabled>
                                <option value="">Select Region</option>
                            </select>
                        </div>
                        <div class="form-group col-4">
                            <label>City</label>
                            <select id="editCity" disabled>
                                <option value="">Select City</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label>City Zone</label>
                            <select id="editCityZone" disabled>
                                <option value="">Select City Zone</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label>Area</label>
                            <select id="editArea" disabled>
                                <option value="">Select Area</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="editIsSalesTaxRegistered">
                                <label for="editIsSalesTaxRegistered">Is Sales Tax Registered?</label>
                            </div>
                        </div>
                        <div class="form-group col-6" id="editStrnGroup" style="display: none;">
                            <label>STRN</label>
                            <input type="text" id="editStrn" maxlength="50">
                        </div>
                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="editIsFiler">
                                <label for="editIsFiler">Is Filer?</label>
                            </div>
                        </div>
                        <div class="form-group col-6" id="editNtnGroup" style="display: none;">
                            <label>NTN</label>
                            <input type="text" id="editNtn" maxlength="50">
                        </div>
                        <div class="form-group col-6">
                            <label>Advance Income Tax %</label>
                            <input type="number" id="editAdvanceIncomeTax" step="0.01" min="0" max="100">
                        </div>
                        <div class="form-group col-6">
                            <label>Default Discount %</label>
                            <input type="number" id="editDefaultDiscount" step="0.01" min="0" max="100">
                        </div>
                        <div class="form-group col-6">
                            <label>Credit Limit</label>
                            <input type="number" id="editBalanceLimit" step="0.01" min="0">
                        </div>
                        <div class="form-group col-6">
                            <label>Credit Period Limit (Days)</label>
                            <input type="number" id="editBalancePeriodLimit" min="0">
                        </div>
                        <div class="form-group col-6">
                            <label>Opening Debit</label>
                            <input type="number" id="editOpeningDebit" step="0.01" min="0" readonly>
                        </div>
                        <div class="form-group col-6">
                            <label>Opening Credit</label>
                            <input type="number" id="editOpeningCredit" step="0.01" min="0">
                        </div>
                        
                        <!-- Opening Balance Invoices Section -->
                        <div class="form-group col-12" id="editOpeningInvoicesSection">
                            <label>Opening Balance Invoices</label>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
                                    <thead style="background: var(--surface-2);">
                                        <tr>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Distribution</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Sales Officer</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Invoice Number</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Debit (Dr)</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Invoice Date</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: center; width: 80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="editInvoicesTableBody">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6" style="padding: 12px; border: 1px solid var(--border-default);">
                                                <button type="button" class="btn btn-secondary btn-sm" id="editAddInvoiceBtn">
                                                    <i class="fas fa-plus"></i> Add Invoice
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Sub Accounts Section -->
                        <div class="form-group col-12">
                            <label>Sub Accounts</label>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
                                    <thead style="background: var(--surface-2);">
                                        <tr>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; width: 80px;">S#</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Sub Account Name</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; width: 150px;">Debit (Dr)</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; width: 150px;">Credit (Cr)</th>
                                            <th style="padding: 12px; border: 1px solid var(--border-default); text-align: center; width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="editSubAccountsTableBody">
                                    </tbody>
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
                        
                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="editIsWholesaler">
                                <label for="editIsWholesaler">Is Wholesaler?</label>
                            </div>
                        </div>
                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="editBlacklist">
                                <label for="editBlacklist">Blacklist Customer</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="editCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSaveBtn">
                        <i class="fas fa-save"></i> Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Customer Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-trash"></i> Delete Customer</h3>
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
                        You are about to delete customer <strong id="deleteCustomerName"></strong>. 
                        This action cannot be undone.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="deleteCancelBtn">Cancel</button>
                <button type="button" class="btn" id="deleteConfirmBtn" style="background: var(--error); color: white;">
                    <i class="fas fa-trash"></i> Delete Customer
                </button>
            </div>
        </div>
    </div>

    <!-- View Customer Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Customer Details</h3>
                <button class="modal-close" id="viewModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group col-4">
                        <label>Company</label>
                        <div class="view-field" id="viewCompany"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Customer Code</label>
                        <div class="view-field" id="viewCustomerCode"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Customer Group</label>
                        <div class="view-field" id="viewCustomerType"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Customer Category</label>
                        <div class="view-field" id="viewCustomerCategory"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Brand Name</label>
                        <div class="view-field" id="viewBrandName"></div>
                    </div>
                    <div class="form-group col-8">
                        <label>Customer Name</label>
                        <div class="view-field" id="viewCustomerName"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Out Station</label>
                        <div class="view-field" id="viewOutStation"></div>
                    </div>
                    <div class="form-group col-12">
                        <label>Address</label>
                        <div class="view-field" id="viewAddress"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>P.O. Box No</label>
                        <div class="view-field" id="viewPoBoxNo"></div>
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
                        <label>Sales Officer</label>
                        <div class="view-field" id="viewSalesOfficer"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Supplier Man</label>
                        <div class="view-field" id="viewSupplierMan"></div>
                    </div>
                    <div class="form-group col-6">
                        <label><i class="fas fa-project-diagram"></i> Project</label>
                        <div class="view-field" id="viewProject"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Country</label>
                        <div class="view-field" id="viewCountry"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>Region</label>
                        <div class="view-field" id="viewRegion"></div>
                    </div>
                    <div class="form-group col-4">
                        <label>City</label>
                        <div class="view-field" id="viewCity"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>City Zone</label>
                        <div class="view-field" id="viewCityZone"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Area</label>
                        <div class="view-field" id="viewArea"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Sales Tax Registered</label>
                        <div class="view-field" id="viewIsSalesTaxRegistered"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>STRN</label>
                        <div class="view-field" id="viewStrn"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Is Filer</label>
                        <div class="view-field" id="viewIsFiler"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>NTN</label>
                        <div class="view-field" id="viewNtn"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Advance Income Tax %</label>
                        <div class="view-field" id="viewAdvanceIncomeTax"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Default Discount %</label>
                        <div class="view-field" id="viewDefaultDiscount"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Credit Limit</label>
                        <div class="view-field" id="viewBalanceLimit"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Credit Period Limit (Days)</label>
                        <div class="view-field" id="viewBalancePeriodLimit"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Opening Debit</label>
                        <div class="view-field" id="viewOpeningDebit"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Opening Credit</label>
                        <div class="view-field" id="viewOpeningCredit"></div>
                    </div>
                    
                    <!-- Opening Balance Invoices Section -->
                    <div class="form-group col-12" id="viewOpeningInvoicesSection">
                        <label>Opening Balance Invoices</label>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
                                <thead style="background: var(--surface-2);">
                                    <tr>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Distribution</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Sales Officer</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Invoice Number</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Debit (Dr)</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Invoice Date</th>
                                    </tr>
                                </thead>
                                <tbody id="viewInvoicesTableBody">
                                    <tr>
                                        <td colspan="5" style="padding: 12px; border: 1px solid var(--border-default); text-align: center; color: var(--subtext);">No invoices</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Sub Accounts Section -->
                    <div class="form-group col-12">
                        <label>Sub Accounts</label>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
                                <thead style="background: var(--surface-2);">
                                    <tr>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; width: 80px;">S#</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left;">Sub Account Name</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; width: 150px;">Debit (Dr)</th>
                                        <th style="padding: 12px; border: 1px solid var(--border-default); text-align: left; width: 150px;">Credit (Cr)</th>
                                    </tr>
                                </thead>
                                <tbody id="viewSubAccountsTableBody">
                                    <tr>
                                        <td colspan="4" style="padding: 12px; border: 1px solid var(--border-default); text-align: center; color: var(--subtext);">No sub accounts</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="form-group col-6">
                        <label>Current Balance</label>
                        <div class="view-field" id="viewCurrentBalance"></div>
                    </div>
                    <div class="form-group col-6">
                        <label>Is Wholesaler</label>
                        <div class="view-field" id="viewIsWholesaler"></div>
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
                    <i class="fas fa-edit"></i> Edit Customer
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
            <div class="notification-message" id="notificationMessage">Customer deleted successfully!</div>
        </div>
        <button class="notification-close" id="notificationClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
    </script>
    <script src="../../../assets/js/customer_supplier/customers/customer-list.js"></script>
</body>
</html>