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
    <title>LedgerOne ERP - Opening Cash In Hand</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/css/vouchers/cash_opening/cash-opening.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Opening Cash In Hand</h1>
            <div class="header-info">
                <div class="current-date" id="currentDateDisplay"></div>
            </div>
        </div>
        
        <!-- Status Message Area -->
        <div id="statusMessage" class="status-message" style="display: none;"></div>
        
        <!-- Form Card -->
        <div class="card" id="formCard">
            <div class="card-title">
                <i class="fas fa-money-bill-wave"></i>
                Opening Cash In Hand Entry
            </div>
            
            <div class="form-locked" id="formContainer">
                <!-- Lock overlay (shown when record exists) -->
                <div class="form-locked-overlay" id="lockOverlay" style="display: none;">
                    <div class="lock-message">
                        <div class="lock-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Form Locked</h3>
                        <p>Opening cash in hand has already been set for this period. You can view the existing record below.</p>
                        <p>To make changes, please contact your system administrator.</p>
                    </div>
                </div>
                
                <!-- Form Content -->
                <div class="form-section">
                    <h3 class="section-title">Cash Details</h3>
                    
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="branch" class="form-label required">Branch</label>
                            <select 
                                id="branch" 
                                class="form-input"
                                required
                            >
                                <option value="">Select Branch</option>
                            </select>
                            <div class="helper-text">Select the branch</div>
                            <div id="branchError" class="error-text" style="display: none;">Please select a branch</div>
                        </div>
                        
                        <div class="form-field">
                            <label for="company" class="form-label required">Company</label>
                            <select 
                                id="company" 
                                class="form-input"
                                required
                            >
                                <option value="">Select Company</option>
                            </select>
                            <div class="helper-text">Select the company</div>
                            <div id="companyError" class="error-text" style="display: none;">Please select a company</div>
                        </div>
                        
                        <div class="form-field">
                            <label for="asOfDate" class="form-label required">As of Date</label>
                            <input 
                                type="date" 
                                id="asOfDate" 
                                class="form-input"
                                required
                            >
                            <div class="helper-text">Default is today's date</div>
                            <div id="asOfDateError" class="error-text" style="display: none;">Please select a valid date</div>
                        </div>
                        
                        <div class="form-field">
                            <label for="openingCash" class="form-label required">Opening Cash In Hand</label>
                            <input 
                                type="number" 
                                id="openingCash" 
                                class="form-input"
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                required
                            >
                            <div class="helper-text">Enter the opening cash amount</div>
                            <div id="openingCashError" class="error-text" style="display: none;">Please enter a valid amount</div>
                        </div>
                        
                        <div class="form-field">
                            <label class="form-label">Currency</label>
                            <input 
                                type="text" 
                                id="currency" 
                                class="form-input"
                                value="USD"
                                readonly
                            >
                            <div class="helper-text">Default currency (can be configured in settings)</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="button" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Save Opening Cash
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Records List Card -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-list"></i>
                Existing Opening Cash Records
            </div>
            
            <div class="table-container">
                <table class="data-table" id="cashRecordsTable">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Company</th>
                            <th>As of Date</th>
                            <th>Opening Cash Amount</th>
                            <th>Currency</th>
                            <th>Entered By</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Records will be populated here -->
                    </tbody>
                </table>
                
                <!-- Empty state -->
                <div id="emptyState" class="empty-state">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h3>No Opening Cash Records Found</h3>
                    <p>Once you save an opening cash record, it will appear here.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Opening Cash</h2>
                <button type="button" class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-field">
                    <label for="editBranch" class="form-label">Branch</label>
                    <input type="text" id="editBranch" class="form-input" readonly>
                </div>
                <div class="form-field">
                    <label for="editCompany" class="form-label">Company</label>
                    <input type="text" id="editCompany" class="form-input" readonly>
                </div>
                <div class="form-field">
                    <label for="editAsOfDate" class="form-label">As of Date</label>
                    <input type="date" id="editAsOfDate" class="form-input" readonly>
                </div>
                <div class="form-field">
                    <label for="editOpeningCash" class="form-label required">Opening Cash Amount</label>
                    <input type="number" id="editOpeningCash" class="form-input" placeholder="0.00" min="0" step="0.01" required>
                    <div id="editOpeningCashError" class="error-text" style="display: none;"></div>
                </div>
                <div class="form-field">
                    <label for="editCurrency" class="form-label">Currency</label>
                    <input type="text" id="editCurrency" class="form-input" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateBtn">Update</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h2>Delete Opening Cash</h2>
                <button type="button" class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this opening cash record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/vouchers/cash_opening/cash-opening.js"></script>
</body>
</html>