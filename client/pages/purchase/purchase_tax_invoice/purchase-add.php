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
    <title>LedgerOne ERP - Purchase Invoice</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/purchase_invoice/purchase-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Purchase Tax Invoice</h1>
        </div>

        <form id="invoiceForm">
            <div class="card">
                <h2 class="card-title">Invoice Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="billNo" class="required">Bill No</label>
                        <input type="text" id="billNo" value="Automatically generated" readonly required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="purchaseDate" class="required">Purchase Date</label>
                        <input type="date" id="purchaseDate" required tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="supplierInvoiceNo">Supplier Invoice No</label>
                        <input type="text" id="supplierInvoiceNo" placeholder="Enter supplier invoice number">
                    </div>
                    <div class="form-group">
                        <label for="supplierInvoiceDate">Supplier Invoice Date</label>
                        <input type="date" id="supplierInvoiceDate">
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
                        <label for="purchaseOrder">Purchase Order #</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" autocomplete="off" placeholder="Search purchase order..." id="purchaseOrderSearch">
                            <div class="dropdown-options" id="purchaseOrderOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="purchaseOrder">
                        </div>
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
                        <label for="subAccount">Sub Account</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" autocomplete="off" placeholder="Search sub account..." id="subAccountSearch">
                            <div class="dropdown-options" id="subAccountOptions">
                                <!-- Options loaded dynamically -->
                            </div>
                            <input type="hidden" id="subAccount">
                        </div>
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
                        <select id="project" tabindex="-1">
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
                                <th width="3%">S#</th>
                                <th width="15%">Product Code / Name</th>
                                <th width="5%">Unit</th>
                                <th width="5%">Qty</th>
                                <th width="8%">RP Unit Price</th>
                                <th width="8%">TP Unit Price</th>
                                <th width="10%">RP Total Value<br><small style="font-size: 9px; text-transform: uppercase;">(Excl. Sale Tax)</small></th>
                                <th width="10%">TP Total Value<br><small style="font-size: 9px; text-transform: uppercase;">(Excl. Sale Tax)</small></th>
                                <th width="5%">Disc %</th>
                                <th width="8%">Discount Amount</th>
                                <th width="8%">Sales Tax</th>
                                <th width="10%">TP Amount<br><small style="font-size: 9px; text-transform: uppercase;">(Incl. Sale Tax)</small></th>
                                <th width="10%">Net Amount</th>
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
                                <th id="totalRPUnitPrice">0.00</th>
                                <th id="totalTPUnitPrice">0.00</th>
                                <th id="totalRPValue">0.00</th>
                                <th id="totalTPValue">0.00</th>
                                <th></th>
                                <th id="totalDiscountAmount">0.00</th>
                                <th id="totalSalesTax">0.00</th>
                                <th id="totalTPAmount">0.00</th>
                                <th id="totalNetAmount">0.00</th>
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
                <h2 class="card-title">Invoice Summary</h2>
                <div style="display: flex; justify-content: flex-end;">
                    <div style="width: 400px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid var(--border-default);">
                                <td style="padding: 8px; text-align: left;">RP Excl. Total:</td>
                                <td style="padding: 8px; text-align: right; font-weight: 600;" id="summaryRPTotal">0.00</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-default);">
                                <td style="padding: 8px; text-align: left;">TP Excl. Total:</td>
                                <td style="padding: 8px; text-align: right; font-weight: 600;" id="summaryTPTotal">0.00</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-default);">
                                <td style="padding: 8px; text-align: left;">Discount Total:</td>
                                <td style="padding: 8px; text-align: right; font-weight: 600;" id="summaryDiscountTotal">0.00</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-default);">
                                <td style="padding: 8px; text-align: left;">Sales Tax:</td>
                                <td style="padding: 8px; text-align: right; font-weight: 600;" id="summaryTotalSalesTax">0.00</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-default);">
                                <td style="padding: 8px; text-align: left;">Total Amount:</td>
                                <td style="padding: 8px; text-align: right; font-weight: 600;" id="totalBill">0.00</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-default);">
                                <td style="padding: 8px; text-align: left;">Advance Tax (<span id="advanceTaxPercent">0</span>%):</td>
                                <td style="padding: 8px; text-align: right; font-weight: 600;" id="advanceTax">0.00</td>
                            </tr>
                            <tr style="border-top: 2px solid var(--border-strong);">
                                <td style="padding: 12px 8px; text-align: left; font-weight: 700; font-size: 16px;">Net Amount:</td>
                                <td style="padding: 12px 8px; text-align: right; font-weight: 700; font-size: 16px; color: var(--primary);" id="netAmount">0.00</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" id="resetBtn" tabindex="-1">
                    <i class="fas fa-redo"></i> Reset Invoice
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> <span id="saveBtnText">Save Invoice</span>
                </button>
            </div>
        </form>
    </div>

    <script src="../../../assets/js/purchase/purchase_tax_invoice/purchase-add.js"></script>
</body>
</html>