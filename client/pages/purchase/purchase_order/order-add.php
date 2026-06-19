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
    <title>LedgerOne ERP - Purchase Order</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_order/order-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Purchase Order</h1>
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn btn-secondary" id="fieldsSettingsBtn">
                    <i class="fas fa-columns"></i> Fields Settings
                </button>
                <button type="button" class="btn btn-secondary" id="invoiceSettingsBtn">
                    <i class="fas fa-cog"></i> Order Settings
                </button>
            </div>
        </div>

        <form id="invoiceForm">
            <div class="card">
                <h2 class="card-title">Order Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="billNo" class="required">Order No</label>
                        <input type="text" id="billNo" value="Automatically generated" readonly required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="purchaseDate" class="required">Order Date</label>
                        <input type="date" id="purchaseDate" required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="supplierInvoiceNo">Supplier Invoice No</label>
                        <input type="text" id="supplierInvoiceNo" placeholder="Enter supplier invoice number">
                    </div>
                    <div class="form-group">
                        <label for="company" class="required">Company</label>
                        <select id="company" required>
                            <option value="">Select Company</option>
                            <!-- Options loaded dynamically -->
                        </select>
                        <div class="error-message" id="companyError">Please select a company</div>
                    </div>
                    <div class="form-group">
                        <label for="supplierInvoiceDate">Supplier Invoice Date</label>
                        <input type="date" id="supplierInvoiceDate">
                    </div>
                    <div class="form-group">
                        <label for="biltyNo">Bilty No</label>
                        <input type="text" id="biltyNo" placeholder="Enter bilty/LR number">
                    </div>
                    <div class="form-group">
                        <label for="transportName">Transport Name</label>
                        <input type="text" id="transportName" placeholder="Enter transport company name">
                    </div>
                    <div class="form-group">
                        <label for="previousBalance">Supplier's Previous Balance</label>
                        <input type="text" id="previousBalance" value="0.00" readonly tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="supplierCode" class="required">Supplier Code</label>
                        <div style="display: flex; gap: 8px;">
                            <div class="searchable-dropdown" style="flex: 1;">
                                <input type="text" class="search-input" autocomplete="off" placeholder="Search supplier code..." id="supplierCodeSearch">
                                <div class="dropdown-options" id="supplierCodeOptions">
                                    <!-- Options loaded dynamically -->
                                </div>
                                <input type="hidden" id="supplierCode" required>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="addSupplierBtn" title="Add New Supplier">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="error-message" id="supplierCodeError">Please select a supplier code</div>
                    </div>

                    <div class="form-group">
                        <label for="branch" class="required">Branch</label>
                        <div class="searchable-dropdown">
                            <input type="text" autocomplete="off" class="search-input" placeholder="Search branch..." id="branchSearch">
                            <div class="dropdown-options" id="branchOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="branch" required>
                        </div>
                        <div class="error-message" id="branchError">Please select a branch</div>
                    </div>
                    <div class="form-group">
                        <label for="currency" class="required">Currency</label>
                        <select id="currency" required tabindex="-1">
                            <option value="">Select Currency</option>
                            <!-- Options loaded dynamically -->
                        </select>
                        <div class="error-message" id="currencyError">Please select a currency</div>
                    </div>
                    <div class="form-group">
                        <label for="project">Project</label>
                        <select id="project">
                            <option value="">Select Project</option>
                            <!-- Options loaded dynamically -->
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" placeholder="Enter any additional remarks" tabindex="-1"></textarea>
                    </div>

                </div>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="card-title" style="margin-bottom: 0;">Items</h2>
                    <button type="button" class="btn btn-secondary btn-sm" id="addProductBtn" title="Add New Product">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>
                <div class="table-container">
                    <table id="itemsTable">
                        <thead>
                            <tr>
                                <th width="4%">S#</th>
                                <th width="20%">Product Code / Name</th>
                                <!-- Dynamic unit columns will be inserted here -->
                                <th width="8%"><span id="purchasePriceLabel">Purchase Price</span></th>
                                <th width="8%"><span id="grossAmountLabel">Gross Amount</span></th>
                                <th width="5%">Disc %</th>
                                <th width="8%"><span id="discountAmountLabel">Disc Amt</span></th>
                                <th width="5%">TO %</th>
                                <th width="8%">TO Amt</th>
                                <th width="5%">GST %</th>
                                <th width="8%">GST Amt</th>
                                <th width="5%">FOC Qty</th>
                                <th width="8%"><span id="netAmountLabel">Net Amount</span></th>
                                <th width="4%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be added dynamically -->
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <th colspan="2">Totals</th>
                                <!-- Dynamic unit totals will be inserted here -->
                                <th id="totalPurchasePrice">0.00</th>
                                <th id="totalGrossAmount">0.00</th>
                                <th></th>
                                <th id="totalDiscountAmountItems">0.00</th>
                                <th></th>
                                <th id="totalTradeOfferAmountItems">0.00</th>
                                <th></th>
                                <th id="totalGSTAmountItems">0.00</th>
                                <th id="totalFOCQty">0.00</th>
                                <th id="totalNetAmountItems">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Order Summary</h2>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label" id="totalBillLabel">Total Bill</span>
                        <span class="summary-value" id="totalBill">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Discount %</span>
                        <input type="number" id="totalDiscountPercent" class="table-input" min="0" max="100" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="totalDiscountAmountLabel">Discount Amount</span>
                        <input type="number" id="totalDiscountAmount" class="table-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">GST %</span>
                        <input type="number" id="totalGSTPercent" class="table-input" min="0" max="100" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="totalGSTAmountLabel">GST Amount</span>
                        <input type="number" id="totalGSTAmount" class="table-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="shippingFeesLabel">Shipping Fees</span>
                        <input type="number" id="shippingFees" class="table-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="netAmountSummaryLabel">Net Amount</span>
                        <span class="summary-value" id="netAmount">0.00</span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" id="resetBtn" tabindex="-1">
                    <i class="fas fa-redo"></i> Reset Order
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Order
                </button>
            </div>
        </form>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <h3 class="modal-title">Order Saved Successfully!</h3>
            <p>What would you like to do next?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="printLaterBtn">
                    Print Later
                </button>
                <button class="btn btn-primary" id="printInvoiceBtn">
                    <i class="fas fa-print"></i> Print Order
                </button>
            </div>
        </div>
    </div>

    <!-- Fields Settings Modal -->
    <div class="modal" id="fieldsSettingsModal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto; width: 500px;">
            <h3 class="modal-title">Fields Settings</h3>
            <p style="font-size: 13px; color: var(--subtext); margin-bottom: 16px;">Toggle fields to show/hide. Required fields are always visible.</p>
            <div id="fieldsSettingsContainer" style="margin: 20px 0;">
                <!-- Fields will be populated by JavaScript -->
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeFieldsSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveFieldsSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Invoice Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <h3 class="modal-title">Order Settings</h3>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableTradeOffer"> Enable Inline Trade Offer Discount %
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableTradeOfferAmount"> Enable Inline Trade Offer Amount
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableFOC"> Enable Free Of Cost (FOC) Quantity
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableTaxation"> Enable Taxation
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableInlineCashDiscount"> Enable Inline Cash Discount %
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableInlineCashDiscountAmount"> Enable Inline Cash Discount Amount
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableInvoiceCashDiscount"> Enable Invoice-wise Cash Discount %
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableInvoiceCashDiscountAmount"> Enable Invoice-wise Cash Discount Amount
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="enableShippingFees"> Enable Shipping Fees
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal" id="addSupplierModal">
        <div class="modal-content" style="width: 90%; max-width: 1200px; height: 90vh;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="modal-title">Add New Supplier</h3>
                <button class="btn btn-secondary btn-sm" id="closeSupplierModalBtn">Close</button>
            </div>
            <iframe id="supplierIframe" style="width: 100%; height: calc(100% - 60px); border: none; border-radius: 8px;"></iframe>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal" id="addProductModal">
        <div class="modal-content" style="width: 90%; max-width: 1200px; height: 90vh;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="modal-title">Add New Product</h3>
                <button class="btn btn-secondary btn-sm" id="closeProductModalBtn">Close</button>
            </div>
            <iframe id="productIframe" style="width: 100%; height: calc(100% - 60px); border: none; border-radius: 8px;"></iframe>
        </div>
    </div>

    <script src="../../../assets/js/purchase/purchase_order/order-add-uom.js"></script>
    <script src="../../../assets/js/purchase/purchase_order/fields-settings.js"></script>
    <script src="../../../assets/js/purchase/purchase_order/order-add.js"></script>
</body>
</html>