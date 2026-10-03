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
$initialSearch = $_GET['search'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Grocery Purchase Invoices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/purchase/grocery_purchase_invoice/purchase-list.css?v=<?php echo time(); ?>">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Grocery Purchase Invoices</h1>
        </div>

        <div class="card">
            <h2 class="card-title">Filters & Search</h2>
            <div class="filters">
                <div class="form-group">
                    <label for="dateFrom">Date From</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="form-group">
                    <label for="dateTo">Date To</label>
                    <input type="date" id="dateTo">
                </div>
                <div class="form-group">
                    <label for="companyFilter">Company</label>
                    <select id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="supplierFilter">Supplier</label>
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" autocomplete="off" placeholder="All Suppliers" id="supplierFilterSearch">
                        <div class="dropdown-options" id="supplierFilterOptions">
                            <!-- Options loaded dynamically -->
                        </div>
                        <input type="hidden" id="supplierFilter">
                    </div>
                </div>
                <div class="form-group">
                    <label for="supplierInvoiceNoFilter">Supplier Invoice No</label>
                    <input type="text" id="supplierInvoiceNoFilter" placeholder="Search supplier inv no...">
                </div>
                <div class="form-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="partial">Partial</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
            </div>
            
            <div class="actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
<input type="text" id="searchInput" placeholder="Search by invoice number, supplier..." value="<?= htmlspecialchars($initialSearch) ?>">
                </div>
                <button class="btn btn-primary" id="newInvoiceBtn">
                    <i class="fas fa-plus"></i> New Invoice
                </button>
            </div>
            
            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="12%">Invoice No</th>
                            <th width="12%">Date</th>
                            <th width="18%">Supplier</th>
                            <th width="11%">Supplier Inv No</th>
                            <th width="6%">Items</th>
                            <th width="11%">Total Amount</th>
                            <th width="10%">Status</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">
                    Showing 0 to 0 of 0 entries
                </div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Dynamic pagination buttons will be generated here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h3 class="modal-title">Confirm Delete</h3>
            <p>Are you sure you want to delete this purchase invoice? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Duplicate Success Modal -->
    <div class="modal" id="duplicateSuccessModal">
        <div class="modal-content duplicate-success-content">
            <div class="dup-success-icon"><i class="fas fa-check-circle"></i></div>
            <h3 class="modal-title" style="text-align:center;">Invoice Duplicated!</h3>
            <p class="dup-success-msg">New invoice <strong id="dupSuccessBillNo"></strong> has been created successfully.</p>
            <div class="dup-success-timer">Auto-closing in <span id="dupSuccessCountdown">6</span>s</div>
            <div class="modal-actions" style="justify-content:center;gap:10px;">
                <button class="btn btn-secondary" id="dupSuccessCloseBtn"><i class="fas fa-times"></i> Close</button>
                <button class="btn btn-success" id="dupSuccessPrintBtn"><i class="fas fa-print"></i> Print</button>
                <button class="btn btn-primary" id="dupSuccessEditBtn"><i class="fas fa-edit"></i> Edit</button>
            </div>
        </div>
    </div>

    <!-- Duplicate Invoice Modal -->
    <div class="modal" id="duplicateModal">
        <div class="modal-content duplicate-modal-content">
            <div class="duplicate-modal-header">
                <div class="duplicate-modal-icon"><i class="fas fa-copy"></i></div>
                <div>
                    <h3 class="modal-title" style="margin-bottom:4px;">Duplicate Invoice</h3>
                    <p class="duplicate-modal-sub">All items, amounts &amp; details will be copied exactly</p>
                </div>
            </div>

            <div class="duplicate-info-row">
                <div class="duplicate-info-box">
                    <span class="duplicate-info-label">Source Invoice</span>
                    <span class="duplicate-info-value" id="duplicateSourceBillNo"></span>
                </div>
                <div class="duplicate-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="duplicate-info-box duplicate-info-box--new">
                    <span class="duplicate-info-label">New Invoice</span>
                    <span class="duplicate-info-value" id="duplicateNewBillNoDisplay">Generating...</span>
                </div>
            </div>

            <div class="duplicate-fields">
                <div class="duplicate-field-group">
                    <label for="duplicateDate">Purchase Date</label>
                    <input type="date" id="duplicateDate">
                </div>
                <div class="duplicate-field-group">
                    <label>Supplier <span class="dup-optional">(optional — keep same)</span></label>
                    <div class="searchable-dropdown">
                        <input type="text" class="search-input" autocomplete="off" placeholder="Keep original supplier" id="dupSupplierSearch">
                        <div class="dropdown-options" id="dupSupplierOptions"></div>
                        <input type="hidden" id="dupSupplierId">
                    </div>
                    <span id="dupSupplierBalance" style="font-size:12px;color:var(--subtext);margin-top:4px;display:none;"></span>
                </div>
            </div>

            <p class="duplicate-readonly-note"><i class="fas fa-info-circle"></i> Invoice number is auto-generated. Leave supplier blank to keep original.</p>
            <span id="duplicateError" style="color:var(--error); font-size:12px; display:block; margin-top:8px;"></span>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDuplicateBtn">Cancel</button>
                <button class="btn btn-primary" id="confirmDuplicateBtn"><i class="fas fa-copy"></i> Confirm Duplicate</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/purchase/grocery_purchase_invoice/purchase-list.js"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
</body>
</html>