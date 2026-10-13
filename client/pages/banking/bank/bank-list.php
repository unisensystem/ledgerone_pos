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
// 👇 ADD KARO
$initialSearch = $_GET['search'] ?? '';

// Get base currency symbol
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Bank Accounts List</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/banking/bank/bank-list.css">
</head>

<body class="light-theme">
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <div class="logo-icon">L</div>
                <div class="logo-text">
                    <h1>LedgerOne ERP</h1>
                    <p>Bank Accounts Management</p>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </header>

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h2>Bank Accounts</h2>
                <p>Manage all your bank accounts in one place</p>
            </div>
            <div class="action-buttons">
                <button class="btn btn-ghost">
                    <i class="fas fa-filter"></i> Filters
                </button>
                <button class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-primary" id="addAccountBtn">
                    <i class="fas fa-plus"></i> Add Bank Account
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card total-accounts">
                <div class="stat-value">24</div>
                <div class="stat-label">
                    <span>Total Accounts</span>
                    <span class="stat-change positive">+3 this month</span>
                </div>
            </div>
            <div class="stat-card active-accounts">
                <div class="stat-value">18</div>
                <div class="stat-label">
                    <span>Active Accounts</span>
                    <span class="stat-change positive">75% active</span>
                </div>
            </div>
            <div class="stat-card balance">
                <div class="stat-value">PKR 12.8M</div>
                <div class="stat-label">
                    <span>Total Balance</span>
                    <span class="stat-change positive">+8.2%</span>
                </div>
            </div>
            <div class="stat-card mfb-accounts">
                <div class="stat-value">6</div>
                <div class="stat-label">
                    <span>MFB Accounts</span>
                    <span class="stat-change positive">+2</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <div class="filter-row">
                <div class="search-box filter-group">
                    <label class="filter-label">Search Accounts</label>
                    <i class="fas fa-search"></i>
                    <input type="text" class="filter-input" id="searchInput" placeholder="Search by bank name, account number, or branch..." value="<?= htmlspecialchars($initialSearch) ?>">
                    
                </div>
                <div class="filter-group">
                    <label class="filter-label">Account Type</label>
                    <select class="filter-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="current">Current Account</option>
                        <option value="savings">Savings Account</option>
                        <option value="mfb_account">MFB Account</option>
                        <option value="checking">Checking Account</option>
                        <option value="credit_card">Credit Card</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Bank</label>
                    <select class="filter-select" id="bankFilter">
                        <option value="">All Banks</option>
                        <option value="NBP">National Bank of Pakistan</option>
                        <option value="HBL">Habib Bank Limited</option>
                        <option value="UBL">United Bank Limited</option>
                        <option value="MCB">MCB Bank</option>
                        <option value="ABL">Allied Bank Limited</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-secondary" id="applyFilters">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <button class="btn btn-ghost" id="resetFilters">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions hidden" id="bulkActions">
            <div class="bulk-count" id="selectedCount">0 accounts selected</div>
            <button class="btn btn-secondary">
                <i class="fas fa-toggle-on"></i> Activate
            </button>
            <button class="btn btn-secondary">
                <i class="fas fa-toggle-off"></i> Deactivate
            </button>
            <button class="btn btn-secondary" id="bulkDelete">
                <i class="fas fa-trash"></i> Delete
            </button>
            <button class="btn btn-ghost" id="clearSelection">
                <i class="fas fa-times"></i> Clear Selection
            </button>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <h3>Bank Accounts List</h3>
                <div class="table-actions">
                    <button class="btn btn-ghost">
                        <i class="fas fa-columns"></i> Columns
                    </button>
                    <button class="btn btn-ghost">
                        <i class="fas fa-cog"></i> Settings
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table id="accountsTable">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th class="sortable" data-sort="bank">Bank Name <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort="account">Account Number <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort="type">Account Type <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort="branch">Branch <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort="balance">Balance <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort="currency">Currency <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort="status">Status <i class="fas fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div class="empty-state hidden" id="emptyState">
                <i class="fas fa-university"></i>
                <h3>No bank accounts found</h3>
                <p>Try adjusting your search or filters to find what you're looking for.</p>
                <button class="btn btn-primary" id="addFirstAccount">
                    <i class="fas fa-plus"></i> Add Your First Bank Account
                </button>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">Showing 1 to 10 of 24 accounts</div>
                <div class="pagination-controls">
                    <button class="page-btn" id="prevPage" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal edit-modal">
            <div class="modal-header">
                <h3>Edit Bank Account</h3>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <div class="form-row">
                        <label>Bank Name *</label>
                        <input type="text" id="editBankName" required>
                    </div>
                    <div class="form-row">
                        <label>Account Number *</label>
                        <input type="text" id="editAccountNumber" required>
                    </div>
                    <div class="form-row">
                        <label>Account Title</label>
                        <input type="text" id="editAccountTitle">
                    </div>
                    <div class="form-row">
                        <label>Account Type *</label>
                        <select id="editAccountType" required>
                            <option value="current">Current Account</option>
                            <option value="savings">Savings Account</option>
                            <option value="checking">Checking Account</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="loan">Loan Account</option>
                            <option value="mfb_account">MFB Account</option>
                            <option value="branchless">Branchless Banking</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Currency</label>
                        <select id="editCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Branch Name *</label>
                        <input type="text" id="editBranchName" required>
                    </div>
                    <div class="form-row">
                        <label>Branch Code</label>
                        <input type="text" id="editBranchCode">
                    </div>
                    <div class="form-row">
                        <label>Branch City *</label>
                        <input type="text" id="editBranchCity" required>
                    </div>
                    <div class="form-row">
                        <label>Branch State *</label>
                        <input type="text" id="editBranchState" required>
                    </div>
                    <div class="form-row">
                        <label>IBAN</label>
                        <input type="text" id="editIban">
                    </div>
                    <div class="form-row">
                        <label>SWIFT Code</label>
                        <input type="text" id="editSwiftCode">
                    </div>
                    <div class="form-row">
                        <label>Contact Person</label>
                        <input type="text" id="editContactPerson">
                    </div>
                    <div class="form-row">
                        <label>Contact Number</label>
                        <input type="tel" id="editContactNumber">
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input type="email" id="editEmail">
                    </div>
                    <div class="form-row">
                        <label>Balance Type</label>
                        <select id="editBalanceType">
                            <option value="debit">Debit Balance</option>
                            <option value="credit">Credit Balance</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Opening Balance</label>
                        <input type="number" id="editOpeningBalance" step="0.01">
                    </div>
                    <div class="form-row">
                        <label>Bank Logo</label>
                        <input type="file" id="editBankLogo" accept="image/*">
                        <div style="font-size: 12px; color: var(--light-text-sub); margin-top: 4px;">Upload new logo (JPG, PNG, GIF - Max 2MB)</div>
                        <div id="editLogoPreview" style="margin-top: 10px; display: none;">
                            <img id="editLogoPreviewImg" style="max-width: 100px; max-height: 100px; border-radius: 8px;" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Status</label>
                        <select id="editIsActive">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEdit">Cancel</button>
                <button class="btn btn-primary" id="saveEdit">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this bank account?</p>
                <p><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDelete">Cancel</button>
                <button class="btn btn-primary" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

   <script src="../../../assets/js/banking/bank/bank-list.js"></script>
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