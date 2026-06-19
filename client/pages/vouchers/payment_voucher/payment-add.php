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
    <title>Payment Voucher - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/payment_voucher/payment-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Payment Voucher</h1>
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
                <div class="mode-toggle">
                    <label class="toggle-label">
                        <input type="radio" name="voucherMode" value="single" checked>
                        <span>Single</span>
                    </label>
                    <label class="toggle-label">
                        <input type="radio" name="voucherMode" value="bulk">
                        <span>Bulk</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-container" id="singleVoucherForm">
            <form id="paymentVoucherForm">
                <div class="form-section">
                    <h2 class="section-title">Voucher Details</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="voucherDate" class="required">Voucher Date</label>
                            <input type="date" id="voucherDate" required>
                        </div>
                        <div class="form-group">
                            <label for="voucherNumber" class="required">Voucher #</label>
                            <input type="text" id="voucherNumber" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="company" class="required">Company</label>
                            <select id="company" required>
                                <option value="">Select Company</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supplierCode" class="required" id="supplierLabel">Supplier Code</label>
                            <div class="searchable-dropdown">
                                <input type="text" id="supplierCode" placeholder="Search supplier..." autocomplete="off" required>
                                <div class="dropdown-list" id="supplierDropdown">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="subAccount">Sub Account</label>
                            <select id="subAccount">
                                <option value="">Select Sub Account</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="billNo">Bill No</label>
                            <div class="searchable-dropdown">
                                <input type="text" id="billNo" placeholder="Search bill..." autocomplete="off">
                                <div class="dropdown-list" id="billDropdown">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="currency" class="required">Currency</label>
                            <select id="currency" required>
                                <option value="">Select Currency</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount" class="required">Amount</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="currencySymbol">$</span>
                                <input type="number" id="amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="form-group hidden" id="bankAccountGroup">
                            <label for="bankAccount">Bank Account</label>
                            <select id="bankAccount">
                                <option value="">Select Bank Account</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group hidden" id="chequeNoGroup">
                            <label for="chequeNo">Cheque No</label>
                            <input type="text" id="chequeNo">
                        </div>
                        <div class="form-group hidden" id="chequeDateGroup">
                            <label for="chequeDate">Cheque Date</label>
                            <input type="date" id="chequeDate">
                        </div>
                        <div class="form-group">
                            <label for="paymentMethod" class="required">Payment Method</label>
                            <select id="paymentMethod" required>
                                <option value="">Select Payment Method</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="project">Project</label>
                            <select id="project">
                                <option value="">Select Project</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="attachment">Attachment</label>
                            <input type="file" id="attachment" accept=".png,.jpg,.jpeg,.gif,.pdf,.webp,.avif">
                            <div class="attachment-preview" id="attachmentPreview">
                                <div class="attachment-thumbnail" id="attachmentThumbnail">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="attachment-info">
                                    <div class="attachment-name" id="attachmentName"></div>
                                    <div class="attachment-size" id="attachmentSize"></div>
                                </div>
                                <button type="button" class="remove-attachment" id="removeAttachment">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" placeholder="Enter description..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-redo"></i>
                        Reset Voucher
                    </button>
                    <button type="submit" class="btn btn-primary btn-lock" id="postBtn">
                        <i class="fas fa-paper-plane"></i>
                        Post Voucher
                    </button>
                </div>
            </form>
        </div>

        <!-- Bulk Voucher Form -->
        <div class="form-container" id="bulkVoucherForm" style="display: none;">
            <div class="bulk-controls">
                <div style="display: flex; gap: 16px; align-items: center; flex: 1;">
                    <div class="form-group" style="margin: 0; min-width: 250px;">
                        <label for="bulkCompany">Company</label>
                        <select id="bulkCompany" required>
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-secondary" id="addRowBtn">
                        <i class="fas fa-plus"></i>
                        Add Row
                    </button>
                </div>
                <button type="button" class="btn btn-primary" id="postBulkBtn">
                    <i class="fas fa-paper-plane"></i>
                    Post All Vouchers
                </button>
            </div>
            <div class="table-wrapper">
                <table class="bulk-table" id="bulkTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Sub Account</th>
                            <th>Bill No</th>
                            <th>Currency</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Project</th>
                            <th>Bank Account</th>
                            <th>Cheque Date</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bulkTableBody">
                        <!-- Rows will be added dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for attachment preview -->
    <div class="modal" id="attachmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Attachment Preview</div>
                <button class="modal-close" id="modalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be populated by JS -->
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/payment_voucher/payment-add.js"></script>
</body>
</html>