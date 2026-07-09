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
    <title>Journal Entry - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/journal_voucher/journal-add.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Journal Entry</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" id="viewListBtn">
                    <i class="fas fa-list"></i> View List
                </button>
            </div>
        </div>

        <div id="validationMessage" class="validation-message"></div>

        <div class="card">
            <div class="card-title">Main Content</div>
            
            <div class="form-section">
                <div class="form-row">
                    <div class="form-field">
                        <label for="date">Date</label>
                        <input type="date" id="date" value="">
                    </div>
                    
                    <div class="form-field">
                        <label for="voucher">Voucher #</label>
                        <input type="text" id="voucher" value="JV-2023-001" readonly>
                    </div>
                    
                    <div class="form-field">
                        <label for="company">Company</label>
                        <select id="company" required>
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    
                    <div class="form-field full-width">
                        <label for="description">Description</label>
                        <textarea id="description" placeholder="Enter journal entry description..."></textarea>
                        <div class="helper-text">Brief description of the journal entry</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Journal Entries</div>
            
            <div style="position: relative; z-index: 10;">
                <div class="table-container">
                    <table id="journalTable">
                        <thead>
                            <tr>
                                <th width="5%">S#</th>
                                <th width="30%">Account</th>
                                <th width="15%">Debit</th>
                                <th width="15%">Credit</th>
                                <th width="25%">Line Description</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="journalTableBody">
                            <!-- Journal entries will be added here dynamically -->
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="2">Total</td>
                                <td id="totalDebit">0.00</td>
                                <td id="totalCredit">0.00</td>
                                <td colspan="2" id="balanceStatus">Balanced</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; justify-content: center;">
                <button class="btn btn-ghost" id="addEntryBtn">
                    <i class="fas fa-plus"></i> Add Entry Line
                </button>
            </div>
        </div>

        <div class="form-actions">
            <div class="form-info">
                <div class="helper-text" id="formStatus">Journal entry is not balanced. Debits must equal credits.</div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-secondary" id="resetBtn">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button class="btn btn-secondary" id="saveDraftBtn">
                    <i class="fas fa-save"></i> Save as Draft
                </button>
                <button class="btn btn-primary" id="postVoucherBtn">
                    <i class="fas fa-check-circle"></i> Post Voucher
                </button>
            </div>
        </div>
    </div>

    <!-- Post Confirmation Modal -->
    <div class="modal" id="postConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Post Voucher</h3>
                <button class="modal-close" id="closePostModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to post this journal voucher?</p>
                <p style="margin-top: 12px; color: #6B7280; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> Once posted, the entry will be recorded in the accounting ledger and cannot be edited.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelPostBtn">
                    Cancel
                </button>
                <button class="btn btn-primary" id="confirmPostBtn">
                    Post Voucher
                </button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/journal_voucher/journal-add.js"></script>
</body>
</html>