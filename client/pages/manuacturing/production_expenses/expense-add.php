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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>LedgerOne · Production Expenses Entry</title>
    <!-- Google Fonts: Inter + IBM Plex Mono for accounting vibe -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=IBM+Plex+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Font Awesome 6 (free icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel=stylesheet href="../../../assets/css/manufacturing/production_expenses/expense-add.css">
</head>

<body>
    <div class="app-container">

        <div class="page">
            <!-- header section -->
            <div class="section-label"><i class="fas fa-info-circle"></i> Header Information</div>
            <div class="card">
                <div class="card-inner">
                    <div class="grid-4" style="margin-bottom: 20px;">
                        <div class="field">
                            <label>Date <span class="req">*</span></label>
                            <input type="date" id="entryDate">
                        </div>
                        <div class="field">
                            <label>Allocation Method <span class="req">*</span></label>
                            <select id="allocMethod" onchange="toggleAllocationMode()">
                                <option value="direct">Direct (Single Batch)</option>
                                <option value="multi">Multi-Batch Split</option>
                                <option value="period">Period Overhead (All Batches)</option>
                            </select>
                        </div>
                        <div class="field span-2" id="singlePOField">
                            <label>Production Order <span class="req">*</span></label>
                            <input type="text" id="prodOrderSearch" placeholder="Search production order..."
                                autocomplete="off">
                            <select id="prodOrder" onchange="loadPODetails()" style="display:none;">
                                <option value="">— Select Production Order —</option>
                            </select>
                            <div id="prodOrderDropdown" class="search-dropdown"></div>
                            <span class="field-hint" id="poHint">Select PO to auto-fill product details</span>
                        </div>
                    </div>
                    <div id="multiBatchSection" style="display:none; margin-bottom:20px;">
                        <div class="field">
                            <label>Select Production Orders (Multiple) <span class="req">*</span></label>
                            <div id="multiBatchList" class="multi-batch-list"></div>
                            <button type="button" class="btn-add-row" onclick="addBatchRow()" style="margin-top:8px;"><i class="fas fa-plus-circle"></i> Add Batch</button>
                        </div>
                    </div>
                    <div id="periodSection" style="display:none; margin-bottom:20px;">
                        <div class="grid-4">
                            <div class="field">
                                <label>Period From <span class="req">*</span></label>
                                <input type="date" id="periodFrom">
                            </div>
                            <div class="field">
                                <label>Period To <span class="req">*</span></label>
                                <input type="date" id="periodTo">
                            </div>
                            <div class="field span-2">
                                <label>Allocation Basis <span class="req">*</span></label>
                                <select id="allocationBasis">
                                    <option value="qty">By Quantity Produced</option>
                                    <option value="hours">By Machine Hours</option>
                                    <option value="equal">Equal Distribution</option>
                                </select>
                            </div>
                        </div>
                        <div class="field" style="margin-top:12px;">
                            <button type="button" class="btn-secondary" onclick="loadPeriodBatches()" style="width:100%;"><i class="fas fa-sync"></i> Load Batches in Period</button>
                        </div>
                        <div id="periodBatchesPreview" style="margin-top:12px; padding:12px; background:var(--surface-1); border-radius:8px; display:none;">
                            <div style="font-size:12px; color:var(--subtext); margin-bottom:8px;">Batches Found:</div>
                            <div id="periodBatchesList" style="font-size:11px; font-family:var(--mono);"></div>
                        </div>
                    </div>
                    <div class="grid-4" id="singlePOInfo">
                        <div class="field">
                            <label>Finished Good</label>
                            <input type="text" id="fgName" readonly placeholder="Auto-filled">
                        </div>
                        <div class="field">
                            <label>Planned Qty</label>
                            <input type="text" id="plannedQty" readonly placeholder="—">
                        </div>
                    </div>
                    <div class="grid-4" style="margin-top: 20px;">
                        <div class="field span-2">
                            <label>Narration</label>
                            <input type="text" id="narration" placeholder="Batch-level note">
                        </div>
                    </div>
                </div>
                <div class="info-row" id="infoRow" style="display: none;">
                    <div class="info-pill"><span class="pill-label">PO Date</span><span class="pill-value"
                            id="infoPoDate">—</span></div>
                    <div class="info-pill"><span class="pill-label">BOM</span><span class="pill-value"
                            id="infoBom">—</span></div>
                    <div class="info-pill"><span class="pill-label">Status</span><span class="pill-value accent"
                            id="infoStatus">—</span></div>
                    <div class="info-pill"><span class="pill-label">Posted Expenses</span><span class="pill-value"
                            id="infoPrevExp">Rs. 0</span></div>
                </div>
            </div>

            <!-- expense lines -->
            <div class="section-label"><i class="fas fa-receipt"></i> Expense Lines</div>
            <div class="card">
                <div class="table-wrap">
                    <table class="line-table" id="lineTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>GL Debit Account</th>
                                <th>Description</th>
                                <th>Entry Mode</th>
                                <th>Hours</th>
                                <th>Rate (Rs.)</th>
                                <th>Amount (Rs.)</th>
                                <th>Vendor</th>
                                <th>Payment Status</th>
                                <th>Payment Mode</th>
                                <th>Bank Account</th>
                                <th>Cheque No</th>
                                <th>Cheque Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="lineBody"></tbody>
                    </table>
                </div>
                <div class="add-row-bar">
                    <button class="btn-add-row" onclick="addRow()"><i class="fas fa-plus-circle"></i> Add Expense
                        Line</button>
                </div>
                <div class="totals-bar">
                    <div class="total-chip primary"><span class="chip-label">Total Amount</span><span class="chip-value"
                            id="totalAmount">Rs. 0</span></div>
                </div>
            </div>

            <!-- GL Preview -->
            <div class="section-label"><i class="fas fa-book"></i> GL Posting Preview</div>
            <div class="gl-preview">
                <div class="gl-header"><span><i class="fas fa-clipboard-list"></i> Journal Entries (Auto)</span><span
                        id="glStatus">No entries</span></div>
                <div class="gl-body" id="glBody">
                    <div class="gl-empty">Add expense lines to preview double-entry.</div>
                </div>
            </div>

            <div class="actions-bar">
                <button class="btn btn-secondary" onclick="resetForm()">Discard Changes</button>
                <button class="btn btn-primary" onclick="saveAndPost()"><i class="fas fa-check-circle"></i> Save &
                    Post</button>
            </div>
        </div>
        <div class="toast" id="toast"><i class="fas fa-check-circle" id="toastIcon"></i>
            <div><strong id="toastTitle"></strong>
                <div id="toastMsg"></div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/manufacturing/production_expenses/expense-add.js"></script>
</body>

</html>