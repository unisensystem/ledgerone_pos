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
    <title>LedgerOne ERP - Sale Invoice</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/sale_tax_invoice/pos-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <form id="invoiceForm">
            <div class="actions" id="settingsButtons" style="margin-bottom: 20px; display: none;">
                <button type="button" class="btn btn-secondary" id="invoiceSettingsBtn" tabindex="-1">
                    <i class="fas fa-cog"></i> Invoice Settings
                </button>
                <button type="button" class="btn btn-secondary" id="printSettingsBtn" tabindex="-1">
                    <i class="fas fa-cog"></i> Print Settings
                </button>
                <button type="button" class="btn btn-secondary" id="printCustomizationBtn" tabindex="-1">
                    <i class="fas fa-palette"></i> Print Customization
                </button>
                <button type="button" class="btn btn-secondary" id="fieldSettingsBtn" tabindex="-1">
                    <i class="fas fa-eye"></i> Field Settings
                </button>
                <button type="button" class="btn btn-secondary" id="shortcutSettingsBtn" tabindex="-1">
                    <i class="fas fa-keyboard"></i> Shortcut Settings
                </button>
            </div>
            
            <div class="card">
                <h2 class="card-title">Invoice Details</h2>
                <div class="form-grid">
                    <div class="form-group" id="saleDateGroup">
                        <label for="saleDate" class="required">Sale Date</label>
                        <input type="date" id="saleDate" required tabindex="-1">
                    </div>
                    <div class="form-group" id="areaCityGroup">
                        <label for="areaCity">Area/City</label>
                        <select id="areaCity" tabindex="-1">
                            <option value="">All Areas</option>
                        </select>
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
                    <div class="form-group" id="subAccountGroup">
                        <label for="subAccount">Sub Account</label>
                        <select id="subAccount" tabindex="-1">
                            <option value="">Select Sub Account</option>
                            <!-- Options loaded dynamically -->
                        </select>
                    </div>

                    <div class="form-group" id="saleOrderGroup">
                        <label for="saleOrder">Sale Order#</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search sale order..." id="saleOrderSearch" autocomplete="off">
                            <div class="dropdown-options" id="saleOrderOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="saleOrder">
                        </div>
                    </div>

                    <div class="form-group" id="companyGroup">
                        <label for="company" class="required">Company</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search company..." id="companySearch" autocomplete="off">
                            <div class="dropdown-options" id="companyOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="company" required>
                        </div>
                        <div class="error-message" id="companyError">Please select a company</div>
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

                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Items</h2>
                <div id="priceHistoryContainer" style="display: none; margin-bottom: 16px; padding: 8px 12px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--primary); width: fit-content;">
                    <strong style="color: var(--heading); font-size: 12px;">Last 3 Sale Prices:</strong>
                    <div id="priceHistoryContent" style="margin-top: 4px; font-size: 13px; color: var(--body);"></div>
                </div>
                <div id="stockContainer" style="display: none; margin-bottom: 16px; padding: 8px 12px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--success); width: fit-content;">
                    <div id="stockContent" style="font-size: 13px; color: var(--body);"></div>
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
                                <th width="10%">Scheme</th>
                                <!-- Dynamic unit columns will be inserted here -->
                                <th width="8%" class="price-cell"><span id="salePriceLabel">Sale Price</span></th>
                                <th width="8%" class="gross-cell"><span id="grossAmountLabel">Gross Amount</span></th>
                                <th width="5%" class="disc-percent-cell">Disc %</th>
                                <th width="8%" class="disc-amount-cell"><span id="discountAmountLabel">Disc Amt</span></th>
                                <th width="8%" class="to-amount-cell">T.O Amt</th>
                                <th width="5%" class="tax-percent-cell">Tax %</th>
                                <th width="8%" class="tax-amount-cell">Tax Amt</th>
                                <th width="8%" class="foc-cell">FOC Qty</th>
                                <th width="8%" class="net-cell"><span id="netAmountLabel">Net Amount</span></th>
                                <th width="3%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be added dynamically -->
                        </tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <th style="text-align: left;">Totals</th>
                                <th style="text-align: left;"></th>
                                <th style="text-align: left;"></th>
                                <!-- Dynamic unit totals will be inserted here -->
                                <th id="totalSalePrice" class="price-cell">0.00</th>
                                <th id="totalGrossAmount" class="gross-cell">0.00</th>
                                <th class="disc-percent-cell"></th>
                                <th id="totalDiscountAmountItems" class="disc-amount-cell">0.00</th>
                                <th id="totalTradeOfferAmount" class="to-amount-cell">0.00</th>
                                <th class="tax-percent-cell"></th>
                                <th id="totalTaxAmount" class="tax-amount-cell">0.00</th>
                                <th id="totalFocQty" class="foc-cell">0.00</th>
                                <th id="totalNetAmountItems" class="net-cell">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn-secondary" id="addRowBtn" tabindex="-1">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Invoice Summary</h2>
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
                        <span class="summary-label" id="shippingFeesLabel">Shipping Fees</span>
                        <input type="number" id="shippingFees" class="table-input" min="0" step="0.01" value="0">
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="netAmountSummaryLabel">Net Amount</span>
                        <span class="summary-value" id="netAmount">0.00</span>
                    </div>
                    <!-- Dynamic Invoice-Level Taxes will be inserted here -->
                    <div id="invoiceLevelTaxesContainer"></div>
                    <div class="summary-item">
                        <span class="summary-label" id="netReceivableLabel">Net Receivable</span>
                        <span class="summary-value" id="netReceivable" style="color: var(--primary); font-size: 16px; font-weight: 700;">0.00</span>
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
                        <span class="summary-label" id="amountPaidLabel">Amount Paid</span>
                        <div style="display: flex; gap: 4px; align-items: center;">
                            <input type="number" id="amountPaid" class="table-input" min="0" step="0.01" value="0" style="flex: 1;">
                            <button type="button" class="btn btn-secondary" id="copyNetAmountBtn" title="Copy Net Amount" style="height: 40px; padding: 0 12px;">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 4px; font-size: 11px;">
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
                                <input type="radio" name="autoFillAmountPaid" value="yes" style="width: auto; height: auto;">
                                <span>Auto</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
                                <input type="radio" name="autoFillAmountPaid" value="no" checked style="width: auto; height: auto;">
                                <span>Manual</span>
                            </label>
                        </div>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label" id="remainingBalanceLabel">Remaining Balance</span>
                        <input type="text" id="remainingBalance" class="table-input" value="0.00" readonly tabindex="-1">
                    </div>
                    <div class="summary-item full-width">
                        <span class="summary-label">Remarks</span>
                        <textarea id="remarks" placeholder="Enter any additional remarks" tabindex="-1" style="width: 100%; min-height: 60px;"></textarea>
                    </div>
                </div>
            </div>

            <div class="actions">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 16px; background: var(--surface-2); border: 1px solid var(--border-strong); border-radius: var(--radius); height: 32px; font-size: 13px; font-weight: 500; transition: all 0.2s;" id="toggleSettingsLabel">
                    <input type="checkbox" id="toggleSettingsBtn" style="width: auto;">
                    <span>Show Settings Buttons</span>
                </label>
                <button type="button" class="btn btn-secondary" id="resetBtn" tabindex="-1">
                    <i class="fas fa-redo"></i> Reset Invoice
                </button>
                <button type="button" class="btn btn-secondary" id="salesReturnBtn" tabindex="-1" onclick="window.location.href='../sale_return/counter-return.php'">
                    <i class="fas fa-undo"></i> Record Sales Return
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn" tabindex="-1">
                    <i class="fas fa-save"></i> Save Invoice
                </button>
            </div>
        </form>
    </div>

    <!-- Invoice Settings Modal -->
    <div class="modal" id="invoiceSettingsModal">
        <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title">Invoice Settings</h3>
            <div style="margin: 20px 0;">
                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-default);">
                    <p style="margin-bottom: 12px; font-weight: 600; font-size: 14px;">Default Scheme:</p>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="defaultScheme" value="sale_on_tp" id="schemeDefault" style="width: auto;">
                        <span>Sale On TP</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="defaultScheme" value="less" id="schemeLess" style="width: auto;">
                        <span>Less</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="defaultScheme" value="less_special" id="schemeLessSpecial" style="width: auto;">
                        <span>Less Special</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="defaultScheme" value="given" id="schemeGiven" style="width: auto;">
                        <span>Given</span>
                    </label>
                </div>

                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-default);">
                    <p style="margin-bottom: 12px; font-weight: 600; font-size: 14px;">Sale Price (₨):</p>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="salePriceSetting" value="trade_price" id="salePriceTP" style="width: auto;">
                        <span>Trade Price (TP)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="salePriceSetting" value="mrp" id="salePriceMRP" style="width: auto;">
                        <span>Maximum Retail Price (MRP)</span>
                    </label>
                </div>

                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-default);">
                    <p style="margin-bottom: 12px; font-weight: 600; font-size: 14px;">Product Filtering:</p>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="productFilteringMode" value="showAll" id="productFilterShowAll" checked style="width: auto;">
                        <span>Show All Products</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="productFilteringMode" value="salesOfficerFilter" id="productFilterSalesOfficer" style="width: auto;">
                        <span>Sales Officer Product Filtering</span>
                    </label>
                </div>
                
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
                    <span>Enable Tax % and Tax Amt</span>
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
                    <span>Enable Invoice-wise Cash Discount %</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableInvoiceCashDiscountAmount" style="width: auto;">
                    <span>Enable Invoice-wise Cash Discount Amount</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableShippingFees" style="width: auto;">
                    <span>Enable Shipping Fees</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enablePrintQRCode" style="width: auto;">
                    <span>Enable QR Code on Print</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="enableAmountPaidPaymentMethod" style="width: auto;">
                    <span>Enable Amount Paid and Payment Method</span>
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeInvoiceSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveInvoiceSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Other Modals (Print Settings, Field Settings, etc.) -->
    <!-- [Rest of the modals remain the same as before] -->

    <script src="../../../assets/js/sale/sale_tax_invoice/pos-add-uom.js?v=<?php echo time(); ?>&debug=1"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/pos-add-scheme.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/invoice-level-taxes-dynamic.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/pos-tax-calculation.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/withholding-tax.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/stock-validation.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/pos-add.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/tax-integration.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_tax_invoice/supplier-product-filter.js?v=<?php echo time(); ?>"></script>
</body>
</html>
