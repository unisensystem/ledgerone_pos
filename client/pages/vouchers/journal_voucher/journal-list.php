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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entries List - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/journal_voucher/journal-list.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Journal Entries</h1>
            <div class="header-actions">
                <a href="#" class="btn btn-primary" id="newEntryBtn">
                    <i class="fas fa-plus"></i> New Journal Entry
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="stat-value" id="totalEntries">24</div>
                <div class="stat-label">Total Entries</div>
            </div>
            <div class="stat-card posted">
                <div class="stat-value" id="postedEntries">18</div>
                <div class="stat-label">Posted</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-value" id="pendingEntries">4</div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card draft">
                <div class="stat-value" id="draftEntries">2</div>
                <div class="stat-label">Drafts</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <span>Journal Entries List</span>
                <div class="table-actions">
                    <button class="btn btn-ghost" id="exportBtn">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filter-group">
                    <label for="filterDateFrom">Date From</label>
                    <input type="date" id="filterDateFrom">
                </div>
                <div class="filter-group">
                    <label for="filterDateTo">Date To</label>
                    <input type="date" id="filterDateTo">
                </div>
                <div class="filter-group">
                    <label for="filterCompany">Company</label>
                    <select id="filterCompany">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus">
                        <option value="">All Statuses</option>
                        <option value="posted">Posted</option>
                        <option value="pending">Pending</option>
                        <option value="draft">Draft</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button class="btn btn-primary" id="applyFiltersBtn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table id="journalEntriesTable">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="15%">Voucher #</th>
                            <th width="12%">Date</th>
                            <th width="25%">Description</th>
                            <th width="10%">Debit Total</th>
                            <th width="10%">Credit Total</th>
                            <th width="10%">Status</th>
                            <th width="13%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="journalTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- No data state (hidden by default) -->
            <div id="noDataState" class="no-data" style="display: none;">
                <i class="fas fa-file-invoice"></i>
                <p>No journal entries found matching your criteria</p>
                <button class="btn btn-ghost" id="clearFiltersBtn">
                    Clear Filters
                </button>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info">
                    Showing <span id="startEntry">1</span> to <span id="endEntry">10</span> of <span id="totalEntriesCount">24</span> entries
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevPageBtn" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn" id="nextPageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Entry Modal -->
    <div class="modal" id="viewEntryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Journal Entry Details</h3>
                <button class="modal-close" id="closeViewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Voucher #:</div>
                    <div class="entry-detail-value" id="modalVoucher">JV-2023-001</div>
                </div>
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Date:</div>
                    <div class="entry-detail-value" id="modalDate">2023-10-15</div>
                </div>
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Company:</div>
                    <div class="entry-detail-value" id="modalCompany">-</div>
                </div>
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Status:</div>
                    <div class="entry-detail-value">
                        <span class="status-badge status-posted" id="modalStatus">Posted</span>
                    </div>
                </div>
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Description:</div>
                    <div class="entry-detail-value" id="modalDescription">Office supplies purchase for Q4 2023</div>
                </div>
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Posted By:</div>
                    <div class="entry-detail-value" id="modalPostedBy">John Smith</div>
                </div>
                <div class="entry-detail-row">
                    <div class="entry-detail-label">Posted On:</div>
                    <div class="entry-detail-value" id="modalPostedOn">2023-10-15 14:30</div>
                </div>

                <div style="margin-top: 24px;">
                    <h4 style="font-size: 14px; margin-bottom: 12px; color: #0E1A2B;">Entry Lines</h4>
                    <table class="entry-detail-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Debit</th>
                                <th>Credit</th>
                            </tr>
                        </thead>
                        <tbody id="modalEntryLines">
                            <!-- Entry lines will be populated here -->
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: 600;">
                                <td>Total</td>
                                <td id="modalTotalDebit">1,250.00</td>
                                <td id="modalTotalCredit">1,250.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="printEntryBtn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-primary" id="closeModalBtn">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <button class="modal-close" id="closeDeleteModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete journal entry <strong id="deleteVoucherName">JV-2023-001</strong>?</p>
                <p style="margin-top: 12px; color: #E34F4F; font-size: 13px;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteBtn">
                    Cancel
                </button>
                <button class="btn btn-primary" id="confirmDeleteBtn" style="background-color: #E34F4F;">
                    Delete Entry
                </button>
            </div>
        </div>
    </div>

    <!-- Post Draft Confirmation Modal -->
    <div class="modal" id="postDraftModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Post Voucher</h3>
                <button class="modal-close" id="closePostDraftModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to post journal entry <strong id="postDraftVoucherName">JV-2023-001</strong>?</p>
                <p style="margin-top: 12px; color: #6B7280; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> Once posted, the entry will be recorded in the accounting ledger and cannot be edited.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelPostDraftBtn">
                    Cancel
                </button>
                <button class="btn btn-primary" id="confirmPostDraftBtn">
                    Post Voucher
                </button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/journal_voucher/journal-list.js"></script>
</body>
</html>