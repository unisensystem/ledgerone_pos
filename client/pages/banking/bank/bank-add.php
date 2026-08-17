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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Bank Account Entry</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="../../../assets/css/banking/bank/bank-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <div class="logo-icon">F</div>
                <div class="logo-text">
                    <h1>LedgerOne ERP</h1>
                    <p>Bank Account Management</p>
                </div>
            </div>
        </header>

        <!-- Main Form -->
        <div class="form-container">
            <div class="form-header">
                <h2>Add New Bank Account <span class="status-indicator status-active">Active</span></h2>
                <p>Enter bank account details for your fuel station operations</p>
            </div>

            <form id="bankAccountForm" class="form-body">
                <!-- Bank Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Bank Information</h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <label class="form-label" for="bankName">Bank Name *</label>
                            <input type="text" id="bankName" class="form-input" placeholder="Enter bank name" required>
                            <div class="helper-text">Full legal name of the bank</div>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="accountNumber">Account Number *</label>
                            <input type="text" id="accountNumber" class="form-input" placeholder="Enter account number" required>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="accountTitle">Account Title</label>
                            <input type="text" id="accountTitle" class="form-input" placeholder="Account holder name">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="accountType">Account Type *</label>
                            <select id="accountType" class="form-select" required>
                                <option value="">Select account type</option>
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
                            <label class="form-label">MFB (Microfinance Bank)</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="isMfb">
                                    <label for="isMfb">This is an MFB account</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row" id="mfbSection">
                            <label class="form-label" for="mfbName">MFB Name</label>
                            <input type="text" id="mfbName" class="form-input" placeholder="Enter MFB name">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="currency">Currency *</label>
                            <select id="currency" class="form-select" required>
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>

                        <div class="form-row full-width">
                            <label class="form-label" for="bankLogo">Bank Logo</label>
                            <input type="file" id="bankLogo" class="form-input" accept="image/*">
                            <div class="helper-text">Upload bank logo (JPG, PNG, GIF - Max 2MB)</div>
                            <div id="logoPreview" style="margin-top: 10px; display: none;">
                                <img id="logoPreviewImg" style="max-width: 150px; max-height: 150px; border-radius: 8px;" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branch Details Section -->
                <div class="form-section">
                    <h3 class="section-title">Branch Details</h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <label class="form-label" for="branchName">Branch Name *</label>
                            <input type="text" id="branchName" class="form-input" placeholder="Enter branch name" required>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="branchCode">Branch Code</label>
                            <input type="text" id="branchCode" class="form-input" placeholder="Enter branch code">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="branchCity">Branch City *</label>
                            <input type="text" id="branchCity" class="form-input" placeholder="Enter city" required>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="branchState">Branch State *</label>
                            <input type="text" id="branchState" class="form-input" placeholder="Enter state" required>
                        </div>

                        <div class="form-row full-width">
                            <label class="form-label" for="branchAddress">Branch Address</label>
                            <textarea id="branchAddress" class="form-textarea" placeholder="Full branch address"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Financial Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Financial Information</h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <label class="form-label" for="balanceType">Balance Type</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" id="debit" name="balanceType" value="debit" checked>
                                    <label for="debit">Debit Balance</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" id="credit" name="balanceType" value="credit">
                                    <label for="credit">Credit Balance</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="openingBalance">Opening Balance (PKR)</label>
                            <input type="number" id="openingBalance" class="form-input" placeholder="0.00" step="0.01" value="0.00">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="asOfDate">As of Date *</label>
                            <input type="date" id="asOfDate" class="form-input" required>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Additional Information</h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <label class="form-label" for="iban">IBAN</label>
                            <input type="text" id="iban" class="form-input" placeholder="International Bank Account Number">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="swiftCode">SWIFT Code</label>
                            <input type="text" id="swiftCode" class="form-input" placeholder="Bank SWIFT code">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="contactPerson">Contact Person</label>
                            <input type="text" id="contactPerson" class="form-input" placeholder="Bank relationship manager">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="contactNumber">Contact Number</label>
                            <input type="tel" id="contactNumber" class="form-input" placeholder="Contact phone number">
                        </div>

                        <div class="form-row">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" class="form-input" placeholder="bank@example.com">
                        </div>

                        <div class="form-row full-width">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea id="notes" class="form-textarea" placeholder="Additional notes about this account"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Account Status -->
                <div class="form-section">
                    <h3 class="section-title">Account Status</h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <label class="form-label">Account Status</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="isActive" checked>
                                    <label for="isActive">Account is active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Create Bank Account</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/banking/bank/bank-add.js"></script>
</body>
</html>