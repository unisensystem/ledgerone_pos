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
    <title>LedgerOne ERP - Sale Return</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/sale_return/return-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Sale Return</h1>
        </div>

        <form id="invoiceForm">
            <div class="actions" style="margin-bottom: 20px;">
                <button type="button" class="btn btn-secondary" id="invoiceSettingsBtn">
                    <i class="fas fa-cog"></i> Invoice Settings
                </button>
                <button type="button" class="btn btn-secondary" id="printSettingsBtn">
                    <i class="fas fa-cog"></i> Print Settings
                </button>
            </div>
            
            <div class="card">
                <h2 class="card-title">Return Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Price Type</label>
                        <div style="display: flex; gap: 20px; align-items: center; height: 40px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                <input type="radio" name="priceType" value="mrp" style="width: auto;">
                                <span>Use Maximum Retail Price (MRP)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                <input type="radio" name="priceType" value="tp" checked style="width: auto;">
                                <span>Use Trade Price (TP)</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="company" class="required">Company</label>
                        <select id="company" required tabindex="-1">
                            <option value="">Select Company</option>
                            <!-- Options loaded dynamically -->
                        </select>
                        <div class="error-message" id="companyError">Please select a company</div>
                    </div>
                    <div class="form-group" id="billNoGroup">
                        <label for="billNo" class="required">Bill No</label>
                        <input type="text" id="billNo" value="Automatically generated" readonly required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="saleDate" class="required">Return Date</label>
                        <input type="date" id="saleDate" required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="saleInvoice">Sale Invoice #</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search invoice..." id="saleInvoiceSearch" autocomplete="off">
                            <div class="dropdown-options" id="saleInvoiceOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="saleInvoice">
                        </div>
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
                    <div class="form-group full-width" id="customerAddressGroup">
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
                    <div class="form-group" id="subAccountGroup">
                        <label for="subAccount">Sub Account</label>
                        <select id="subAccount" tabindex="-1">
                            <option value="">Select Sub Account</option>
                            <!-- Options loaded dynamically -->
                        </select>
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
                <div id="stockContainer" style="display: none; margin-bottom: 16px; padding: 12px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--success);">
                    <strong style="color: var(--heading);">Available Stock:</strong>
                    <div id="stockContent" style="margin-top: 8px; font-size: 13px; color: var(--body);"></div>
                </div>
                <div class="actions" style="margin-bottom: 16px; justify-content: flex-start;">
                    <button type="button" class="btn btn-secondary" onclick="openOverlay('../../inventory/products/product-add.php')" tabindex="-1">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                    <button type="button" class="btn btn-secondary" id="bulkStatusBtn" tabindex="-1">
                        <i class="fas fa-check-double"></i> Set All Status
                    </button>
                </div>
                <div class="table-container">
                    <table id="itemsTable">
                        <thead>
                            <tr>
                                <th width="3%">S#</th>
                                <th width="15%">Product Code / Name</th>
                                <th width="6%">Unit</th>
                                <th width="5%">Qty</th>
                                <th width="4%">Pcs</th>
                                <th width="4%">Ctn</th>
                                <th width="4%">Dz</th>
                                <th width="8%"><span id="salePriceLabel">Sale Price</span></th>
                                <th width="8%"><span id="grossAmountLabel">Gross Amount</span></th>
                                <th width="5%">Disc %</th>
                                <th width="8%"><span id="discountAmountLabel">Disc Amt</span></th>
                                <th width="5%">T.O Disc %</th>
                                <th width="8%">T.O Amt</th>
                                <th width="5%">GST %</th>
                                <th width="8%">GST Amt</th>
                                <th width="5%">FOC Qty</th>
                                <th width="7%">Status</th>
                                <th width="8%"><span id="netAmountLabel">Net Amount</span></th>
                                <th width="3%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be added dynamically -->
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <th colspan="3">Totals</th>
                                <th id="totalQty">0.00</th>
                                <th id="totalPcs">0</th>
                                <th id="totalCtn">0</th>
                                <th id="totalDz">0</th>
                                <th id="totalSalePrice">0.00</th>
                                <th id="totalGrossAmount">0.00</th>
                                <th></th>
                                <th id="totalDiscountAmountItems">0.00</th>
                                <th></th>
                                <th id="totalTradeOfferAmount">0.00</th>
                                <th></th>
                                <th id="totalGstAmount">0.00</th>
                                <th id="totalFocQty">0.00</th>
                                <th></th>
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
                <h2 class="card-title">Return Summary</h2>
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
                    <div class="summary-item">
                        <span class="summary-label">Payment Method</span>
                        <select id="paymentMethod" class="table-input" tabindex="-1">
                            <option value="">Select Payment Method</option>
                            <option value="cash" selected>Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="summary-item" id="bankAccountContainer" style="display: none;">
                        <span class="summary-label">Bank Account</span>
                        <select id="bankAccount" class="table-input" tabindex="-1">
                            <option value="">Select Bank Account</option>
                            <!-- Options loaded dynamically -->
                        </select>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="amountPaidLabel">Amount Refunded</span>
                        <input type="number" id="amountPaid" class="table-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="remainingBalanceLabel">Remaining Balance</span>
                        <input type="text" id="remainingBalance" class="table-input" value="0.00" readonly tabindex="-1">
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" id="resetBtn" tabindex="-1">
                    <i class="fas fa-redo"></i> Reset Return
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Return
                </button>
            </div>
        </form>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <h3 class="modal-title">Return Saved Successfully!</h3>
            <p>What would you like to do next?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="printLaterBtn">
                    Print Later
                </button>
                <button class="btn btn-primary" id="printInvoiceBtn">
                    <i class="fas fa-print"></i> Print Return
                </button>
            </div>
        </div>
    </div>

    <!-- Print Options Modal -->
    <div class="modal" id="printOptionsModal">
        <div class="modal-content">
            <h3 class="modal-title">Select Print Format</h3>
            <p>Choose how you want to print the return:</p>
            <div class="modal-actions" style="flex-direction: column; gap: 10px;">
                <button class="btn btn-primary" id="printFullBtn" style="width: 100%;">
                    <i class="fas fa-file-invoice"></i> Print Full Return
                </button>
                <button class="btn btn-secondary" id="printThermalBtn" style="width: 100%;">
                    <i class="fas fa-receipt"></i> Print Thermal Return
                </button>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                    <input type="checkbox" id="rememberPrintChoice" style="width: auto;">
                    <span>Remember my choice</span>
                </label>
            </div>
        </div>
    </div>



    <!-- Invoice Settings Modal -->
    <div class="modal" id="invoiceSettingsModal">
        <div class="modal-content">
            <h3 class="modal-title">Return Settings</h3>
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
                    <span>Enable Return-wise Cash Discount %</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableInvoiceCashDiscountAmount" style="width: auto;">
                    <span>Enable Return-wise Cash Discount Amount</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableShippingFees" style="width: auto;">
                    <span>Enable Shipping Fees</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enablePcs" style="width: auto;">
                    <span>Enable Pcs Column</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableCtn" style="width: auto;">
                    <span>Enable Ctn Column</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableDz" style="width: auto;">
                    <span>Enable Dz Column</span>
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeInvoiceSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveInvoiceSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Print Settings Modal -->
    <div class="modal" id="printSettingsModal">
        <div class="modal-content">
            <h3 class="modal-title">Print Settings</h3>
            <p>Configure your print preferences:</p>
            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="enableRememberPrint" style="width: auto;">
                    <span>Remember my print choice</span>
                </label>
                <div id="printPreferenceSection" style="margin-top: 15px; display: none;">
                    <p style="margin-bottom: 10px; font-size: 14px;">Current preference:</p>
                    <select id="printPreference" class="table-input" style="width: 100%;">
                        <option value="full">Full Invoice</option>
                        <option value="thermal">Thermal Invoice</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closePrintSettingsBtn">Close</button>
                <button class="btn btn-primary" id="savePrintSettingsBtn">Save Settings</button>
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



    <script src="../../../assets/js/sale/sale_return/return-add.js"></script>
</body>
</html>