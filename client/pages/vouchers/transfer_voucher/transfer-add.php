<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transfer Voucher - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/transfer_voucher/transfer-add.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Transfer Voucher</h1>
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

        <!-- Single Mode -->
        <div class="form-container" id="singleVoucherForm">
            <form id="transferVoucherForm">
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
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency" class="required">Currency</label>
                            <select id="currency" required>
                                <option value="">Select Currency</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount" class="required">Amount</label>
                            <div class="currency-input">
                                <span class="currency-symbol" id="currencySymbol">$</span>
                                <input type="number" id="amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="paymentMethod" class="required">Payment Method</label>
                            <select id="paymentMethod" required>
                                <option value="">Select Payment Method</option>
                            </select>
                        </div>
                        <div class="form-group hidden" id="bankAccountGroup">
                            <label for="bankAccount">Bank Account</label>
                            <select id="bankAccount">
                                <option value="">Select Bank Account</option>
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
                        <div class="form-group hidden" id="slipNoGroup">
                            <label for="slipNo">Slip No</label>
                            <input type="text" id="slipNo" placeholder="Enter slip number">
                        </div>
                        <div class="form-group hidden" id="attachmentGroup">
                            <label for="slipAttachment">Attachment</label>
                            <input type="file" id="slipAttachment" accept=".png,.jpg,.jpeg,.gif,.pdf,.webp,.avif">
                            <div id="attachmentPreview" style="display:none; margin-top:6px; font-size:12px; color:var(--subtext); display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-file"></i>
                                <span id="attachmentName"></span>
                                <button type="button" id="removeAttachment" style="background:none;border:none;color:var(--error);cursor:pointer;"><i class="fas fa-times"></i></button>
                            </div>
                        </div>                    </div>
                </div>

                <!-- FROM Party -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-arrow-up" style="color: var(--error);"></i>
                        From (Payer)
                    </h2>
                    <div class="party-type-toggle" id="fromTypeToggle">
                        <button type="button" class="party-btn active" data-type="customer" data-side="from">
                            <i class="fas fa-user"></i> Customer
                        </button>
                        <button type="button" class="party-btn" data-type="supplier" data-side="from">
                            <i class="fas fa-truck"></i> Supplier
                        </button>
                    </div>
                    <div class="form-grid" style="margin-top: 16px;">
                        <div class="form-group">
                            <label for="fromParty" class="required" id="fromPartyLabel">Customer Code</label>
                            <div class="searchable-dropdown">
                                <input type="text" id="fromParty" placeholder="Search customer..." autocomplete="off" required>
                                <div class="dropdown-list" id="fromPartyDropdown"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="fromSubAccount">Sub Account</label>
                            <select id="fromSubAccount">
                                <option value="">Select Sub Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fromPreviousBalance">Previous Balance</label>
                            <input type="text" id="fromPreviousBalance" readonly>
                        </div>
                        <div class="form-group">
                            <label for="fromRemainingBalance">Remaining Balance</label>
                            <input type="text" id="fromRemainingBalance" readonly>
                        </div>
                    </div>
                </div>

                <!-- TO Party -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-arrow-down" style="color: var(--success);"></i>
                        To (Receiver)
                    </h2>
                    <div class="party-type-toggle" id="toTypeToggle">
                        <button type="button" class="party-btn active" data-type="customer" data-side="to">
                            <i class="fas fa-user"></i> Customer
                        </button>
                        <button type="button" class="party-btn" data-type="supplier" data-side="to">
                            <i class="fas fa-truck"></i> Supplier
                        </button>
                    </div>
                    <div class="form-grid" style="margin-top: 16px;">
                        <div class="form-group">
                            <label for="toParty" class="required" id="toPartyLabel">Customer Code</label>
                            <div class="searchable-dropdown">
                                <input type="text" id="toParty" placeholder="Search customer..." autocomplete="off" required>
                                <div class="dropdown-list" id="toPartyDropdown"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="toSubAccount">Sub Account</label>
                            <select id="toSubAccount">
                                <option value="">Select Sub Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="toPreviousBalance">Previous Balance</label>
                            <input type="text" id="toPreviousBalance" readonly>
                        </div>
                        <div class="form-group">
                            <label for="toRemainingBalance">Remaining Balance</label>
                            <input type="text" id="toRemainingBalance" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" placeholder="Enter description..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-redo"></i> Reset Voucher
                    </button>
                    <button type="submit" class="btn btn-primary btn-lock" id="postBtn">
                        <i class="fas fa-paper-plane"></i> Post Voucher
                    </button>
                </div>
            </form>
        </div>

        <!-- Bulk Mode -->
        <div class="form-container" id="bulkVoucherForm" style="display: none;">
            <div class="bulk-controls">
                <div style="display: flex; gap: 16px; align-items: center; flex: 1;">
                    <div class="form-group" style="margin: 0; min-width: 220px;">
                        <label for="bulkCompany">Company</label>
                        <select id="bulkCompany" required>
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 180px;">
                        <label for="bulkCurrency">Currency</label>
                        <select id="bulkCurrency">
                            <option value="">Select Currency</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-secondary" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add Row
                    </button>
                </div>
                <button type="button" class="btn btn-primary" id="postBulkBtn">
                    <i class="fas fa-paper-plane"></i> Post All Vouchers
                </button>
            </div>
            <div class="table-wrapper">
                <table class="bulk-table" id="bulkTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From Type</th>
                            <th>From Party</th>
                            <th>To Type</th>
                            <th>To Party</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Bank Account</th>
                            <th>Cheque Date</th>
                            <th>Cheque No</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bulkTableBody"></tbody>
                    <tfoot>
                        <tr style="background: var(--surface-2); font-weight: 600;">
                            <td colspan="5" style="text-align: right; padding: 12px;">Total:</td>
                            <td id="bulkTotalAmount" style="padding: 12px;">0.00</td>
                            <td colspan="6"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/transfer_voucher/transfer-add.js"></script>
</body>
</html>
