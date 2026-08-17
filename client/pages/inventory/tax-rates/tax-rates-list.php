<?php
require_once '../../../../includes/dashboard.php';

session_start();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Tax Rates</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/tax-rates/tax-rates.css">
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 style="margin: 0;">Tax Rates Management</h1>
            <button type="button" class="btn btn-primary" id="addNewBtn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px;">
                    <path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Add Tax Rate
            </button>
        </div>

        <div class="card search-section">
            <div class="search-filters">
                <div class="form-group">
                    <input type="text" id="searchInput" placeholder="Search by tax name or authority..." class="search-input">
                </div>
                <div class="form-group">
                    <select id="taxTypeFilter" class="filter-select">
                        <option value="">All Tax Types</option>
                        <option value="sales_tax">Sales Tax</option>
                        <option value="further_tax">Further Tax</option>
                        <option value="wht">WHT</option>
                        <option value="advance_tax">Advance Tax</option>
                        <option value="minimum_tax">Minimum Tax</option>
                        <option value="fed">FED</option>
                    </select>
                </div>
                <div class="form-group">
                    <select id="transactionTypeFilter" class="filter-select">
                        <option value="">All Transactions</option>
                        <option value="sale">Sale</option>
                        <option value="purchase">Purchase</option>
                        <option value="import">Import</option>
                        <option value="export">Export</option>
                        <option value="payment">Payment</option>
                    </select>
                </div>
                <div class="form-group">
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="button" class="btn btn-secondary" id="resetFilters">Reset</button>
            </div>
        </div>

        <div class="card">
            <div id="loadingSpinner" style="text-align: center; display: none;">
                <p>Loading tax rates...</p>
            </div>
            <table id="taxRatesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Tax Name</th>
                        <th>Type</th>
                        <th>Transaction</th>
                        <th>Rate %</th>
                        <th>Authority</th>
                        <th>Record Type</th>
                        <th>Status</th>
                        <th>Effective From</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                </tbody>
            </table>
            
            <div id="paginationContainer" class="pagination-container">
                <!-- Pagination will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="taxRateModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Tax Rate</h2>
                <button type="button" class="modal-close" id="closeModal">&times;</button>
            </div>
            <form id="taxRateForm" class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Tax Authority</label>
                        <select id="taxAuthority" name="tax_authority" required>
                            <option value="">Select Authority</option>
                            <option value="FBR">FBR</option>
                            <option value="SBP">SBP</option>
                            <option value="CUSTOMS">CUSTOMS</option>
                            <option value="OTHER">OTHER</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Tax Type</label>
                        <select id="taxType" name="tax_type" required>
                            <option value="">Select Tax Type</option>
                            <option value="sales_tax">Sales Tax</option>
                            <option value="further_tax">Further Tax</option>
                            <option value="wht">WHT</option>
                            <option value="advance_tax">Advance Tax</option>
                            <option value="minimum_tax">Minimum Tax</option>
                            <option value="fed">FED</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">Transaction Type</label>
                        <select id="transactionType" name="transaction_type" required>
                            <option value="">Select Type</option>
                            <option value="sale">Sale</option>
                            <option value="purchase">Purchase</option>
                            <option value="import">Import</option>
                            <option value="export">Export</option>
                            <option value="payment">Payment</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label class="required">Tax Name</label>
                        <input type="text" id="taxName" name="tax_name" required placeholder="e.g., GST — Sale to Registered Buyer">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Legal Section</label>
                        <input type="text" id="legalSection" name="legal_section" placeholder="e.g., Sec 3(1)">
                    </div>
                    <div class="form-group">
                        <label>Finance Act Year</label>
                        <input type="number" id="financeActYear" name="finance_act_year" min="2000" value="2024">
                    </div>
                    <div class="form-group">
                        <label class="required">Rate Percentage</label>
                        <input type="number" id="ratePercentage" name="rate_percentage" step="0.01" required placeholder="18.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Threshold Min</label>
                        <input type="number" id="thresholdMin" name="threshold_min" step="0.01" placeholder="Minimum amount">
                    </div>
                    <div class="form-group">
                        <label>Threshold Max</label>
                        <input type="number" id="thresholdMax" name="threshold_max" step="0.01" placeholder="Maximum amount">
                    </div>
                    <div class="form-group">
                        <label class="required">Tax Regime</label>
                        <select id="taxRegimeId" name="tax_regime_id" required>
                            <option value="">Select Tax Regime</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Customer Type</label>
                        <select id="customerTypeId" name="customer_type_id">
                            <option value="">Select Customer Type</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Party Type</label>
                        <select id="partyType" name="party_type">
                            <option value="">Select Party Type</option>
                            <option value="all">All</option>
                            <option value="registered_company">Registered Company</option>
                            <option value="unregistered">Unregistered</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Deducted By</label>
                        <select id="deductedBy" name="deducted_by">
                            <option value="seller">Seller</option>
                            <option value="buyer">Buyer</option>
                            <option value="customs">Customs</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <select id="currency" name="currency">
                            <option value="PKR">PKR</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Effective From</label>
                        <input type="date" id="effectiveFrom" name="effective_from" required>
                    </div>
                    <div class="form-group">
                        <label>Effective To</label>
                        <input type="date" id="effectiveTo" name="effective_to">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_filer"> Is Filer
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_adjustable" checked> Is Adjustable
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_refundable" checked> Is Refundable
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_final_tax"> Is Final Tax
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" checked> Is Active
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group full-width">
                        <label>Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Tax rate description"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../../assets/js/inventory/tax-rates/tax-rates-list.js"></script>
</body>
</html>
