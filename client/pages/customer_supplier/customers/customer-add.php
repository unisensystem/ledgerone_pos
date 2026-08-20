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
    <title>LedgerOne ERP - Customer Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customers/customer-add.css">
</head>

<body class="light-mode">
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--border-default);">
                <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;">
                    <i class="fas fa-user-plus"></i>
                    Customer Entry
                </h2>
                <button type="button" id="customizeFieldsBtn" class="btn btn-secondary btn-sm" style="padding: 0 16px; height: 40px;">
                    <i class="fas fa-sliders-h"></i>
                    Customize Fields
                </button>
            </div>
            <form id="customerForm">
                <div class="form-grid">
                    <!-- Basic Information Section -->
                    <div class="form-section" id="basicInfoSection">
                        <h3 class="section-title">
                            <i class="fas fa-id-card"></i>
                            Basic Information
                        </h3>

                        <div class="form-group col-4">
                            <label for="company" class="required">
                                <i class="fas fa-building"></i>
                                Company
                            </label>
                            <select id="company" required>
                                <option value="">Select Company</option>
                            </select>
                            <div class="error-text" id="companyError" style="display: none;">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Company is required</span>
                            </div>
                        </div>

                        <div class="form-group col-4">
                            <label for="customerCode" class="required">
                                <i class="fas fa-hashtag"></i>
                                Customer Code
                            </label>
                            <input type="text" id="customerCode" readonly value="Auto Generated">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Automatically generated
                            </div>
                        </div>

                        <div class="form-group col-3">
                            <label for="customerGroup">
                                <i class="fas fa-tag"></i>
                                Customer Group
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <select id="customerGroup" style="flex: 1;">
                                    <option value="">Select Customer Group</option>
                                </select>
                                <button type="button" class="btn btn-secondary btn-sm" id="manageTypesBtn" style="padding: 0 16px;">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group col-3">
                            <label for="customerCategory">
                                <i class="fas fa-folder"></i>
                                Customer Category
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <select id="customerCategory" style="flex: 1;">
                                    <option value="">Select Customer Category</option>
                                </select>
                                <button type="button" class="btn btn-secondary btn-sm" id="manageCategoriesBtn" style="padding: 0 16px;">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group col-3">
                            <label for="brandName">
                                <i class="fas fa-tag"></i>
                                Brand Name
                            </label>
                            <select id="brandName">
                                <option value="">Select Brand</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="customerName" class="required">
                                <i class="fas fa-user"></i>
                                Customer Name
                            </label>
                            <input type="text" id="customerName" required placeholder="Enter customer full name">
                            <div class="error-text" id="customerNameError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Customer name is required</span>
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="outStation">
                                <label for="outStation">
                                    <i class="fas fa-road"></i>
                                    Out Station
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section" id="contactInfoSection">
                        <h3 class="section-title">
                            <i class="fas fa-address-book"></i>
                            Contact Information
                        </h3>
                        <!-- Add this BEFORE the address form-group -->
<div class="form-group col-12">
    <label for="shopkeeperName">
        <i class="fas fa-store"></i>
        Shopkeeper Name
    </label>
    <input type="text" id="shopkeeperName" placeholder="Enter shopkeeper name">
</div>

                        <div class="form-group col-12">
                            <label for="address">
                                <i class="fas fa-map-marker-alt"></i>
                                Address
                            </label>
                            <textarea id="address" placeholder="Enter complete customer address"></textarea>
                        </div>

                        <div class="form-group col-6">
                            <label for="poBoxNo">
                                <i class="fas fa-mailbox"></i>
                                P.O. Box No
                            </label>
                            <input type="text" id="poBoxNo" maxlength="50" placeholder="e.g., P.O. Box 12345">
                        </div>

                        <div class="form-group col-6">
                            <label for="primaryPhone">
                                <i class="fas fa-phone"></i>
                                Primary Phone
                            </label>
                            <input type="text" id="primaryPhone" maxlength="15" placeholder="e.g., 1234567890">
                            <div class="error-text" id="primaryPhoneError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Phone can only contain numbers (max 15 digits)</span>
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="secondaryPhone">
                                <i class="fas fa-phone-alt"></i>
                                Secondary Phone
                            </label>
                            <input type="text" id="secondaryPhone" maxlength="15" placeholder="Optional">
                            <div class="error-text" id="secondaryPhoneError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Phone can only contain numbers (max 15 digits)</span>
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input type="email" id="email" maxlength="300" placeholder="customer@example.com">
                            <div class="error-text" id="emailError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Please enter a valid email address</span>
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="identityCard">
                                <i class="fas fa-id-card"></i>
                                Identity Card No
                            </label>
                            <input type="text" id="identityCard" maxlength="15" placeholder="XXXXX-YYYYYYY-Z">
                            <div class="error-text" id="identityCardError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Identity card must be in the format XXXXX-YYYYYYY-Z</span>
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="licenseNo">
                                <i class="fas fa-certificate"></i>
                                License #
                            </label>
                            <input type="text" id="licenseNo" maxlength="100" placeholder="Enter license number">
                        </div>
                    </div>

                    <!-- Sales Officer Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-tie"></i>
                            Sales Information
                        </h3>

                        <div class="form-group col-6">
                            <label for="salesOfficer">
                                <i class="fas fa-user-tie"></i>
                                Associated Sales Officer
                            </label>
                            <select id="salesOfficer">
                                <option value="">Select Sales Officer</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="supplierMan">
                                <i class="fas fa-user-tag"></i>
                                Supplier Man
                            </label>
                            <select id="supplierMan">
                                <option value="">Select Supplier Man</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="project">
                                <i class="fas fa-project-diagram"></i>
                                Project
                            </label>
                            <select id="project">
                                <option value="">Select Project</option>
                            </select>
                        </div>
                    </div>

                    <!-- Territory Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marked-alt"></i>
                            Territory
                        </h3>

                        <div class="form-group col-4">
                            <label for="country">
                                <i class="fas fa-globe"></i>
                                Country
                            </label>
                            <select id="country">
                                <option value="">Select Country</option>
                            </select>
                        </div>

                        <div class="form-group col-4">
                            <label for="region">
                                <i class="fas fa-map"></i>
                                Region
                            </label>
                            <select id="region">
                                <option value="">Select Region</option>
                            </select>
                        </div>

                        <div class="form-group col-4">
                            <label for="city">
                                <i class="fas fa-city"></i>
                                City
                            </label>
                            <select id="city">
                                <option value="">Select City</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="cityZone">
                                <i class="fas fa-map-pin"></i>
                                City Zone
                            </label>
                            <select id="cityZone">
                                <option value="">Select City Zone</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="area">
                                <i class="fas fa-map-marker-alt"></i>
                                Area
                            </label>
                            <select id="area">
                                <option value="">Select Area</option>
                            </select>
                        </div>
                    </div>

                    <!-- Taxation Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Taxation
                        </h3>

                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="isSalesTaxRegistered">
                                <label for="isSalesTaxRegistered">
                                    <i class="fas fa-receipt"></i>
                                    Is Sales Tax Registered?
                                </label>
                            </div>
                        </div>

                        <div class="form-group col-6" id="strnGroup" style="display: none;">
                            <label for="strn">
                                <i class="fas fa-hashtag"></i>
                                Sales Tax Registered Number (STRN)
                            </label>
                            <input type="text" id="strn" maxlength="50" placeholder="Enter STRN">
                        </div>

                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="isFiler">
                                <label for="isFiler">
                                    <i class="fas fa-file-alt"></i>
                                    Is Filer?
                                </label>
                            </div>
                        </div>

                        <div class="form-group col-6" id="ntnGroup" style="display: none;">
                            <label for="ntn">
                                <i class="fas fa-hashtag"></i>
                                National Tax Number (NTN)
                            </label>
                            <input type="text" id="ntn" maxlength="50" placeholder="Enter NTN">
                        </div>

                        <div class="form-group col-6">
                            <label for="advanceIncomeTax">
                                <i class="fas fa-percent"></i>
                                Advance Income Tax %
                            </label>
                            <input type="number" id="advanceIncomeTax" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Financial Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-line"></i>
                            Financial Information
                        </h3>

                        <div class="form-group col-6">
                            <label for="defaultDiscount">
                                <i class="fas fa-percent"></i>
                                Default Discount %
                            </label>
                            <input type="number" id="defaultDiscount" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>

                        <div class="form-group col-6">
                            <label for="balanceLimit">
                                <i class="fas fa-wallet"></i>
                                Credit Limit
                            </label>
                            <input type="number" id="balanceLimit" step="0.01" min="0" placeholder="0.00">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Maximum credit limit allowed
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="balancePeriodLimit">
                                <i class="fas fa-calendar-alt"></i>
                                Credit Period Limit (Days)
                            </label>
                            <input type="number" id="balancePeriodLimit" min="0" placeholder="0">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Credit period in days
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="openingDebit">
                                <i class="fas fa-money-bill-wave"></i>
                                Opening Debit (Dr) Amount
                            </label>
                            <input type="number" id="openingDebit" step="0.01" min="0" placeholder="0.00" readonly>
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Auto-calculated from invoices or enter manually
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="openingCredit">
                                <i class="fas fa-credit-card"></i>
                                Opening Credit (Cr) Amount
                            </label>
                            <input type="number" id="openingCredit" step="0.01" min="0" placeholder="0.00">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Amount you owe to customer
                            </div>
                        </div>
                    </div>

                    <!-- Opening Balance Invoices Section -->
                    <div class="form-section" id="openingInvoicesSection">
                        <h3 class="section-title">
                            <i class="fas fa-file-invoice"></i>
                            Opening Balance Invoices
                        </h3>

                        <div class="form-group col-12">
                            <div style="overflow-x: auto;">
                                <table id="invoicesTable" style="width: 100%; border-collapse: collapse; border: 1px solid var(--border-default);">
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
                                    <tbody id="invoicesTableBody">
                                        <!-- Invoice rows will be added here -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6" style="padding: 12px; border: 1px solid var(--border-default);">
                                                <button type="button" class="btn btn-secondary btn-sm" id="addInvoiceBtn">
                                                    <i class="fas fa-plus"></i>
                                                    Add Invoice
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="helper-text" style="margin-top: 8px;">
                                <i class="fas fa-info-circle"></i>
                                Opening Debit will be auto-calculated from invoice totals
                            </div>
                        </div>
                    </div>

                    <!-- Sub Accounts Section -->
                    <div class="form-section" id="subAccountsSection">
                        <h3 class="section-title">
                            <i class="fas fa-users"></i>
                            Sub Accounts
                        </h3>

                        <div class="form-group col-12">
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
                                    <tbody id="subAccountsTableBody">
                                        <!-- Sub account rows will be added here -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="padding: 12px; border: 1px solid var(--border-default);">
                                                <button type="button" class="btn btn-secondary btn-sm" id="addSubAccountBtn">
                                                    <i class="fas fa-plus"></i>
                                                    Add Sub Account
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Security Settings
                        </h3>

                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="isWholesaler">
                                <label for="isWholesaler">
                                    <i class="fas fa-warehouse"></i>
                                    Is Wholesaler?
                                </label>
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="blacklist">
                                <label for="blacklist">
                                    <i class="fas fa-ban"></i>
                                    Blacklist this customer
                                </label>
                            </div>
                        </div>

                        <div class="form-group col-12">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Prevent transactions with blacklisted customers
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo"></i>
                        Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="notification" id="notification">
        <div class="notification-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title" id="notificationTitle">Success</div>
            <div class="notification-message" id="notificationMessage">Customer saved successfully!</div>
        </div>
        <button class="notification-close" id="notificationClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Customer Types Modal -->
    <div class="modal" id="typesModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-tags"></i> Manage Customer Types</h3>
                <button class="modal-close" id="typesModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn btn-primary btn-sm" id="addTypeBtn">
                        <i class="fas fa-plus"></i> Add New Type
                    </button>
                </div>
                <div class="table-container" style="border-radius: 12px; border: 1px solid var(--border-default);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: var(--surface-2);">
                            <tr>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-default);">Type Name</th>
                                <th style="padding: 12px; text-align: center; width: 100px; border-bottom: 1px solid var(--border-default);">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="typesTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="typesCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Customer Categories Modal -->
    <div class="modal" id="categoriesModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-folder-open"></i> Manage Customer Categories</h3>
                <button class="modal-close" id="categoriesModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn btn-primary btn-sm" id="addCategoryBtn">
                        <i class="fas fa-plus"></i> Add New Category
                    </button>
                </div>
                <div class="table-container" style="border-radius: 12px; border: 1px solid var(--border-default);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: var(--surface-2);">
                            <tr>
                                <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-default);">Category Name</th>
                                <th style="padding: 12px; text-align: center; width: 100px; border-bottom: 1px solid var(--border-default);">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="categoriesCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Customize Fields Modal -->
    <div class="modal" id="customizeFieldsModal">
        <div class="modal-content" style="max-width: 500px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h3><i class="fas fa-sliders-h"></i> Customize Fields</h3>
                <button class="modal-close" id="customizeFieldsModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div style="margin-bottom: 16px; display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" id="customizeFieldsSelectAllBtn" style="flex: 1;">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="customizeFieldsDeselectAllBtn" style="flex: 1;">
                        <i class="fas fa-square"></i> Deselect All
                    </button>
                </div>
                <div id="customizeFieldsList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <!-- Fields will be generated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer" style="gap: 12px;">
                <button type="button" class="btn btn-secondary" id="customizeFieldsResetBtn">Reset to Default</button>
                <button type="button" class="btn btn-primary" id="customizeFieldsSaveBtn">Save Preferences</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Type Modal -->
    <div class="modal" id="typeFormModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="typeFormTitle"><i class="fas fa-plus"></i> Add Customer Type</h3>
                <button class="modal-close" id="typeFormModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="typeForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="typeName" class="required">Type Name</label>
                        <input type="text" id="typeName" required placeholder="Enter type name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="typeFormCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="typeFormSaveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal" id="categoryFormModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="categoryFormTitle"><i class="fas fa-plus"></i> Add Customer Category</h3>
                <button class="modal-close" id="categoryFormModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="categoryName" class="required">Category Name</label>
                        <input type="text" id="categoryName" required placeholder="Enter category name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="categoryFormCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="categoryFormSaveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/customer_supplier/customers/customer-add.js"></script>
</body>

</html>