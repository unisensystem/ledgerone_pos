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
    <title>LedgerOne ERP - Supplier Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/suppliers/supplier-add.css">
</head>
<body class="light-mode">
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--border-default);">
                <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;">
                    <i class="fas fa-user-plus"></i>
                    Supplier Entry
                </h2>
                <button type="button" id="customizeFieldsBtn" class="btn btn-secondary btn-sm" style="padding: 0 16px; height: 40px;">
                    <i class="fas fa-sliders-h"></i>
                    Customize Fields
                </button>
            </div>
            <form id="supplierForm">
                <div class="form-grid">
                    <!-- Basic Information Section -->
                    <div class="form-section">
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

                        <div class="form-group col-4" style="position: relative;">
                            <label for="assocCompanySearch">
                                <i class="fas fa-building"></i>
                                Associated Companies
                            </label>
                            <div id="assocCompanyChipsContainer" style="
                                border: 1px solid var(--input-border, #ccc);
                                border-radius: 8px;
                                padding: 6px 8px;
                                background: var(--input-bg, #fff);
                                cursor: text;
                                min-height: 48px;
                            ">
                                <div id="assocCompanyChipsList" style="
                                    display: flex;
                                    flex-wrap: wrap;
                                    gap: 6px;
                                    margin-bottom: 4px;
                                "></div>
                                <input type="text" id="assocCompanySearch" placeholder="Search company..." autocomplete="off" style="
                                    border: none;
                                    outline: none;
                                    background: transparent;
                                    width: 100%;
                                    font-size: 14px;
                                    padding: 2px 4px;
                                    height: auto;
                                ">
                            </div>
                            <div id="assocCompanyDropdown" style="
                                display: none;
                                position: absolute;
                                top: 100%;
                                left: 0;
                                right: 0;
                                z-index: 9999;
                                background: var(--surface-1, #fff);
                                border: 1px solid var(--border-default, #ccc);
                                border-radius: 8px;
                                max-height: 200px;
                                overflow-y: auto;
                                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            "></div>
                            <input type="hidden" id="assocCompanyIds" name="assocCompanyIds" value="">
                        </div>

                        <div class="form-group col-4">
                            <label for="supplierCode" class="required">
                                <i class="fas fa-hashtag"></i>
                                Supplier Code
                            </label>
                            <input type="text" id="supplierCode" readonly value="Auto Generated">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Automatically generated
                            </div>
                        </div>
<div class="form-group col-4">
    <label for="salesmanSearch">
        <i class="fas fa-user-tie"></i>
        Salesman
    </label>
    <div id="salesmanChipsContainer" style="
        border: 1px solid var(--input-border, #ccc);
        border-radius: 8px;
        padding: 6px 8px;
        background: var(--input-bg, #fff);
        cursor: text;
    ">
        <div id="salesmanChipsList" style="
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 4px;
        ">
            <!-- chips render here -->
        </div>
        <input type="text" id="salesmanSearch" placeholder="Search salesman..." autocomplete="off" style="
            border: none;
            outline: none;
            background: transparent;
            width: 100%;
            font-size: 14px;
            padding: 2px 4px;
        ">
    </div>
    <div id="salesmanDropdown" style="
        display: none;
        position: absolute;
        z-index: 999;
        background: var(--surface-1, #fff);
        border: 1px solid var(--border-default, #ccc);
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    "></div>
    <input type="hidden" id="salesmanIds" name="salesmanIds" value="">
</div>

                        <div class="form-group col-12">
                            <label for="supplierName" class="required">
                                <i class="fas fa-user"></i>
                                Brand Name - Company
                            </label>
                            
                            <input type="text" id="supplierName" required placeholder="Enter Brand Name - Company">
                            <div class="error-text" id="supplierNameError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Brand Name is required</span>
                            </div>
                        </div>

                        <div class="form-group col-4">
    <label for="supplierCategory">
        <i class="fas fa-folder"></i>
        Supplier Category
    </label>
    <div style="display: flex; gap: 8px;">
        <select id="supplierCategory" style="flex: 1;">
            <option value="">Select Supplier Category</option>
        </select>
        <button type="button" class="btn btn-secondary btn-sm" id="manageCategoriesBtn" style="padding: 0 16px;">
            <i class="fas fa-cog"></i>
        </button>
    </div>
</div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-address-book"></i>
                            Contact Information
                        </h3>

                        <div class="form-group col-8">
                            <label for="brandName">
                                <i class="fas fa-tag"></i>
                                Supplier Name
                            </label>
                            <input type="text" id="brandName" maxlength="255" placeholder="Enter Supplier name">
                        </div>

                        <div class="form-group col-12">
                            <label for="address">
                                <i class="fas fa-map-marker-alt"></i>
                                Address
                            </label>
                            <textarea id="address" placeholder="Enter complete supplier address"></textarea>
                        </div>

          <div class="form-group col-6" style="position: relative;">
    <label for="citySearch">
        <i class="fas fa-city"></i>
        City
    </label>
    <input type="text" id="citySearch" autocomplete="off" maxlength="255" placeholder="Search city...">
    <input type="hidden" id="city" value="">
    <div id="cityDropdown" style="
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 9999;
        background: var(--surface-1, #fff);
        border: 1px solid var(--border-default, #ccc);
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    "></div>
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
                            <input type="email" id="email" maxlength="300" placeholder="supplier@example.com">
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
                            <input type="text" id="identityCard" maxlength="15" placeholder="Numbers only">
                            <div class="error-text" id="identityCardError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Identity card can only contain numbers</span>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-line"></i>
                            Financial Information
                        </h3>

                        <div class="form-group col-6">
                            <label for="project">
                                <i class="fas fa-project-diagram"></i>
                                Project
                            </label>
                            <select id="project">
                                <option value="">Select Project</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="openingDebit">
                                <i class="fas fa-money-bill-wave"></i>
                                Opening Debit (Dr) Amount
                            </label>
                            <input type="number" id="openingDebit" step="0.01" min="0" placeholder="0.00">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Auto-calculated from sub accounts
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
                                Auto-calculated from sub accounts
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="aitPercent">
                                <i class="fas fa-percent"></i>
                                AIT %
                            </label>
                            <input type="number" id="aitPercent" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>

                        <div class="form-group col-6">
                            <label for="creditDays">
                                <i class="fas fa-calendar-day"></i>
                                Credit Days
                            </label>
                            <input type="number" id="creditDays" step="1" min="0" placeholder="0">
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Number of days allowed before payment is due
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="partyType">
                                <i class="fas fa-building"></i>
                                Party Type
                            </label>
                            <select id="partyType">
                                <option value="">Select Party Type</option>
                                <option value="registered_company">Registered Company</option>
                                <option value="unregistered">Unregistered</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sub Accounts Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-list"></i>
                            Sub Accounts
                        </h3>

                        <div class="form-group col-12">
                            <div class="table-container">
                                <table id="subAccountsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">S#</th>
                                            <th>Sub Account Name</th>
                                            <th style="width: 150px;">Debit</th>
                                            <th style="width: 150px;">Credit</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="subAccountsBody">
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
                    </div>

                    <!-- Security Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Security Settings
                        </h3>

                        <div class="form-group col-12">
                            <div class="checkbox-group">
                                <input type="checkbox" id="blacklist">
                                <label for="blacklist">
                                    <i class="fas fa-ban"></i>
                                    Blacklist this supplier
                                </label>
                            </div>
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Prevent transactions with blacklisted suppliers
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
                        Save Supplier
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
            <div class="notification-message" id="notificationMessage">Supplier saved successfully!</div>
        </div>
        <button class="notification-close" id="notificationClose">
            <i class="fas fa-times"></i>
        </button>
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
                </div>
            </div>
            <div class="modal-footer" style="gap: 12px;">
                <button type="button" class="btn btn-secondary" id="customizeFieldsResetBtn">Reset to Default</button>
                <button type="button" class="btn btn-primary" id="customizeFieldsSaveBtn">Save Preferences</button>
            </div>
        </div>
    </div>

    <!-- Supplier Categories Modal -->
<div class="modal" id="categoriesModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-folder-open"></i> Manage Supplier Categories</h3>
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
                    <tbody id="categoriesTableBody"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="categoriesCloseBtn">Close</button>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal" id="categoryFormModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 id="categoryFormTitle"><i class="fas fa-plus"></i> Add Supplier Category</h3>
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

    <script src="../../../assets/js/customer_supplier/suppliers/supplier-add.js?v=<?php echo time(); ?>"></script>
</body>

</html>
