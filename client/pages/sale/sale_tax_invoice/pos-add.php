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
    <title>LedgerOne ERP - Sale Tax Invoice</title>
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
                        <label for="areaCitySearch">Area/City</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" placeholder="Search area or city..." id="areaCitySearch" autocomplete="off" tabindex="-1">
                            <div class="dropdown-options" id="areaCityOptions"></div>
                            <input type="hidden" id="areaCity">
                        </div>
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
                    <div class="form-group" id="containerGroup">
                        <label for="container">Container</label>
                        <select id="container" tabindex="-1">
                            <option value="">Select Container</option>
                            <!-- Options loaded dynamically -->
                        </select>
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
                    <div class="form-group" id="invoiceTypeGroup">
                        <label for="invoiceType">Invoice Type</label>
                        <select id="invoiceType" tabindex="-1">
                            <option value="Credit">Credit</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div class="form-group" id="dueDateGroup">
                        <label for="dueDate">Due Date</label>
                        <input type="date" id="dueDate" tabindex="-1">
                    </div>
                    <div class="form-group" id="biltyNoGroup">
                        <label for="biltyNo">Bilty No</label>
                        <input type="text" id="biltyNo" placeholder="Enter bilty number" tabindex="-1">
                    </div>
                    <div class="form-group" id="transportNameGroup">
                        <label for="transportName">Transport Name</label>
                        <input type="text" id="transportName" placeholder="Enter transport name" tabindex="-1">
                    </div>
                    <div class="form-group" id="projectGroup">
                        <label for="project">Project</label>
                        <select id="project" tabindex="-1">
                            <option value="">Select Project</option>
                        </select>
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
                    <!-- Dynamic Invoice-Level Taxes will be inserted here -->
                    <div id="invoiceLevelTaxesContainer"></div>
                    <div class="summary-item">
                        <span class="summary-label" id="netAmountSummaryLabel">Net Amount</span>
                        <span class="summary-value" id="netAmount">0.00</span>
                    </div>
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

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <h3 class="modal-title">Invoice Saved Successfully!</h3>
            <p>What would you like to do next?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="printLaterBtn">
                    Print Later
                </button>
                <button class="btn btn-primary" id="printInvoiceBtn">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
            </div>
        </div>
    </div>

    <!-- Print Options Modal -->
    <div class="modal" id="printOptionsModal">
        <div class="modal-content">
            <h3 class="modal-title">Select Print Format</h3>
            <p>Choose how you want to print the invoice:</p>
            <div class="modal-actions" style="flex-direction: column; gap: 10px;">
                <button class="btn btn-primary" id="printFullBtn" style="width: 100%;">
                    <i class="fas fa-file-invoice"></i> Print Full Invoice
                </button>
                <button class="btn btn-secondary" id="printThermalBtn" style="width: 100%;">
                    <i class="fas fa-receipt"></i> Print Thermal Invoice
                </button>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                    <input type="checkbox" id="rememberPrintChoice" style="width: auto;">
                    <span>Remember my choice</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Draft Invoices Modal -->
    <div class="modal" id="draftsModal">
        <div class="modal-content" style="width: 800px; max-width: 95%;">
            <h3 class="modal-title">Draft Invoices</h3>
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
        <div class="modal-content" style="max-width: 1000px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border-default);">
                <h3 class="modal-title" style="margin: 0;">Invoice Settings</h3>
                <button type="button" class="btn btn-secondary btn-sm" id="closeInvoiceSettingsBtn" style="padding: 6px 12px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div style="margin: 0;">
                <!-- Default Scheme Section -->
                <div style="margin-bottom: 28px; padding: 16px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--primary);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-tag" style="color: var(--primary); font-size: 16px;"></i>
                        <h4 style="margin: 0; color: var(--heading); font-size: 15px; font-weight: 600;">Default Scheme</h4>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="defaultScheme" value="sale_on_tp" id="schemeDefault" style="width: auto;">
                            <span style="font-size: 13px;">Sale On TP</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="defaultScheme" value="less" id="schemeLess" style="width: auto;">
                            <span style="font-size: 13px;">Less</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="defaultScheme" value="less_special" id="schemeLessSpecial" style="width: auto;">
                            <span style="font-size: 13px;">Less Special</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="defaultScheme" value="given" id="schemeGiven" style="width: auto;">
                            <span style="font-size: 13px;">Given</span>
                        </label>
                    </div>
                </div>

                <!-- Sale Price Section -->
                <div style="margin-bottom: 28px; padding: 16px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--success);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-rupee-sign" style="color: var(--success); font-size: 16px;"></i>
                        <h4 style="margin: 0; color: var(--heading); font-size: 15px; font-weight: 600;">Sale Price (₨)</h4>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="salePriceSetting" value="trade_price" id="salePriceTP" style="width: auto;">
                            <span style="font-size: 13px;">Trade Price (TP)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="salePriceSetting" value="mrp" id="salePriceMRP" style="width: auto;">
                            <span style="font-size: 13px;">Maximum Retail Price (MRP)</span>
                        </label>
                    </div>
                </div>

                <!-- Product Filtering Section -->
                <div style="margin-bottom: 28px; padding: 16px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--success);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-filter" style="color: var(--success); font-size: 16px;"></i>
                        <h4 style="margin: 0; color: var(--heading); font-size: 15px; font-weight: 600;">Product Filtering</h4>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="productFilteringMode" value="showAll" id="productFilteringShowAll" style="width: auto;" checked>
                            <div>
                                <span style="font-size: 13px; font-weight: 500; display: block;">Show All Products</span>
                                <span style="font-size: 11px; color: var(--subtext);">Display all available products</span>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="radio" name="productFilteringMode" value="salesOfficerFilter" id="productFilteringSalesOfficer" style="width: auto;">
                            <div>
                                <span style="font-size: 13px; font-weight: 500; display: block;">Sales Officer Filter</span>
                                <span style="font-size: 11px; color: var(--subtext);">Filter by selected officer</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Item Display Section -->
                <div style="margin-bottom: 28px; padding: 16px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--warning);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-list" style="color: var(--warning); font-size: 16px;"></i>
                        <h4 style="margin: 0; color: var(--heading); font-size: 15px; font-weight: 600;">Item Display Options</h4>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableTradeOfferAmount" style="width: auto;">
                            <span style="font-size: 13px;">Trade Offer Amount</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableFOC" style="width: auto;">
                            <span style="font-size: 13px;">FOC Quantity</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableCashDiscountPercent" style="width: auto;">
                            <span style="font-size: 13px;">Cash Discount %</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableCashDiscountAmount" style="width: auto;">
                            <span style="font-size: 13px;">Cash Discount Amt</span>
                        </label>
                    </div>
                </div>

                <!-- Invoice Summary Section -->
                <div style="margin-bottom: 28px; padding: 16px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--info);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-receipt" style="color: var(--info); font-size: 16px;"></i>
                        <h4 style="margin: 0; color: var(--heading); font-size: 15px; font-weight: 600;">Invoice Summary Options</h4>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableInvoiceCashDiscountPercent" style="width: auto;">
                            <span style="font-size: 13px;">Invoice Discount %</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableInvoiceCashDiscountAmount" style="width: auto;">
                            <span style="font-size: 13px;">Invoice Discount Amt</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableShippingFees" style="width: auto;">
                            <span style="font-size: 13px;">Shipping Fees</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                            <input type="checkbox" id="enableAmountPaidPaymentMethod" style="width: auto;">
                            <span style="font-size: 13px;">Amount Paid & Method</span>
                        </label>
                    </div>
                </div>

                <!-- Print Section -->
                <div style="padding: 16px; background: var(--surface-2); border-radius: var(--radius); border-left: 4px solid var(--error);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-print" style="color: var(--error); font-size: 16px;"></i>
                        <h4 style="margin: 0; color: var(--heading); font-size: 15px; font-weight: 600;">Print Options</h4>
                    </div>
                    
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: var(--surface-1); border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='var(--surface-0)'" onmouseout="this.style.background='var(--surface-1)'">
                        <input type="checkbox" id="enablePrintQRCode" style="width: auto;">
                        <span style="font-size: 13px;">Enable QR Code on Print</span>
                    </label>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 28px; padding-top: 16px; border-top: 1px solid var(--border-default);">
                <button type="button" class="btn btn-secondary" id="closeInvoiceSettingsBtn2">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveInvoiceSettingsBtn"><i class="fas fa-save"></i> Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Print Settings Modal -->
    <div class="modal" id="printSettingsModal">
        <div class="modal-content" style="max-width: 1000px; max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title">Print Settings</h3>
            <p>Configure your print preferences:</p>
            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 15px;">
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
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-default);">
                    <p style="margin-bottom: 10px; font-size: 14px; font-weight: 600;">Totals Section Layout:</p>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="totalsLayout" value="vertical" id="totalsVertical" checked style="width: auto;">
                        <span>Vertical (Default)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="totalsLayout" value="horizontal-2" id="totalsHorizontal2" style="width: auto;">
                        <span>Horizontal - 2 Columns</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="totalsLayout" value="horizontal-3" id="totalsHorizontal3" style="width: auto;">
                        <span>Horizontal - 3 Columns</span>
                    </label>
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-default);">
                    <p style="margin-bottom: 10px; font-size: 14px; font-weight: 600;">Customize Labels:</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Total Bill:</label>
                            <input type="text" id="labelTotalBill" class="table-input" placeholder="Total Bill" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Discount Amount:</label>
                            <input type="text" id="labelDiscountAmount" class="table-input" placeholder="Discount Amount" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Shipping Fees:</label>
                            <input type="text" id="labelShippingFees" class="table-input" placeholder="Shipping Fees" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Net Amount:</label>
                            <input type="text" id="labelNetAmount" class="table-input" placeholder="Net Amount" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Amount Paid:</label>
                            <input type="text" id="labelAmountPaid" class="table-input" placeholder="Amount Paid" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Payment Method:</label>
                            <input type="text" id="labelPaymentMethod" class="table-input" placeholder="Payment Method" style="width: 100%;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: var(--subtext);">Remaining Balance:</label>
                            <input type="text" id="labelRemainingBalance" class="table-input" placeholder="Remaining Balance" style="width: 100%;">
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-default);">
                    <p style="margin-bottom: 10px; font-size: 14px; font-weight: 600;">Hide Fields on Print:</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintCustomerPhone" style="width: auto;">
                            <span>Customer Phone</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintCustomerEmail" style="width: auto;">
                            <span>Customer Email</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintCustomerAddress" style="width: auto;">
                            <span>Customer Address</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintPreviousBalance" style="width: auto;">
                            <span>Previous Balance</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintBranch" style="width: auto;">
                            <span>Branch</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintCurrency" style="width: auto;">
                            <span>Currency</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintSalesOfficer" style="width: auto;">
                            <span>Sales Officer</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintBiltyNo" style="width: auto;">
                            <span>Bilty No</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintTransport" style="width: auto;">
                            <span>Transport</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintRemarks" style="width: auto;">
                            <span>Remarks</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintTotalBill" style="width: auto;">
                            <span>Total Bill</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintNetAmount" style="width: auto;">
                            <span>Net Amount</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintPaymentMethod" style="width: auto;">
                            <span>Payment Method</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintAmountPaid" style="width: auto;">
                            <span>Amount Paid</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintRemainingBalance" style="width: auto;">
                            <span>Remaining Balance</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintAmountInWords" style="width: auto;">
                            <span>Amount in Words</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintSignatures" style="width: auto;">
                            <span>Signatures Section</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintGeneratedBy" style="width: auto;">
                            <span>Generated By</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="hidePrintGeneratedOn" style="width: auto;">
                            <span>Generated On</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closePrintSettingsBtn">Close</button>
                <button class="btn btn-primary" id="savePrintSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Child Display Modal -->
    <div class="modal" id="childDisplayModal">
        <div class="modal-content">
            <h3 class="modal-title">Child Products Display</h3>
            <p>Choose how child products should appear on print:</p>
            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="radio" name="childDisplay" value="separate" id="childDisplaySeparate" checked style="width: auto;">
                    <span>Separate Rows (Default)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="childDisplay" value="inline" id="childDisplayInline" style="width: auto;">
                    <span>Inline with Parent (e.g., Biryani Masala: 5 | Korma Masala: 4)</span>
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeChildDisplayBtn">Close</button>
                <button class="btn btn-primary" id="saveChildDisplayBtn">Save</button>
            </div>
        </div>
    </div>

    <!-- Print Customization Modal -->
    <div class="modal" id="printCustomizationModal">
        <div class="modal-content" style="max-width: 1200px; width: 95%; max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title">Print Customization</h3>
            <p style="margin-bottom: 20px; color: var(--subtext); font-size: 14px;">Drag columns to reorder   Click labels to edit   Changes apply to printed invoices</p>
            
            <div style="display: grid; grid-template-columns: 400px 1fr; gap: 24px; margin-bottom: 24px;">
                <div>
                    <h4 style="margin-bottom: 16px; color: var(--heading); font-size: 16px;">Column Order & Labels</h4>
                    <div id="columnsList" style="border: 1px solid var(--border-default); border-radius: var(--radius); padding: 16px; background: var(--surface-1); min-height: 500px;">
                        <!-- Columns will be populated here -->
                    </div>
                </div>
                <div>
                    <h4 style="margin-bottom: 16px; color: var(--heading); font-size: 16px;">Preview</h4>
                    <div style="border: 1px solid var(--border-default); border-radius: var(--radius); padding: 16px; background: var(--surface-0); overflow-x: auto; min-height: 500px;">
                        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                            <thead>
                                <tr id="previewHeader" style="background: var(--surface-2);">
                                    <!-- Preview headers will be populated here -->
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="20" style="padding: 40px; text-align: center; color: var(--subtext); font-size: 14px;">Drag and edit columns on the left to see preview</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-secondary" id="resetPrintCustomizationBtn"><i class="fas fa-undo"></i> Reset to Default</button>
                <button class="btn btn-secondary" id="closePrintCustomizationBtn">Close</button>
                <button class="btn btn-primary" id="savePrintCustomizationBtn"><i class="fas fa-save"></i> Save Customization</button>
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

    <!-- Shortcut Settings Modal -->
    <div class="modal" id="shortcutSettingsModal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title">Shortcut Settings</h3>
            <p>Configure keyboard shortcuts for quick actions:</p>
            <div style="margin: 20px 0;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center;">
                    <label>Add New Row:</label>
                    <input type="text" id="shortcutAddRow" class="table-input" placeholder="e.g., F2">
                    
                    <label>Save Invoice:</label>
                    <input type="text" id="shortcutSave" class="table-input" placeholder="e.g., F9">
                    
                    <label>Save as Draft:</label>
                    <input type="text" id="shortcutDraft" class="table-input" placeholder="e.g., F8">
                    
                    <label>Reset Invoice:</label>
                    <input type="text" id="shortcutReset" class="table-input" placeholder="e.g., F5">
                    
                    <label>Focus Customer:</label>
                    <input type="text" id="shortcutCustomer" class="table-input" placeholder="e.g., F3">
                    
                    <label>Focus Product Search:</label>
                    <input type="text" id="shortcutProduct" class="table-input" placeholder="e.g., F4">
                    
                    <label>View Draft Invoices:</label>
                    <input type="text" id="shortcutViewDrafts" class="table-input" placeholder="e.g., F6">
                    
                    <label>Close Modal/Overlay:</label>
                    <input type="text" id="shortcutCloseModal" class="table-input" placeholder="e.g., Escape">
                    
                    <label>Delete Row:</label>
                    <input type="text" id="shortcutDeleteRow" class="table-input" placeholder="e.g., Delete">
                    
                    <label>Move Forward:</label>
                    <input type="text" id="shortcutMoveForward" class="table-input" placeholder="e.g., ArrowRight">
                    
                    <label>Move Backward:</label>
                    <input type="text" id="shortcutMoveBackward" class="table-input" placeholder="e.g., ArrowLeft">
                    
                    <label>Add Customer:</label>
                    <input type="text" id="shortcutAddCustomer" class="table-input" placeholder="e.g., Ctrl+N">
                    
                    <label>Sales Return:</label>
                    <input type="text" id="shortcutSalesReturn" class="table-input" placeholder="e.g., F7">
                    
                    <label>Next Field:</label>
                    <input type="text" id="shortcutNextField" class="table-input" placeholder="e.g., Tab">
                    
                    <label>Previous Field:</label>
                    <input type="text" id="shortcutPrevField" class="table-input" placeholder="e.g., Shift+Tab">
                </div>
                <p style="margin-top: 16px; font-size: 12px; color: var(--subtext);">Click on an input and press the desired key combination</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="resetShortcutsBtn">Reset to Default</button>
                <button class="btn btn-secondary" id="closeShortcutSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveShortcutSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Field Settings Modal -->
    <div class="modal" id="fieldSettingsModal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <h3 class="modal-title">Field Settings</h3>
            <p>Select which fields to hide:</p>
            <div style="margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideBillNoStandard" style="width: auto;">
                    <span>Hide Bill No</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideSaleDateStandard" style="width: auto;">
                    <span>Hide Sale Date</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hidePreviousBalanceStandard" style="width: auto;">
                    <span>Hide Customer's Previous Balance</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideSubAccountStandard" style="width: auto;">
                    <span>Hide Sub Account</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideCustomerAddressStandard" style="width: auto;">
                    <span>Hide Customer Address</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideSaleOrderStandard" style="width: auto;">
                    <span>Hide Sale Order#</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideBranchStandard" style="width: auto;">
                    <span>Hide Branch</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideCurrencyStandard" style="width: auto;">
                    <span>Hide Currency</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideSalesOfficerStandard" style="width: auto;">
                    <span>Hide Sales Officer</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideBiltyNoStandard" style="width: auto;">
                    <span>Hide Bilty No</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideTransportNameStandard" style="width: auto;">
                    <span>Hide Transport Name</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideRemarksStandard" style="width: auto;">
                    <span>Hide Remarks</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="hideProjectStandard" style="width: auto;">
                    <span>Hide Project</span>
                </label>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeFieldSettingsBtn">Close</button>
                <button class="btn btn-primary" id="saveFieldSettingsBtn">Save Settings</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/sale/sale_tax_invoice/load-projects.js?v=<?php echo time(); ?>"></script>
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