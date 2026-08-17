<?php
require_once '../../../includes/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Chart of Accounts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/chart_of_accounts/coa.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-sitemap" style="color: var(--primary); margin-right: 12px;"></i>Chart of Accounts</h1>
            <div class="panel-actions">
                <button class="btn btn-secondary" id="expandAllBtn">
                    <i class="fas fa-expand-alt"></i> Expand All
                </button>
                <button class="btn btn-secondary" id="collapseAllBtn">
                    <i class="fas fa-compress-alt"></i> Collapse All
                </button>
            </div>
        </div>

        <div class="main-content">
            <!-- Help Panel -->
            <div class="help-panel">
                <h2><i class="fas fa-question-circle" style="color: var(--primary);"></i> CoA Guide</h2>
                
                <div class="help-section">
                    <h3><i class="fas fa-sitemap"></i> What is Chart of Accounts?</h3>
                    <p>The Chart of Accounts (CoA) is a structured list of all accounts used in the general ledger of an organization. It provides a framework for recording all financial transactions.</p>
                </div>
                
                <div class="help-section">
                    <h3><i class="fas fa-layer-group"></i> Hierarchical Structure</h3>
                    <p>Accounts are organized in a tree-like structure with 5 main account heads at the top. Under each head, you can create unlimited sub-accounts for detailed tracking.</p>
                    
                    <div class="account-heads">
                        <div class="account-head-tag">Assets</div>
                        <div class="account-head-tag">Liabilities</div>
                        <div class="account-head-tag">Equity</div>
                        <div class="account-head-tag">Income</div>
                        <div class="account-head-tag">Expenses</div>
                    </div>
                </div>
                
                <div class="help-section">
                    <h3><i class="fas fa-lock"></i> Account Status</h3>
                    <p><strong>System Locked:</strong> Account heads are locked by system and cannot be edited or deleted.</p>
                    <p><strong>Manageable:</strong> Sub-accounts and posting accounts created by users can be edited or deleted as needed.</p>
                </div>
                
                <div class="help-section">
                    <h3><i class="fas fa-ruler-combined"></i> Hierarchy Rules</h3>
                    <ul class="rules-list">
                        <li><i class="fas fa-check-circle"></i> There are 5 main Account Heads (System Locked)</li>
                        <li><i class="fas fa-check-circle"></i> You can create unlimited Sub Accounts under Account Heads</li>
                        <li><i class="fas fa-check-circle"></i> You can nest Sub Accounts under other Sub Accounts</li>
                        <li><i class="fas fa-check-circle"></i> Posting Accounts can ONLY be created under Sub Accounts</li>
                        <li><i class="fas fa-check-circle"></i> Posting Accounts cannot be created directly under Account Heads</li>
                    </ul>
                </div>
                
                <div class="help-tip">
                    <h4><i class="fas fa-lightbulb"></i> Quick Tip</h4>
                    <p>Click on the arrow next to an account to expand or collapse its sub-accounts. You can also use the Expand All/Collapse All buttons above.</p>
                </div>
            </div>

            <!-- CoA Panel -->
            <div class="coa-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-code-branch" style="color: var(--primary); margin-right: 10px;"></i>Account Hierarchy</h2>
                    <div class="account-creation-buttons">
                        <button class="btn btn-primary" id="addSubAccountBtn">
                            <i class="fas fa-plus"></i> Add Sub Account
                        </button>
                        <button class="btn btn-secondary" id="addPostingAccountBtn">
                            <i class="fas fa-file-invoice-dollar"></i> Add Posting Account
                        </button>
                    </div>
                </div>
                
                <div class="tree-controls">
                    <div>
                        <button class="btn btn-secondary" id="showManageableBtn">
                            <i class="fas fa-edit"></i> Show Manageable Only
                        </button>
                        <button class="btn btn-secondary" id="showAllBtn">
                            <i class="fas fa-eye"></i> Show All
                        </button>
                    </div>
                    <div class="account-stats">
                        <span style="font-size: 13px; color: var(--subtext);">
                            <i class="fas fa-cube"></i> <span id="totalAccounts">0</span> Accounts
                        </span>
                    </div>
                </div>
                
                <div class="coa-tree" id="coaTree">
                    <!-- CoA tree will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Sub Account Modal -->
    <div class="modal-overlay" id="subAccountModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-folder-plus" style="color: var(--primary); margin-right: 10px;"></i>Add Sub Account</h3>
                <button class="modal-close" id="closeSubAccountModalBtn">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="modal-info">
                    <p><strong>Note:</strong> Sub Accounts can be created under Account Heads or other Sub Accounts. You can nest Sub Accounts to create deeper hierarchies.</p>
                </div>
                
                <form id="subAccountForm">
                    <div class="form-group">
                        <label class="form-label" for="subAccountParent">Parent Account</label>
                        <select class="form-input" id="subAccountParent" required>
                            <option value="">Select parent account</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="subAccountId">Account ID</label>
                            <input type="text" class="form-input" id="subAccountId" placeholder="e.g., 111" required>
                            <small style="color: var(--subtext); font-size: 12px;">Typically follows parent ID pattern (e.g., 11 → 111)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="subAccountName">Account Name</label>
                            <input type="text" class="form-input" id="subAccountName" placeholder="e.g., Current Assets" required>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelSubAccountBtn">Cancel</button>
                <button class="btn btn-primary" id="saveSubAccountBtn">Save Sub Account</button>
            </div>
        </div>
    </div>

    <!-- Edit Sub Account Modal -->
    <div class="modal-overlay" id="editSubAccountModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary); margin-right: 10px;"></i>Edit Sub Account</h3>
                <button class="modal-close" id="closeEditSubAccountModalBtn">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="editSubAccountForm">
                    <input type="hidden" id="editSubAccountId">
                    
                    <div class="form-group">
                        <label class="form-label" for="editSubAccountParent">Parent Account</label>
                        <select class="form-input" id="editSubAccountParent" required>
                            <option value="">Select parent account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="editSubAccountName">Account Name</label>
                        <input type="text" class="form-input" id="editSubAccountName" required>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEditSubAccountBtn">Cancel</button>
                <button class="btn btn-primary" id="updateSubAccountBtn">Update Sub Account</button>
            </div>
        </div>
    </div>

    <!-- Edit Posting Account Modal -->
    <div class="modal-overlay" id="editPostingAccountModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color: var(--primary); margin-right: 10px;"></i>Edit Posting Account</h3>
                <button class="modal-close" id="closeEditPostingAccountModalBtn">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="editPostingAccountForm">
                    <input type="hidden" id="editPostingAccountId">
                    
                    <div class="form-group">
                        <label class="form-label" for="editPostingAccountParent">Parent Sub Account</label>
                        <select class="form-input" id="editPostingAccountParent" required>
                            <option value="">Select parent sub account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="editPostingAccountName">Account Name</label>
                        <input type="text" class="form-input" id="editPostingAccountName" required>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelEditPostingAccountBtn">Cancel</button>
                <button class="btn btn-primary" id="updatePostingAccountBtn">Update Posting Account</button>
            </div>
        </div>
    </div>

    <!-- Add Posting Account Modal -->
    <div class="modal-overlay" id="postingAccountModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-file-invoice-dollar" style="color: var(--primary); margin-right: 10px;"></i>Add Posting Account</h3>
                <button class="modal-close" id="closePostingAccountModalBtn">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="modal-info">
                    <p><strong>Important:</strong> Posting Accounts can ONLY be created under Sub Accounts (not directly under Account Heads). These are the actual accounts where transactions are recorded.</p>
                </div>
                
                <form id="postingAccountForm">
                    <div class="form-group">
                        <label class="form-label" for="postingAccountParent">Parent Sub Account</label>
                        <select class="form-input" id="postingAccountParent" required>
                            <option value="">Select parent sub account</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                        <small style="color: var(--subtext); font-size: 12px;">Only Sub Accounts are shown (not Account Heads)</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="postingAccountId">Account ID</label>
                            <input type="text" class="form-input" id="postingAccountId" placeholder="e.g., 1111" required>
                            <small style="color: var(--subtext); font-size: 12px;">Typically follows parent ID pattern (e.g., 111 → 1111)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="postingAccountName">Account Name</label>
                            <input type="text" class="form-input" id="postingAccountName" placeholder="e.g., Zahid Ghori" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Opening Balance Type</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="balanceType" value="debit" checked>
                                <span>Debit</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="balanceType" value="credit">
                                <span>Credit</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="balanceAmount">Opening Balance Amount</label>
                        <input type="number" class="form-input" id="balanceAmount" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelPostingAccountBtn">Cancel</button>
                <button class="btn btn-primary" id="savePostingAccountBtn">Save Posting Account</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/chart_of_accounts/coa.js"></script>
</body>
</html>