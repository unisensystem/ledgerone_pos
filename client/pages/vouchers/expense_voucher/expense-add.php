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

// Check if editing existing voucher
$voucher_id = $_GET['id'] ?? null;
$is_edit_mode = !empty($voucher_id);

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - <?php echo $is_edit_mode ? 'Edit' : 'Add'; ?> Expense Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/expense_voucher/expense-add.css">
</head>

<body>
    <script>
        const VOUCHER_ID = <?php echo $voucher_id ? intval($voucher_id) : 'null'; ?>;
        const IS_EDIT_MODE = <?php echo $is_edit_mode ? 'true' : 'false'; ?>;
    </script>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><?php echo $is_edit_mode ? 'Edit' : 'Add'; ?> Expense Entry</h1>
            <button class="help-toggle" id="helpToggle">
                <i class="fas fa-question-circle"></i>
                Show Help
            </button>
        </div>

        <!-- Help Panel -->
        <div class="help-panel" id="helpPanel">
            <h3>Help for Non-Accountant Users</h3>

            <div class="help-item">
                <h4><i class="fas fa-calendar-alt"></i> Date Field</h4>
                <p>The date is automatically set to today's date. You can change it if the expense occurred on a
                    different date.</p>
            </div>

            <div class="help-item">
                <h4><i class="fas fa-hashtag"></i> Voucher Number</h4>
                <p>This is a unique reference number generated automatically by the system. You don't need to change
                    this.</p>
            </div>

            <div class="help-item">
                <h4><i class="fas fa-wallet"></i> Expense Account & Cost Center</h4>
                <p>Use the searchable dropdowns to find the appropriate account or cost center. If you can't find what
                    you need, use the "Add" buttons to create new ones.</p>
            </div>

            <div class="help-item">
                <h4><i class="fas fa-list"></i> Adding Multiple Expenses</h4>
                <p>Click the "+" button in the "Expense Entries" table to add more expense lines. Use the "-" button to
                    remove lines you don't need.</p>
            </div>

            <div class="help-item">
                <h4><i class="fas fa-check-circle"></i> Posting the Voucher</h4>
                <p>Once all expense details are entered correctly, click "Post Voucher" to save the expense entry to the
                    system.</p>
            </div>
        </div>

        <!-- Validation Message -->
        <div class="validation-message" id="validationMessage"></div>

        <!-- Main Section Card -->
        <div class="card">
            <div class="card-title">Main Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="date" class="required">Date</label>
                    <input type="date" id="date" value="" required>
                    <div class="error-text" id="dateError">Date is required</div>
                </div>

                <div class="form-group">
                    <label for="voucher" class="required">Voucher #</label>
                    <input type="text" id="voucher" value="" readonly required>
                    <div class="error-text" id="voucherError">Voucher number is required</div>
                </div>

                <div class="form-group">
                    <label for="company" class="required">Company</label>
                    <select id="company" required>
                        <option value="">Select Company</option>
                    </select>
                    <div class="error-text" id="companyError">Company is required</div>
                </div>

                <div class="form-group">
                    <label for="project">Project</label>
                    <select id="project">
                        <option value="">Select Project</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" placeholder="Enter a brief description of this expense entry"></textarea>
                    <div class="error-text" id="descriptionError">Description is required</div>
                </div>
            </div>
        </div>

        <!-- Expense Entries Card -->
        <div class="card">
            <div class="card-title">Expense Entries</div>

            <div class="table-wrapper">
                <table class="entries-table">
                    <thead>
                        <tr>
                            <th style="width: 40px">S#</th>
                            <th>Expense Account</th>
                            <th>Cost Center</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Bank Account</th>
                            <th>Cheque No</th>
                            <th>Cheque Date</th>
                            <th style="width: 80px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="expenseEntries">
                        <!-- Entries will be added here dynamically -->
                    </tbody>
                </table>
            </div>

            <div class="button-group">
                <button class="btn btn-ghost" id="addEntryBtn">
                    <i class="fas fa-plus"></i>
                    Add New Expense Line
                </button>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="footer-actions">
            <div class="left-actions">
                <button class="btn btn-secondary" id="addExpenseAccountBtn">
                    <i class="fas fa-plus-circle"></i>
                    Add Expense Account
                </button>
                <button class="btn btn-secondary" id="addCostCenterBtn">
                    <i class="fas fa-plus-circle"></i>
                    Add Cost Center
                </button>
                <button class="btn btn-ghost" id="manageCostCenterBtn">
                    <i class="fas fa-cog"></i>
                    Manage Cost Center
                </button>
            </div>

            <div>
                <button class="btn btn-primary" id="postVoucherBtn">
                    <i class="fas fa-check"></i>
                    <?php echo $is_edit_mode ? 'Update' : 'Post'; ?> Voucher
                </button>
            </div>
        </div>
    </div>

    <!-- Add Expense Account Modal -->
    <div class="modal" id="addExpenseAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Add Expense Account</div>
                <button class="modal-close" id="closeExpenseAccountModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="subAccount" class="required">Sub Account</label>
                    <select id="subAccount" required>
                        <option value="">Select sub account</option>
                    </select>
                    <div class="error-text" id="subAccountError">Sub account is required</div>
                </div>
                <div class="form-group">
                    <label for="accountName" class="required">Account Name</label>
                    <input type="text" id="accountName" placeholder="Enter account name" required>
                    <div class="error-text" id="accountNameError">Account name is required</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelExpenseAccount">Cancel</button>
                <button class="btn btn-primary" id="saveExpenseAccount">Save Account</button>
            </div>
        </div>
    </div>

    <!-- Add Cost Center Modal -->
    <div class="modal" id="addCostCenterModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Add Cost Center</div>
                <button class="modal-close" id="closeCostCenterModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="costCenterAccount" class="required">Account</label>
                    <div class="searchable-dropdown">
                        <input type="text" id="costCenterAccount" placeholder="Search account..." required>
                        <div class="dropdown-options" id="costCenterAccountOptions"></div>
                    </div>
                    <div class="error-text" id="costCenterAccountError">Account is required</div>
                </div>
                <div class="form-group">
                    <label for="costCenterName" class="required">Cost Center Name</label>
                    <input type="text" id="costCenterName" placeholder="Enter cost center name" required>
                    <div class="error-text" id="costCenterNameError">Cost center name is required</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelCostCenter">Cancel</button>
                <button class="btn btn-primary" id="saveCostCenter">Save Cost Center</button>
            </div>
        </div>
    </div>

    <!-- Manage Cost Center Modal -->
    <div class="modal" id="manageCostCenterModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <div class="modal-title">Manage Cost Centers</div>
                <button class="modal-close" id="closeManageCostCenterModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-wrapper">
                    <table class="entries-table">
                        <thead>
                            <tr>
                                <th>Cost Center Name</th>
                                <th>Account</th>
                                <th style="width: 120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="costCenterList">
                            <!-- Cost centers will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Cost Center Modal -->
    <div class="modal" id="editCostCenterModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit Cost Center</div>
                <button class="modal-close" id="closeEditCostCenterModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCostCenterId">
                <div class="form-group">
                    <label for="editCostCenterAccount" class="required">Account</label>
                    <div class="searchable-dropdown">
                        <input type="text" id="editCostCenterAccount" placeholder="Search account..." required>
                        <div class="dropdown-options" id="editCostCenterAccountOptions"></div>
                    </div>
                    <div class="error-text" id="editCostCenterAccountError">Account is required</div>
                </div>
                <div class="form-group">
                    <label for="editCostCenterName" class="required">Cost Center Name</label>
                    <input type="text" id="editCostCenterName" placeholder="Enter cost center name" required>
                    <div class="error-text" id="editCostCenterNameError">Cost center name is required</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEditCostCenter">Cancel</button>
                <button class="btn btn-primary" id="updateCostCenter">Update Cost Center</button>
            </div>
        </div>
    </div>

    <!-- Delete Cost Center Modal -->
    <div class="modal" id="deleteCostCenterModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Confirm Deletion</div>
                <button class="modal-close" id="closeDeleteCostCenterModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this cost center?</p>
                <p style="color: var(--subtext); margin-top: 8px;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteCostCenter">Cancel</button>
                <button class="btn btn-primary" id="confirmDeleteCostCenter" style="background-color: var(--error);">Delete</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/expense_voucher/expense-add.js"></script>
</body>

</html>