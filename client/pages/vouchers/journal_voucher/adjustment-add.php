<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Adjustment - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/vouchers/journal_voucher/adjustment-add.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Balance Adjustment</h1>
                <p class="subtitle">Correct customer or supplier balance using a journal adjustment entry</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='journal-list.php'">
                    <i class="fas fa-list"></i> View Journal List
                </button>
            </div>
        </div>

        <div id="alertBox" class="alert" style="display:none;"></div>

        <div class="card">
            <div class="card-title">Voucher Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date <span class="req">*</span></label>
                    <input type="date" id="voucherDate">
                </div>
                <div class="form-group">
                    <label>Voucher #</label>
                    <input type="text" id="voucherNumber" readonly placeholder="Auto-generated">
                </div>
                <div class="form-group">
                    <label>Company <span class="req">*</span></label>
                    <select id="company">
                        <option value="">Select Company</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Adjustment Mode Toggle -->
        <div class="card">
            <div class="card-title">Adjustment Mode</div>
            <div class="mode-toggle">
                <button class="mode-btn active" id="btnSingleMode" data-mode="single">
                    <i class="fas fa-user-edit"></i> Single Party Adjustment
                </button>
                <button class="mode-btn" id="btnCrossMode" data-mode="cross">
                    <i class="fas fa-exchange-alt"></i> Cross Party Adjustment
                </button>
            </div>
            <div class="mode-hint" id="modeHint">
                <i class="fas fa-info-circle"></i>
                <span id="modeHintText">Single party: adjust one customer or supplier balance against a contra account</span>
            </div>
        </div>

        <!-- Party 1 (always shown) -->
        <div class="card">
            <div class="card-title" id="party1CardTitle">Party Selection</div>

            <div class="party-toggle">
                <button class="party-btn active" data-type="customer" id="btnCustomer">
                    <i class="fas fa-user"></i> Customer
                </button>
                <button class="party-btn" data-type="supplier" id="btnSupplier">
                    <i class="fas fa-truck"></i> Supplier
                </button>
            </div>

            <div class="form-grid" style="margin-top:20px;">
                <div class="form-group">
                    <label id="partyLabel">Customer <span class="req">*</span></label>
                    <div class="searchable-dropdown">
                        <input type="text" id="partySearch" placeholder="Search customer..." autocomplete="off">
                        <div class="dropdown-list" id="partyDropdown"></div>
                    </div>
                    <input type="hidden" id="partyId">
                    <input type="hidden" id="partyCode">
                </div>
                <div class="form-group" id="subAccountGroup" style="display:none;">
                    <label>Sub Account</label>
                    <select id="subAccount">
                        <option value="">All / Main Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current Balance</label>
                    <div class="balance-display" id="balanceDisplay">
                        <span id="balanceAmount">—</span>
                        <span id="balanceLabel" class="balance-label"></span>
                    </div>
                </div>
                <!-- Single mode: new balance shown inside party1 card -->
                <div class="form-group" id="newBalSingleGroup">
                    <label>New Balance (After Adjustment)</label>
                    <div class="balance-display">
                        <span id="newBalSingleAmount">—</span>
                        <span id="newBalSingleLabel" class="balance-label"></span>
                    </div>
                </div>
                <!-- Cross mode: entry side badge + new balance shown inside party1 card -->
                <div class="form-group" id="party1EntryGroup" style="display:none;">
                    <label>Entry Side</label>
                    <div class="entry-side-badge" id="party1EntryBadge"></div>
                </div>
                <div class="form-group" id="party1NewBalGroup" style="display:none;">
                    <label>New Balance (After Adjustment)</label>
                    <div class="balance-display" id="newBalanceDisplay">
                        <span id="newBalanceAmount">—</span>
                        <span id="newBalanceLabel" class="balance-label"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Party 2 (cross-party mode only) -->
        <div class="card" id="party2Card" style="display:none;">
            <div class="card-title">Party 2 Selection</div>

            <div class="party-toggle">
                <button class="party-btn active" data-type="customer" id="btnCustomer2">
                    <i class="fas fa-user"></i> Customer
                </button>
                <button class="party-btn" data-type="supplier" id="btnSupplier2">
                    <i class="fas fa-truck"></i> Supplier
                </button>
            </div>

            <div class="form-grid" style="margin-top:20px;">
                <div class="form-group">
                    <label id="partyLabel2">Customer <span class="req">*</span></label>
                    <div class="searchable-dropdown">
                        <input type="text" id="partySearch2" placeholder="Search customer..." autocomplete="off">
                        <div class="dropdown-list" id="partyDropdown2"></div>
                    </div>
                    <input type="hidden" id="partyId2">
                    <input type="hidden" id="partyCode2">
                </div>
                <div class="form-group" id="subAccountGroup2" style="display:none;">
                    <label>Sub Account</label>
                    <select id="subAccount2">
                        <option value="">All / Main Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current Balance</label>
                    <div class="balance-display" id="balanceDisplay2">
                        <span id="balanceAmount2">—</span>
                        <span id="balanceLabel2" class="balance-label"></span>
                    </div>
                </div>
                <!-- Entry side badge + new balance inside party2 card -->
                <div class="form-group" id="party2EntryGroup" style="display:none;">
                    <label>Entry Side</label>
                    <div class="entry-side-badge" id="party2EntryBadge"></div>
                </div>
                <div class="form-group" id="party2NewBalGroup" style="display:none;">
                    <label>New Balance (After Adjustment)</label>
                    <div class="balance-display" id="newBalanceDisplay2">
                        <span id="newBalanceAmount2">—</span>
                        <span id="newBalanceLabel2" class="balance-label"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Adjustment Details</div>
            <div class="form-grid">
                <!-- Adj type: shown in both modes -->
                <div class="form-group" id="adjTypeGroup">
                    <label id="adjTypeLabel">Adjustment Type <span class="req">*</span></label>
                    <div class="adj-toggle">
                        <button class="adj-btn active debit-btn" data-type="debit" id="btnDebit">
                            <i class="fas fa-arrow-up"></i> Debit (Increase)
                        </button>
                        <button class="adj-btn credit-btn" data-type="credit" id="btnCredit">
                            <i class="fas fa-arrow-down"></i> Credit (Decrease)
                        </button>
                    </div>
                    <div class="adj-hint" id="adjHint">
                        <i class="fas fa-info-circle"></i>
                        <span id="adjHintText">Debit: Customer owes you more (balance increases)</span>
                    </div>
                </div>
                <!-- Single mode: contra account -->
                <div class="form-group" id="contraGroup">
                    <label>Contra Account <span class="req">*</span></label>
                    <div class="searchable-dropdown">
                        <input type="text" id="contraSearch" placeholder="Search account..." autocomplete="off">
                        <div class="dropdown-list" id="contraDropdown"></div>
                    </div>
                    <input type="hidden" id="contraAccountId">
                </div>
                <div class="form-group">
                    <label>Amount <span class="req">*</span></label>
                    <input type="number" id="amount" min="0.01" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group full-width">
                    <label>Description / Reason <span class="req">*</span></label>
                    <textarea id="description" placeholder="Reason for this adjustment..."></textarea>
                </div>
            </div>
        </div>

        <!-- Summary Preview -->
        <div class="card" id="summaryCard" style="display:none;">
            <div class="card-title"><i class="fas fa-eye"></i> Entry Preview</div>
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody id="previewBody"></tbody>
            </table>
        </div>

        <div class="form-actions">
            <button class="btn btn-secondary" id="resetBtn">
                <i class="fas fa-redo"></i> Reset
            </button>
            <button class="btn btn-primary" id="postBtn">
                <i class="fas fa-check-circle"></i> Post Adjustment
            </button>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Adjustment</h3>
                <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" id="confirmBtn">Post Adjustment</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/vouchers/journal_voucher/adjustment-add.js"></script>
</body>
</html>
