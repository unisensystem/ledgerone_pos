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
    <title>LedgerOne ERP - Bank Statement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/banking/bank_statement/bank-statement.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1 class="page-title">Bank Statement</h1>
                <p class="page-subtitle">Every transaction in and out of a bank account, with running balance</p>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="printBtn" disabled>
                    <i class="fas fa-print"></i> Print Statement
                </button>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card mb-24">
            <h2 class="card-title"><i class="fas fa-filter"></i> Select Account &amp; Period</h2>
            <div class="filters-grid">
                <div class="filter-group" style="grid-column: span 2;">
                    <label class="filter-label">Bank Account *</label>
                    <select class="filter-input" id="bankAccountFilter">
                        <option value="">Select a bank account...</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Date Range</label>
                    <select class="filter-input" id="dateRangePreset">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="filter-group" id="fromDateGroup" style="display:none;">
                    <label class="filter-label">From Date</label>
                    <input type="date" class="filter-input" id="fromDate">
                </div>
                <div class="filter-group" id="toDateGroup" style="display:none;">
                    <label class="filter-label">To Date</label>
                    <input type="date" class="filter-input" id="toDate">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Transaction Type</label>
                    <select class="filter-input" id="txTypeFilter">
                        <option value="all">All Transactions</option>
                        <option value="deposits">Deposits Only</option>
                        <option value="withdrawals">Withdrawals Only</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" class="filter-input" id="searchFilter" placeholder="Voucher #, name...">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-ghost" id="resetFiltersBtn">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button class="btn btn-primary" id="generateBtn">
                    <i class="fas fa-file-invoice-dollar"></i> Generate Statement
                </button>
            </div>
        </div>

        <!-- Everything below only appears once a statement has been generated -->
        <div id="statementResult" style="display: none;">
            <!-- Bank letterhead -->
            <div class="statement-header">
                <div class="bank-identity" style="display:flex; align-items:center; gap:14px;">
                    <div id="shLogoWrapper"></div>
                    <div>
                        <h2 id="shBankName">&mdash;</h2>
                        <div class="account-title" id="shAccountTitle">&mdash;</div>
                        <span class="status-badge" id="shStatusBadge" style="display:none;"></span>
                    </div>
                </div>
                <div class="bank-meta">
                    <div><span class="meta-label">Account #</span> <span id="shAccountNumber">&mdash;</span></div>
                    <div><span class="meta-label">Account Type</span> <span id="shAccountType">&mdash;</span></div>
                    <div><span class="meta-label">Currency</span> <span id="shCurrency">&mdash;</span></div>
                    <div><span class="meta-label">IBAN</span> <span id="shIban">&mdash;</span></div>
                    <div><span class="meta-label">SWIFT Code</span> <span id="shSwift">&mdash;</span></div>
                    <div><span class="meta-label">Branch</span> <span id="shBranch">&mdash;</span></div>
                </div>
            </div>

            <!-- Extra bank/branch/contact chips — only renders when data actually exists -->
            <div class="extra-details-bar" id="shExtraDetails" style="display:none;"></div>

            <div class="statement-meta-bar">
                <span><i class="fas fa-hashtag"></i> Ref: <strong id="shStatementRef">&mdash;</strong></span>
                <span><i class="fas fa-user"></i> Generated By: <strong id="shGeneratedBy">&mdash;</strong></span>
                <span><i class="fas fa-clock"></i> Generated At: <strong id="shGeneratedAt">&mdash;</strong></span>
            </div>

            <p class="statement-period" id="shPeriod"></p>

            <!-- Balance Summary -->
            <div class="balances-section mb-24">
                <div class="balance-card opening-balance">
                    <div>
                        <div class="balance-label">Opening Balance</div>
                        <div class="balance-value" id="sumOpening">0.00</div>
                        <div class="balance-subtext" id="sumOpeningAsOf"></div>
                    </div>
                    <i class="fas fa-wallet" style="color: var(--primary); font-size: 24px;"></i>
                </div>
                <div class="balance-card total-inflow">
                    <div>
                        <div class="balance-label">Total Deposits</div>
                        <div class="balance-value" id="sumDeposits">0.00</div>
                        <div class="balance-subtext" id="sumDepositsCount"></div>
                    </div>
                    <i class="fas fa-arrow-down" style="color: var(--success); font-size: 24px;"></i>
                </div>
                <div class="balance-card total-outflow">
                    <div>
                        <div class="balance-label">Total Withdrawals</div>
                        <div class="balance-value" id="sumWithdrawals">0.00</div>
                        <div class="balance-subtext" id="sumWithdrawalsCount"></div>
                    </div>
                    <i class="fas fa-arrow-up" style="color: var(--error); font-size: 24px;"></i>
                </div>
                <div class="balance-card closing-balance">
                    <div>
                        <div class="balance-label">Closing Balance</div>
                        <div class="balance-value" id="sumClosing">0.00</div>
                        <div class="balance-subtext" id="sumNetChange"></div>
                    </div>
                    <i class="fas fa-chart-line" style="color: var(--success); font-size: 24px;"></i>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card">
                <h2 class="card-title"><i class="fas fa-list"></i> Transactions <span class="tx-count-badge" id="txCountBadge"></span></h2>
                <div class="table-container">
                    <table id="statementTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Voucher #</th>
                                <th class="text-right">Deposit</th>
                                <th class="text-right">Withdrawal</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="statementTableBody"></tbody>
                        <tfoot>
                            <tr class="totals-row">
                                <td colspan="3"><strong>TOTALS</strong></td>
                                <td class="text-right positive"><strong id="footDeposits">0.00</strong></td>
                                <td class="text-right negative"><strong id="footWithdrawals">0.00</strong></td>
                                <td class="text-right"><strong id="footClosing">0.00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Before anything has been generated -->
        <div class="card" id="emptyState">
            <div class="empty-state">
                <i class="fas fa-university"></i>
                <p>Select a bank account and click <strong>Generate Statement</strong> to view its transaction history.</p>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/banking/bank_statement/bank-statement.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/banking/bank_statement/bank-statement.js') ?>"></script>
</body>
</html>
