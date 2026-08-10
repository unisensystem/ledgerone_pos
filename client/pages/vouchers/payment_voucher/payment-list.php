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
    <title>Payment Vouchers - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="../../../assets/css/vouchers/payment_voucher/payment-list.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Payment Vouchers</h1>
            <div style="display: flex; gap: 24px;">
                <div class="mode-toggle">
                    <label class="toggle-label">
                        <input type="radio" name="paymentType" value="supplier" checked>
                        <span>Payment to Supplier</span>
                    </label>
                    <label class="toggle-label">
                        <input type="radio" name="paymentType" value="customer">
                        <span>Payment to Customer</span>
                    </label>
                </div>
                <button class="btn btn-primary" id="newVoucherBtn">
                    <i class="fas fa-plus"></i>
                    New Voucher
                </button>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-container">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filters
            </div>
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="dateFrom">Date From</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="filter-group">
                    <label for="dateTo">Date To</label>
                    <input type="date" id="dateTo">
                </div>
                <div class="filter-group">
                    <label for="supplierFilter" id="supplierFilterLabel">Supplier</label>
                    <div class="searchable-dropdown">
                        <input type="text" id="supplierFilter" placeholder="Search supplier..." autocomplete="off">
                        <div class="dropdown-list" id="supplierFilterDropdown"></div>
                    </div>
                </div>
                <div class="filter-group">
                    <label for="companyFilter">Company</label>
                    <select id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="projectFilter">Project</label>
                    <select id="projectFilter">
                        <option value="">All Projects</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="posted">Posted</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-secondary" id="resetFiltersBtn">
                    <i class="fas fa-redo"></i>
                    Reset
                </button>
                <button class="btn btn-primary" id="applyFiltersBtn">
                    <i class="fas fa-check"></i>
                    Apply Filters
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-section" id="summarySection">
            <div class="summary-card summary-total">
                <div class="summary-icon"><i class="fas fa-wallet"></i></div>
                <div class="summary-info">
                    <div class="summary-label">Total Amount</div>
                    <div class="summary-value" id="summaryTotalValue">0.00</div>
                </div>
            </div>
            <div class="summary-methods" id="summaryMethods"></div>
        </div>

        <!-- Table Section -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Recent Payment Vouchers</div>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
<input type="text" id="searchInput" placeholder="Search vouchers..." value="<?= htmlspecialchars($initialSearch) ?>">
                    </div>
                    <button class="btn btn-secondary btn-icon" id="exportBtn" title="Export">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="btn btn-secondary btn-icon" id="refreshBtn" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Voucher #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Bill No</th>
                        <th>Project</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vouchersTableBody">
                    <!-- Table rows will be populated by JavaScript -->
                </tbody>
            </table>

            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 1 to 10 of 45 entries</div>
                <div class="pagination-controls">
                    <!-- Pagination buttons will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content edit-modal">
            <div class="modal-header">
                <div class="modal-title">Edit Payment Voucher</div>
                <button class="modal-close" id="editModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editVoucherForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editVoucherNumber">Voucher #</label>
                            <input type="text" id="editVoucherNumber" readonly>
                        </div>
                        <div class="form-group">
                            <label for="editVoucherDate">Voucher Date</label>
                            <input type="date" id="editVoucherDate" required>
                        </div>
                        <div class="form-group">
                            <label for="editCompany">Company</label>
                            <select id="editCompany" required>
                                <option value="">Select Company</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editSupplier">Supplier</label>
                            <input type="text" id="editSupplier" readonly>
                        </div>
                        <div class="form-group">
                            <label for="editSubAccount">Sub Account</label>
                            <select id="editSubAccount">
                                <option value="">Select Sub Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editBillNo">Bill No</label>
                            <input type="text" id="editBillNo">
                        </div>
                        <div class="form-group">
                            <label for="editCurrency">Currency</label>
                            <select id="editCurrency" required>
                                <option value="">Select Currency</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editAmount">Amount</label>
                            <input type="number" id="editAmount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="editPaymentMethod">Payment Method</label>
                            <select id="editPaymentMethod" required>
                                <option value="">Select Payment Method</option>
                            </select>
                        </div>
                        <div class="form-group" id="editBankAccountGroup">
                            <label for="editBankAccount">Bank Account</label>
                            <select id="editBankAccount">
                                <option value="">Select Bank Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editProject">Project</label>
                            <select id="editProject">
                                <option value="">Select Project</option>
                            </select>
                        </div>
                        <div class="form-group" id="editChequeNoGroup">
                            <label for="editChequeNo">Cheque No</label>
                            <input type="text" id="editChequeNo">
                        </div>
                        <div class="form-group" id="editChequeDateGroup">
                            <label for="editChequeDate">Cheque Date</label>
                            <input type="date" id="editChequeDate">
                        </div>
                        <div class="form-group" id="editCrExpenseGroup">
                            <label for="editCrExpenseRef">Expense Voucher (CR)</label>
                            <select id="editCrExpenseRef">
                                <option value="">Select CR Expense</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="editDescription">Description</label>
                        <textarea id="editDescription" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
                <button class="btn btn-primary" id="saveEditBtn">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Confirm Delete</div>
                <button class="modal-close" id="deleteModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this payment voucher?</p>
                <p><strong>Voucher #:</strong> <span id="deleteVoucherNumber"></span></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/payment_voucher/payment-list.js"></script>
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