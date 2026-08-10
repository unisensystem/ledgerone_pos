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
    <title>LedgerOne ERP - Purchase Tax Invoice</title>
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
                        <input type="date" id="purchaseDate" required>
                    </div>
                    <div class="form-group">
                        <label for="supplierInvoiceNo">Supplier Invoice No</label>
                        <input type="text" id="supplierInvoiceNo" placeholder="Enter supplier invoice number">
                    </div>
                    <div class="form-group">
                        <label for="company" class="required">Company</label>
                        <select id="company" required>
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="supplierCode" class="required">Supplier Code</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="search-input" autocomplete="off" placeholder="Search supplier code..." id="supplierCodeSearch">
                            <div class="dropdown-options" id="supplierCodeOptions"></div>
                            <input type="hidden" id="supplierCode" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="branch" class="required">Branch</label>
                        <div class="searchable-dropdown">
                            <input type="text" autocomplete="off" class="search-input" placeholder="Search branch..." id="branchSearch">
                            <div class="dropdown-options" id="branchOptions"></div>
                            <input type="hidden" id="branch" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="currency" class="required">Currency</label>
                        <select id="currency" required>
                            <option value="">Select Currency</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" placeholder="Enter any additional remarks"></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="card-title" style="margin-bottom: 0;">Items</h2>
                </div>
                <div class="table-container">
                    <table id="itemsTable">
                        <thead>
                            <tr>
                                <th width="4%">S#</th>
                                <th width="25%">Product Code / Name</th>
                                <th width="8%">Unit</th>
                                <th width="8%">Qty</th>
                                <th width="10%">RP Unit Price</th>
                                <th width="10%">TP Unit Price</th>
                                <th width="12%">RP Total Value</th>
                                <th width="12%">TP Total Value</th>
                                <th width="8%">Disc %</th>
                                <th width="10%">Discount Amount</th>
                                <th width="10%">Sales Tax</th>
                                <th width="12%">TP Amount</th>
                                <th width="12%">Net Amount</th>
                                <th width="4%">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
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
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value" id="summaryTotalAmount">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Tax</span>
                        <span class="summary-value" id="summaryTotalTax">0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Net Amount</span>
                        <span class="summary-value" id="summaryNetAmount">0.00</span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" id="resetBtn">
                    <i class="fas fa-redo"></i> Reset Invoice
                </button>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Invoice
                </button>
            </div>
        </form>
    </div>

    <script src="../../../assets/js/purchase/purchase_invoice/purchase-add-new.js"></script>
</body>
</html>