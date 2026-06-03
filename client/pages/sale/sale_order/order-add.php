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
    <title>LedgerOne ERP - Sale Order</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/sale_order/order-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Sale Order</h1>
        </div>

        <form id="invoiceForm">
            <div class="actions" style="margin-bottom: 20px;">
                <button type="button" class="btn btn-secondary" id="viewDraftsBtn">
                    <i class="fas fa-file-alt"></i> View Draft Orders
                </button>
                <button type="button" class="btn btn-secondary" id="fieldsSettingsBtn">
                    <i class="fas fa-columns"></i> Fields Settings
                </button>
                <button type="button" class="btn btn-secondary" id="invoiceSettingsBtn">
                    <i class="fas fa-cog"></i> Order Settings
                </button>
                <button type="button" class="btn btn-secondary" id="printSettingsBtn">
                    <i class="fas fa-cog"></i> Print Settings
                </button>
            </div>
            
            <div class="card">
                <h2 class="card-title">Order Details</h2>
                <div class="form-grid">
                    <div class="form-group" id="companyGroup">
                        <label for="company" class="required">Company</label>
                        <select id="company" required tabindex="-1">
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    <div class="form-group" id="billNoGroup">
                        <label for="billNo" class="required">Bill No</label>
                        <input type="text" id="billNo" value="Automatically generated" readonly required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="saleDate" class="required">Sale Date</label>
                        <input type="date" id="saleDate" required tabindex="-1">
                    </div>
                    <div class="form-group" id="previousBalanceGroup">
                        <label for="previousBalance">Customer's Previous Balance</label>
                        <input type="text" id="previousBalance" value="0.00" readonly tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="customerCode" class="required">Customer Code</label>
                        <div style="display: flex; gap: 8px; align-items: flex-start;">
                            <div class="searchable-dropdown" style="flex: 1;">
                                <input type="text" class="search-input" placeholder="Search customer code..." id="customerCodeSearch" autocomplete="off">
                                <div class="dropdown-options" id="customerCodeOptions">
                                    <!-- Options loaded dynamically -->
                                </div>
                                <input type="hidden" id="customerCode" required>
                            </div>
                            <button type="button" class="btn btn-secondary" style="height: 40px; padding: 0 12px;" onclick="openOverlay('../../customer_supplier/customers/customer-add.php')" title="Add New Customer">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="error-message" id="customerCodeError">Please select a customer code</div>
                    </div>
                    <div class="form-group" id="customerAddressGroup">
                        <label>Customer Address</label>
                        <div id="customerAddress" style="padding: 10px; background: var(--surface-2); border-radius: var(--radius); min-height: 40px; color: var(--subtext);">Select a customer to view address</div>
                    </div>

                    <div class="form-group" id="branchGroup">
                        <label for="branch" class="required">Branch</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search branch..." id="branchSearch" autocomplete="off">
                            <div class="dropdown-options" id="branchOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="branch" required>
                        </div>
                        <div class="error-message" id="branchError">Please select a branch</div>
                    </div>
                    <div class="form-group" id="currencyGroup">
                        <label for="currency" class="required">Currency</label>
                        <select id="currency" required tabindex="-1">
                            <option value="">Select Currency</option>
                            <!-- Options loaded dynamically -->
                        </select>
                        <div class="error-message" id="currencyError">Please select a currency</div>
                    </div>
                    <div class="form-group" id="salesOfficerGroup">
                        <label for="salesOfficer">Sales Officer</label>
                        <select id="salesOfficer" tabindex="-1">
                            <option value="">Select Sales Officer</option>
                            <!-- Options loaded dynamically -->
                        </select>
                    </div>
                    <div class="form-group" id="supplierManGroup">
                        <label for="supplierMan">Supplier Man</label>
                        <select id="supplierMan" tabindex="-1">
                            <option value="">Select Supplier Man</option>
                            <!-- Options loaded dynamically -->
                        </select>
                    </div>
                    <div class="form-group" id="biltyNoGroup">
                        <label for="biltyNo">Bilty No</label>
                        <input type="text" id="biltyNo" placeholder="Enter bilty number" tabindex="-1">
                    </div>
                    <div class="form-group" id="transportNameGroup">
                        <label for="transportName">Transport Name</label>
                        <input type="text" id="transportName" placeholder="Enter transport name" tabindex="-1">
                    </div>
                    <div class="form-group full-width" id="remarksGroup">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" placeholder="Enter any additional remarks" tabindex="-1"></textarea>
                    </div>

                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Items</h2>
                <div id="priceHistoryContainer" style="display: none; margin-bottom: 16px; padding: 12px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--primary);">
                    <strong style="color: var(--heading);">Last 3 Sale Prices:</strong>
                    <div id="priceHistoryContent" style="margin-top: 8px; font-size: 13px; color: var(--body);"></div>
                </div>
                <div class="actions" style="margin-bottom: 16px; justify-content: flex-start;">
                    <button type="button" class="btn btn-secondary" onclick="openOverlay('../../inventory/products/product-add.php')" tabindex="-1">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>
                <div class="table-container">
                    <table id="itemsTable">
                        <thead>
                            <tr>
                                <th width="3%">S#</th>
                                <th width="15%">Product Code / Name</th>
                                <!-- Dynamic unit headers inserted here by JS -->
                                <th width="8%" class="scheme-header">Scheme</th>
                                <th width="8%"><span id="salePriceLabel">Sale Price</span></th>
                                <th width="8%"><span id="grossAmountLabel">Gross Amount</span></th>
                                <th width="5%">Disc %</th>
                                <th width="8%"><span id="discountAmountLabel">Disc Amt</span></th>
                                <th width="8%">T.O Amt</th>
                                <th width="5%">GST %</th>
                                <th width="8%">GST Amt</th>
                                <th width="5%">FOC Qty</th>
                                <th width="8%"><span id="netAmountLabel">Net Amount</span></th>
                                <th width="3%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be added dynamically -->
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <th colspan="2">Totals</th>
                                <!-- Dynamic unit total cells inserted here by JS -->
                                <th></th>
                                <th id="totalSalePrice">0.00</th>
                                <th id="totalGrossAmount">0.00</th>
                                <th></th>
                                <th id="totalDiscountAmountItems">0.00</th>
                                <th id="totalTradeOfferAmount">0.00</th>
                                <th></th>
                                <th id="totalGstAmount">0.00</th>
                                <th id="totalFocQty">0.00</th>
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
                        <input type="number" id="totalGstPercent" class="table-input" min="0" max="100" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="totalGstAmountLabel">GST Amount</span>
                        <input type="number" id="totalGstAmountSummary" class="table-input" min="0" step="0.01" value="0">
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
                <button type="button" class="btn btn-secondary" id="saveDraftBtn">
                    <i class="fas fa-file"></i> Save as Draft
                </button>
                <button type="button" class="btn btn-secondary" id="salesReturnBtn" tabindex="-1">
                    <i class="fas fa-undo"></i> Record Sales Return
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

    <!-- Print Options Modal -->
    <div class="modal" id="printOptionsModal">
        <div class="modal-content">
            <h3 class="modal-title">Select Print Format</h3>
            <p>Choose how you want to print the order:</p>
            <div class="modal-actions" style="flex-direction: column; gap: 10px;">
                <button class="btn btn-primary" id="printFullBtn" style="width: 100%;">
                    <i class="fas fa-file-invoice"></i> Print Full Order
                </button>
                <button class="btn btn-secondary" id="printThermalBtn" style="width: 100%;">
                    <i class="fas fa-receipt"></i> Print Thermal Order
                </button>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                    <input type="checkbox" id="rememberPrintChoice" style="width: auto;">
                    <span>Remember my choice</span>
                </label>
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

    <!-- Draft Invoices Modal -->
    <div class="modal" id="draftsModal">
        <div class="modal-content" style="width: 800px; max-width: 95%;">
            <h3 class="modal-title">Draft Orders</h3>
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default);">Bill No</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default);">Date</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default);">Customer</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default);">Amount</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default);">Action</th>
                        </tr>
                    </thead>
                    <tbody id="draftsTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeDraftsBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Invoice Settings Modal -->
    <div class="modal" id="invoiceSettingsModal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title">Order Settings</h3>
            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableTradeOfferDiscount" style="width: auto;">
                    <span>Enable Inline Trade Offer Discount %</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableTradeOfferAmount" style="width: auto;">
                    <span>Enable Inline Trade Offer Amount</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableFOC" style="width: auto;">
                    <span>Enable Free Of Cost (FOC) Quantity</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableTaxation" style="width: auto;">
                    <span>Enable Taxation</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableCashDiscountPercent" style="width: auto;">
                    <span>Enable Inline Cash Discount %</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableCashDiscountAmount" style="width: auto;">
                    <span>Enable Inline Cash Discount Amount</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableInvoiceCashDiscountPercent" style="width: auto;">
                    <span>Enable Order-wise Cash Discount %</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableInvoiceCashDiscountAmount" style="width: auto;">
                    <span>Enable Order-wise Cash Discount Amount</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableShippingFees" style="width: auto;">
                    <span>Enable Shipping Fees</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableCtn" style="width: auto;">
                    <span>Enable Carton (Ctn)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableDz" style="width: auto;">
                    <span>Enable Dozen (Dz)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enablePcs" style="width: auto;">
                    <span>Enable Pieces (Pcs)</span>
                </label>
                <hr style="margin: 16px 0; border: none; border-top: 1px solid var(--border-default);">
                <p style="margin-bottom: 12px; font-weight: 600;">Sale Price Source:</p>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="radio" name="priceSource" id="useMRP" value="mrp" style="width: auto;">
                    <span>Use MRP</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="radio" name="priceSource" id="useTP" value="trade_price" style="width: auto;">
                    <span>Use Trade Price</span>
                </label>
                <hr style="margin: 16px 0; border: none; border-top: 1px solid var(--border-default);">
                <p style="margin-bottom: 12px; font-weight: 600;">Child Products Display:</p>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="radio" name="childDisplay" id="inlineChild" value="inline" style="width: auto;">
                    <span>Inline (Product: Qty | Product: Qty)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="radio" name="childDisplay" id="separateChild" value="separate" style="width: auto;">
                    <span>Separate Rows</span>
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeInvoiceSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveInvoiceSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Overlay Modal -->
    <div class="modal" id="overlayModal" style="display: none;">
        <div class="modal-content" style="width: 95%; max-width: 1200px; height: 90vh; padding: 0; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; border-bottom: 1px solid var(--border-default);">
                <h3 class="modal-title" style="margin: 0;">Add New</h3>
                <button class="btn btn-secondary btn-sm" onclick="closeOverlay()" style="padding: 8px 12px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <iframe id="overlayIframe" style="width: 100%; height: calc(100% - 60px); border: none;"></iframe>
        </div>
    </div>

    <script src="../../../assets/js/sale/sale_order/order-add-uom.js"></script>
    <script src="../../../assets/js/sale/sale_order/fields-settings.js"></script>
    <script src="../../../assets/js/sale/sale_order/order-add.js"></script>
</body>
</html>