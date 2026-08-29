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
    <title>Receive Voucher - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/receive_voucher/receive-add.css?v=<?php echo time(); ?>">
</head>

<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Receive Voucher</h1>
            <div style="display: flex; align-items: center; gap: 16px;">
                <button type="button" id="customizeFieldsBtn" class="btn btn-secondary" style="height: 40px; padding: 0 16px;">
                    <i class="fas fa-sliders-h"></i>
                    Customize Fields
                </button>
                <div class="mode-toggle">
                    <label class="toggle-label">
                        <input type="checkbox" id="bulkModeToggle">
                        <span class="toggle-slider"></span>
                        <span class="toggle-text">Bulk Mode</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-container" id="singleModeForm">
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
                            <label for="recoveryOfficer">Recovery Officer</label>
                            <div class="searchable-dropdown">
                                <input type="text" id="recoveryOfficer" placeholder="Search employee..." autocomplete="off">
                                <div class="dropdown-list" id="recoveryOfficerDropdown">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="customerCode" class="required">Customer Code</label>
                            <div class="searchable-dropdown">
                                <input type="text" id="customerCode" placeholder="Search customer..." autocomplete="off" required>
                                <div class="dropdown-list" id="customerDropdown">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="previousBalance">Previous Balance</label>
                            <input type="text" id="previousBalance" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="remainingBalance">Remaining Balance</label>
                            <input type="text" id="remainingBalance" readonly>
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
                            <label for="dsrNo">DSR#</label>
                            <input type="text" id="dsrNo" placeholder="DSR-B-24-26-20251231-20260112" pattern="DSR-[A-Z]-\d{2}-\d{2}-\d{8}-\d{8}" title="Format: DSR-B-24-26-20251231-20260112">
                        </div>
                        <div class="form-group">
                            <label for="currency" class="required">Currency</label>
                            <select id="currency" required>
                                <option value="">Select Currency</option>
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
                        <div class="form-group hidden">
                            <label for="container">Container</label>
                            <select id="container">
                                <option value="">Select Container</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="adjustAmount">Adjust</label>
                            <input type="number" id="adjustAmount" step="0.01" min="0" placeholder="0.00" value="0">
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
                        <div class="form-group hidden" id="chequeDateGroup">
                            <label for="chequeDate">Cheque Date</label>
                            <input type="date" id="chequeDate">
                        </div>
                        <div class="form-group hidden" id="chequeNoGroup">
                            <label for="chequeNo">Cheque No</label>
                            <input type="text" id="chequeNo">
                        </div>
                        <div class="form-group">
                            <label for="paymentMethod" class="required">Payment Method</label>
                            <select id="paymentMethod" required>
                                <option value="">Select Payment Method</option>
                                <!-- Options will be populated by JS -->
                            </select>
                        </div>
                        <div class="form-group" id="againstPdcGroup">
                            <label for="againstPdc">Against PDC <span style="font-size:11px;color:var(--subtext);">Optional</span></label>
                            <select id="againstPdc">
                                <option value="">— Select Pending PDC —</option>
                            </select>
                            <div id="pdcRemainingInfo" style="display:none; margin-top:6px; font-size:12px; color:#e8b23f; font-weight:500;"></div>
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

        <!-- Bulk Mode Container -->
        <div class="bulk-container" id="bulkModeForm" style="display: none;">
            <div class="bulk-header">
                <div style="display: flex; gap: 16px; align-items: center; flex: 1; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; min-width: 250px;">
                        <label for="bulkCompany">Company</label>
                        <select id="bulkCompany" required>
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 180px;">
                        <label for="bulkJournalDate">Bulk Journal Date</label>
                        <input type="date" id="bulkJournalDate" title="Applies this date to all rows below">
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 160px;">
                        <label for="bulkCity">City</label>
                        <select id="bulkCity">
                            <option value="">Select City</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 160px;">
                        <label for="bulkZone">Zone</label>
                        <select id="bulkZone">
                            <option value="">Select Zone</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 160px;">
                        <label for="bulkArea">Area</label>
                        <select id="bulkArea">
                            <option value="">Select Area</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 250px;">
                        <label for="bulkRecoveryOfficer">Recovery Officer</label>
                        <div class="searchable-dropdown">
                            <input type="text" id="bulkRecoveryOfficer" placeholder="Search employee..." autocomplete="off">
                            <div class="dropdown-list" id="bulkRecoveryOfficerDropdown"></div>
                        </div>
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
                            <th>Customer</th>
                            <th>Previous Balance</th>
                            <th>Sub Account</th>
                            <th>Bill No</th>
                            <th>DSR#</th>
                            <th>Project</th>
                            <th>Container</th>
                            <th>Currency</th>
                            <th>Adjust</th>
                            <th>Amount</th>
                            <th>Remaining Balance</th>
                            <th>Payment Method</th>
                            <th>Against PDC</th>
                            <th>Bank Account</th>
                            <th>Cheque Date</th>
                            <th>Cheque No</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="bulkTableBody">
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--surface-2); font-weight: 600;">
                            <td colspan="9" style="text-align: right; padding: 12px;">Total:</td>
                            <td id="bulkTotalAmount" style="padding: 12px;">0.00</td>
                            <td colspan="8"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Customize Fields Modal -->
    <div class="modal" id="customizeFieldsModal">
        <div class="modal-content" style="max-width: 480px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-sliders-h"></i> Customize Fields</div>
                <button class="modal-close" id="customizeFieldsModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="text-align: left; padding: 24px;">

            <!-- NEW: Customer Display Format -->
    <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border-default);">
        <label style="display:block; margin-bottom:10px; font-weight:600; font-size:13px;">Customer Display</label>
        <div style="display:flex; gap:16px; flex-wrap: wrap;">
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                <input type="radio" name="customerDisplayFormat" value="both" id="custFmtBoth"> Code + Name
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                <input type="radio" name="customerDisplayFormat" value="code" id="custFmtCode"> Code Only
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                <input type="radio" name="customerDisplayFormat" value="name" id="custFmtName"> Name Only
            </label>
        </div>
    </div>
    <!-- END NEW -->
                <div style="margin-bottom: 16px; display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary" id="customizeSelectAllBtn" style="flex: 1; height: 36px; font-size: 13px;">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button type="button" class="btn btn-secondary" id="customizeDeselectAllBtn" style="flex: 1; height: 36px; font-size: 13px;">
                        <i class="fas fa-square"></i> Deselect All
                    </button>
                </div>
                <div id="customizeFieldsList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-default); display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" id="customizeResetBtn">Reset to Default</button>
                <button type="button" class="btn btn-primary" id="customizeSaveBtn">Save Preferences</button>
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

    <script src="../../../assets/js/vouchers/receive_voucher/receive-add.js?v=<?php echo time(); ?>"></script>
</body>

</html>