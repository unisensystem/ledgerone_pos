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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Customer + Supplier Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customers/customer-add.css">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/suppliers/supplier-add.css">
    <style>
        /* Visual separation between the Customer-only and Supplier-only sections */
        .section-divider {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 8px 0 4px;
        }
        .section-divider .badge {
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .section-divider.customer .badge { background: #e0ecff; color: #1d4ed8; }
        .section-divider.supplier .badge { background: #fef3c7; color: #b45309; }
        .section-divider hr { flex: 1; border: none; border-top: 1px solid var(--border-default); }
    </style>
</head>

<body class="light-mode">
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--border-default);">
                <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;">
                    <i class="fas fa-user-friends"></i>
                    Customer + Supplier Entry (Both)
                </h2>
                <button type="button" id="customizeFieldsBtn" class="btn btn-secondary btn-sm" style="padding: 0 16px; height: 40px;">
                    <i class="fas fa-sliders-h"></i>
                    Customize Fields
                </button>
            </div>

            <div class="helper-text" style="margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i>
                One entry here creates a linked record in BOTH the Customer and Supplier lists (marked as "Both").
            </div>

            <form id="bothForm">
                <div class="form-grid">

                    <!-- Shared Basic Information -->
                    <div class="form-section" id="basicInfoSection">
                        <h3 class="section-title">
                            <i class="fas fa-id-card"></i>
                            Basic Information (Shared)
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
                            <label for="customerCode">
                                <i class="fas fa-hashtag"></i>
                                Customer Code
                            </label>
                            <input type="text" id="customerCode" readonly value="Auto Generated">
                        </div>

                        <div class="form-group col-4">
                            <label for="supplierCode">
                                <i class="fas fa-hashtag"></i>
                                Supplier Code
                            </label>
                            <input type="text" id="supplierCode" readonly value="Auto Generated">
                        </div>

                        <div class="form-group col-6">
                            <label for="partyName" class="required">
                                <i class="fas fa-user"></i>
                                Party Name
                            </label>
                            <input type="text" id="partyName" required placeholder="Enter party's full name / business name">
                            <div class="error-text" id="partyNameError">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Party name is required</span>
                            </div>
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

                        <div class="form-group col-12">
                            <label for="address">
                                <i class="fas fa-map-marker-alt"></i>
                                Address
                            </label>
                            <textarea id="address" placeholder="Enter complete address"></textarea>
                        </div>

                        <div class="form-group col-4">
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

                        <div class="form-group col-4">
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

                        <div class="form-group col-4">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address
                            </label>
                            <input type="email" id="email" maxlength="300" placeholder="party@example.com">
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

                        <div class="form-group col-6">
                            <div class="checkbox-group">
                                <input type="checkbox" id="blacklist">
                                <label for="blacklist">
                                    <i class="fas fa-ban"></i>
                                    Blacklist this Party (both sides)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="section-divider customer">
                        <span class="badge"><i class="fas fa-user"></i> Customer-only fields</span>
                        <hr>
                    </div>

                    <!-- Customer Details -->
                    <div class="form-section" id="customerDetailsSection">
                        <h3 class="section-title">
                            <i class="fas fa-user-tag"></i>
                            Customer Details
                        </h3>

                        <div class="form-group col-4">
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

                        <div class="form-group col-4">
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

                        <div class="form-group col-4">
                            <label for="brandName">
                                <i class="fas fa-tag"></i>
                                Brand Name
                            </label>
                            <select id="brandName">
                                <option value="">Select Brand</option>
                            </select>
                        </div>

                        <div class="form-group col-6">
                            <label for="shopkeeperName">
                                <i class="fas fa-store"></i>
                                Shopkeeper Name
                            </label>
                            <input type="text" id="shopkeeperName" placeholder="Enter shopkeeper name">
                        </div>

                        <div class="form-group col-3">
                            <label for="poBoxNo">
                                <i class="fas fa-mailbox"></i>
                                P.O. Box No
                            </label>
                            <input type="text" id="poBoxNo" maxlength="50" placeholder="e.g., P.O. Box 12345">
                        </div>

                        <div class="form-group col-3">
                            <label for="licenseNo">
                                <i class="fas fa-certificate"></i>
                                License #
                            </label>
                            <input type="text" id="licenseNo" maxlength="100" placeholder="Enter license number">
                        </div>

                        <div class="form-group col-4">
                            <label for="salesOfficer">
                                <i class="fas fa-user-tie"></i>
                                Associated Sales Officer
                            </label>
                            <select id="salesOfficer">
                                <option value="">Select Sales Officer</option>
                            </select>
                        </div>

                        <div class="form-group col-4">
                            <label for="supplierMan">
                                <i class="fas fa-user-tag"></i>
                                Supplier Man
                            </label>
                            <select id="supplierMan">
                                <option value="">Select Supplier Man</option>
                            </select>
                        </div>

                        <div class="form-group col-4">
                            <div class="checkbox-group">
                                <input type="checkbox" id="outStation">
                                <label for="outStation">
                                    <i class="fas fa-road"></i>
                                    Out Station
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Territory -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marked-alt"></i>
                            Customer Territory
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

                    <!-- Customer Taxation -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Customer Taxation
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

                    <!-- Customer Financial -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-line"></i>
                            Customer Financial Information
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
                        </div>

                        <div class="form-group col-6">
                            <label for="balancePeriodLimit">
                                <i class="fas fa-calendar-alt"></i>
                                Credit Period Limit (Days)
                            </label>
                            <input type="number" id="balancePeriodLimit" min="0" placeholder="0">
                        </div>

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
                            <label for="customerOpeningDebit">
                                <i class="fas fa-money-bill-wave"></i>
                                Customer Opening Debit (Dr) Amount
                            </label>
                            <input type="number" id="customerOpeningDebit" step="0.01" min="0" placeholder="0.00" readonly>
                            <div class="helper-text">
                                <i class="fas fa-info-circle"></i>
                                Auto-calculated from invoices or enter manually
                            </div>
                        </div>

                        <div class="form-group col-6">
                            <label for="customerOpeningCredit">
                                <i class="fas fa-credit-card"></i>
                                Customer Opening Credit (Cr) Amount
                            </label>
                            <input type="number" id="customerOpeningCredit" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Customer Opening Balance Invoices -->
                    <div class="form-section" id="openingInvoicesSection">
                        <h3 class="section-title">
                            <i class="fas fa-file-invoice"></i>
                            Customer Opening Balance Invoices
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
                                    <tbody id="invoicesTableBody"></tbody>
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
                        </div>
                    </div>

                    <!-- Customer Sub Accounts -->
                    <div class="form-section" id="subAccountsSection">
                        <h3 class="section-title">
                            <i class="fas fa-users"></i>
                            Customer Sub Accounts
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
                                    <tbody id="subAccountsTableBody"></tbody>
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

                    <div class="section-divider supplier">
                        <span class="badge"><i class="fas fa-truck"></i> Supplier-only fields</span>
                        <hr>
                    </div>

                    <!-- Supplier Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-truck"></i>
                            Supplier Details
                        </h3>

                        <div class="form-group col-6" style="position: relative;">
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
                                <div id="assocCompanyChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;"></div>
                                <input type="text" id="assocCompanySearch" placeholder="Search company..." autocomplete="off" style="
                                    border: none; outline: none; background: transparent; width: 100%; font-size: 14px; padding: 2px 4px; height: auto;
                                ">
                            </div>
                            <div id="assocCompanyDropdown" style="
                                display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
                                background: var(--surface-1, #fff); border: 1px solid var(--border-default, #ccc);
                                border-radius: 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            "></div>
                            <input type="hidden" id="assocCompanyIds" value="">
                        </div>

                        <div class="form-group col-6" style="position: relative;">
                            <label for="salesmanSearch">
                                <i class="fas fa-user-tie"></i>
                                Salesman
                            </label>
                            <div id="salesmanChipsContainer" style="
                                border: 1px solid var(--input-border, #ccc); border-radius: 8px; padding: 6px 8px;
                                background: var(--input-bg, #fff); cursor: text;
                            ">
                                <div id="salesmanChipsList" style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;"></div>
                                <input type="text" id="salesmanSearch" placeholder="Search salesman..." autocomplete="off" style="
                                    border: none; outline: none; background: transparent; width: 100%; font-size: 14px; padding: 2px 4px;
                                ">
                            </div>
                            <div id="salesmanDropdown" style="
                                display: none; position: absolute; z-index: 999; background: var(--surface-1, #fff);
                                border: 1px solid var(--border-default, #ccc); border-radius: 8px; max-height: 200px;
                                overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                            "></div>
                            <input type="hidden" id="salesmanIds" value="">
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
                                <button type="button" class="btn btn-secondary btn-sm" id="manageSupplierCategoriesBtn" style="padding: 0 16px;">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group col-4">
                            <label for="brandNameSupplier">
                                <i class="fas fa-tag"></i>
                                Supplier's Brand Name
                            </label>
                            <input type="text" id="brandNameSupplier" maxlength="255" placeholder="Enter brand name">
                        </div>

                        <div class="form-group col-4" style="position: relative;">
                            <label for="citySearch">
                                <i class="fas fa-city"></i>
                                Supplier City
                            </label>
                            <input type="text" id="citySearch" autocomplete="off" maxlength="255" placeholder="Search city...">
                            <input type="hidden" id="supplierCity" value="">
                            <div id="cityDropdown" style="
                                display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
                                background: var(--surface-1, #fff); border: 1px solid var(--border-default, #ccc);
                                border-radius: 8px; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            "></div>
                        </div>

                        <div class="form-group col-4">
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

                    <!-- Supplier Financial -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-chart-line"></i>
                            Supplier Financial Information
                        </h3>

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
                        </div>

                        <div class="form-group col-6">
                            <label for="supplierOpeningDebit">
                                <i class="fas fa-money-bill-wave"></i>
                                Supplier Opening Debit (Dr) Amount
                            </label>
                            <input type="number" id="supplierOpeningDebit" step="0.01" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group col-6">
                            <label for="supplierOpeningCredit">
                                <i class="fas fa-credit-card"></i>
                                Supplier Opening Credit (Cr) Amount
                            </label>
                            <input type="number" id="supplierOpeningCredit" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Supplier Sub Accounts -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-list"></i>
                            Supplier Sub Accounts
                        </h3>

                        <div class="form-group col-12">
                            <div class="table-container">
                                <table id="supplierSubAccountsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">S#</th>
                                            <th>Sub Account Name</th>
                                            <th style="width: 150px;">Debit</th>
                                            <th style="width: 150px;">Credit</th>
                                            <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="supplierSubAccountsBody">
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
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo"></i>
                        Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i>
                        Save Customer + Supplier
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
            <div class="notification-message" id="notificationMessage">Saved successfully!</div>
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
                <button class="modal-close" id="typesModalClose"><i class="fas fa-times"></i></button>
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
                        <tbody id="typesTableBody"></tbody>
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
                <button class="modal-close" id="categoriesModalClose"><i class="fas fa-times"></i></button>
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

    <!-- Supplier Categories Modal -->
    <div class="modal" id="supplierCategoriesModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-folder-open"></i> Manage Supplier Categories</h3>
                <button class="modal-close" id="supplierCategoriesModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn btn-primary btn-sm" id="addSupplierCategoryBtn">
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
                        <tbody id="supplierCategoriesTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="supplierCategoriesCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Customize Fields Modal -->
    <div class="modal" id="customizeFieldsModal">
        <div class="modal-content" style="max-width: 500px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h3><i class="fas fa-sliders-h"></i> Customize Fields</h3>
                <button class="modal-close" id="customizeFieldsModalClose"><i class="fas fa-times"></i></button>
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
                <div id="customizeFieldsList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;"></div>
            </div>
            <div class="modal-footer" style="gap: 12px;">
                <button type="button" class="btn btn-secondary" id="customizeFieldsResetBtn">Reset to Default</button>
                <button type="button" class="btn btn-primary" id="customizeFieldsSaveBtn">Save Preferences</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Customer Type Modal -->
    <div class="modal" id="typeFormModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="typeFormTitle"><i class="fas fa-plus"></i> Add Customer Type</h3>
                <button class="modal-close" id="typeFormModalClose"><i class="fas fa-times"></i></button>
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

    <!-- Add/Edit Customer Category Modal -->
    <div class="modal" id="categoryFormModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="categoryFormTitle"><i class="fas fa-plus"></i> Add Customer Category</h3>
                <button class="modal-close" id="categoryFormModalClose"><i class="fas fa-times"></i></button>
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

    <!-- Add/Edit Supplier Category Modal -->
    <div class="modal" id="supplierCategoryFormModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="supplierCategoryFormTitle"><i class="fas fa-plus"></i> Add Supplier Category</h3>
                <button class="modal-close" id="supplierCategoryFormModalClose"><i class="fas fa-times"></i></button>
            </div>
            <form id="supplierCategoryForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="supplierCategoryName" class="required">Category Name</label>
                        <input type="text" id="supplierCategoryName" required placeholder="Enter category name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="supplierCategoryFormCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="supplierCategoryFormSaveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/customer_supplier/customer_supplier_both/customer-supplier-add.js?v=<?php echo time(); ?>"></script>
</body>

</html>
