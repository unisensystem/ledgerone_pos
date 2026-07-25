<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
$initialSearch = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transfer Vouchers - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/transfer_voucher/transfer-list.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Transfer Vouchers</h1>
            <button class="btn btn-primary" id="newVoucherBtn">
                <i class="fas fa-plus"></i> New Voucher
            </button>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="filters-title"><i class="fas fa-filter"></i> Filters</div>
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
                    <label for="companyFilter">Company</label>
                    <select id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="fromTypeFilter">From Type</label>
                    <select id="fromTypeFilter">
                        <option value="">All</option>
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="toTypeFilter">To Type</label>
                    <select id="toTypeFilter">
                        <option value="">All</option>
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-secondary" id="resetFiltersBtn"><i class="fas fa-redo"></i> Reset</button>
                <button class="btn btn-primary" id="applyFiltersBtn"><i class="fas fa-check"></i> Apply Filters</button>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary-section" id="summarySection">
            <div class="summary-card summary-total">
                <div class="summary-icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="summary-info">
                    <div class="summary-label">Total Amount</div>
                    <div class="summary-value" id="summaryTotalValue">0.00</div>
                </div>
            </div>
            <div class="summary-methods" id="summaryMethods"></div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Transfer Vouchers</div>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
<input type="text" id="searchInput" placeholder="Search vouchers..." value="<?= htmlspecialchars($initialSearch) ?>">
                    </div>
                    <button class="btn btn-secondary btn-icon" id="exportBtn" title="Export"><i class="fas fa-download"></i></button>
                    <button class="btn btn-secondary btn-icon" id="refreshBtn" title="Refresh"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Voucher #</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Amount</th>
                        <th>Slip No</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vouchersTableBody"></tbody>
            </table>
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content edit-modal">
            <div class="modal-header">
                <div class="modal-title">Edit Transfer Voucher</div>
                <button class="modal-close" id="editModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="editVoucherForm">
                    <input type="hidden" id="editVoucherId">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Voucher #</label>
                            <input type="text" id="editVoucherNumber" readonly>
                        </div>
                        <div class="form-group">
                            <label>Voucher Date</label>
                            <input type="date" id="editVoucherDate" required>
                        </div>
                        <div class="form-group">
                            <label>Company</label>
                            <select id="editCompany" required>
                                <option value="">Select Company</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <select id="editCurrency" required>
                                <option value="">Select Currency</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" id="editAmount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select id="editPaymentMethod" required>
                                <option value="">Select Method</option>
                            </select>
                        </div>
                        <div class="form-group" id="editBankAccountGroup" style="display:none;">
                            <label>Bank Account</label>
                            <select id="editBankAccount">
                                <option value="">Select Bank Account</option>
                            </select>
                        </div>
                        <div class="form-group" id="editChequeNoGroup" style="display:none;">
                            <label>Cheque No</label>
                            <input type="text" id="editChequeNo">
                        </div>
                        <div class="form-group" id="editChequeDateGroup" style="display:none;">
                            <label>Cheque Date</label>
                            <input type="date" id="editChequeDate">
                        </div>
                        <div class="form-group" id="editSlipNoGroup" style="display:none;">
                            <label>Slip No</label>
                            <input type="text" id="editSlipNo" placeholder="Enter slip number">
                        </div>
                        <div class="form-group" id="editAttachmentGroup" style="display:none;">
                            <label>Attachment</label>
                            <input type="file" id="editSlipAttachment" accept="image/*,.pdf">
                            <div id="editCurrentAttachment" style="margin-top:6px; font-size:12px; color:var(--subtext);"></div>
                        </div>
                        <div class="form-group">
                            <label>From Party (readonly)</label>
                            <input type="text" id="editFromParty" readonly>
                        </div>
                        <div class="form-group">
                            <label>To Party (readonly)</label>
                            <input type="text" id="editToParty" readonly>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label>Description</label>
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

    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Confirm Delete</div>
                <button class="modal-close" id="deleteModalClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this transfer voucher?</p>
                <p style="margin-top:8px;"><strong>Voucher #:</strong> <span id="deleteVoucherNumber"></span></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

<script src="../../../assets/js/vouchers/transfer_voucher/transfer-list.js"></script>
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
