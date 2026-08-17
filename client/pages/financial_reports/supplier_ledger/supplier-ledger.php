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

// Get base currency symbol and all tenant currency symbols
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'];

$stmt2 = $pdo->prepare("
    SELECT c.id, c.name, c.symbol, tc.is_base_currency
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ?
    ORDER BY tc.is_base_currency DESC
");
$stmt2->execute([$_SESSION['tenant_id']]);
$all_symbols = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Supplier Ledger Report | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/supplier_ledger/supplier-ledger.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Supplier Ledger Report</h1>
            <div class="ledger-type">
                <div class="ledger-type-btn active" id="summary-ledger-btn">Summary Ledger</div>
                <div class="ledger-type-btn" id="detailed-ledger-btn">Detailed Ledger</div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Filters</h2>
            <div class="filters">
                <div class="form-group">
                    <label class="form-label" for="company-filter">Company</label>
                    <select class="form-control" id="company-filter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="currency-filter">Currency</label>
                    <select class="form-control" id="currency-filter">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="symbol-override">Symbol</label>
                    <select class="form-control" id="symbol-override">
                        <?php foreach ($all_symbols as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['symbol']); ?>" <?php echo $s['is_base_currency'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['symbol']); ?> - <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="supplier-code">Supplier Code</label>
                    <input type="text" class="form-control" id="supplier-search" placeholder="Search suppliers..." autocomplete="off">
                    <select class="form-control" id="supplier-code" style="display: none;">
                        <option value="">Select Supplier</option>
                    </select>
                    <div id="supplier-dropdown" class="supplier-dropdown"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="from-date">From Date</label>
                    <input type="date" class="form-control" id="from-date">
                    <div class="error-message" id="from-date-error">From Date cannot be greater than To Date</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="to-date">To Date</label>
                    <input type="date" class="form-control" id="to-date">
                    <div class="error-message" id="to-date-error">To Date cannot be less than From Date</div>
                </div>
                <div class="form-group" id="sub-account-filter" style="display: none;">
                    <label class="form-label" for="sub-account">Sub Account</label>
                    <select class="form-control" id="sub-account">
                        <option value="">All Sub Accounts</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="load-ledger-btn">
                    <i class="fas fa-sync-alt"></i> Load Ledger
                </button>
                <button class="btn btn-secondary" id="reset-filters-btn">
                    <i class="fas fa-undo"></i> Reset Filters
                </button>
                <button class="btn btn-ghost" id="share-ledger-btn" style="display: none;">
                    <i class="fas fa-share-alt"></i> Share Ledger
                </button>
                <div class="dropdown">
                    <button class="btn btn-ghost dropdown-toggle" id="export-dropdown">
                        <i class="fas fa-download"></i> Export <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="export-menu">
                        <a href="#" class="dropdown-item" id="print-ledger">
                            <i class="fas fa-print"></i> Print Ledger
                        </a>
                        <a href="#" class="dropdown-item" id="pdf-ledger">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>
                       <a href="#" class="dropdown-item" id="excel-ledger">
    <i class="fas fa-file-excel"></i> Export To Excel
</a>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-code"></i> Export JSON
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="background: #f0f9ff; border-left: 4px solid #1f7bff; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 14px; color: #1f7bff;"><strong>Help:</strong> Dr (Debit) = Amount you owe to supplier | Cr (Credit) = Amount supplier owes you</p>
        </div>

        <div class="card">
            <h2 class="card-title" id="ledger-title">Summary Ledger</h2>
            <div class="table-container">
                <table id="summary-ledger-table">
                    <thead>
                        <tr>
                            <th>Supplier Name</th>
                            <th>Opening Balance</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Closing Balance (Dr/Cr)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>ABC Petroleum</td>
                            <td class="balance-positive">$5,250.00 Dr</td>
                            <td>$12,500.00</td>
                            <td>$8,750.00</td>
                            <td class="balance-positive">$9,000.00 Dr</td>
                        </tr>
                        <tr>
                            <td>XYZ Fuels</td>
                            <td class="balance-negative">$3,200.00 Cr</td>
                            <td>$9,800.00</td>
                            <td>$12,000.00</td>
                            <td class="balance-negative">$5,400.00 Cr</td>
                        </tr>
                        <tr>
                            <td>Global Energy</td>
                            <td class="balance-positive">$1,500.00 Dr</td>
                            <td>$7,200.00</td>
                            <td>$6,500.00</td>
                            <td class="balance-positive">$2,200.00 Dr</td>
                        </tr>
                        <tr class="totals-row">
                            <td><strong>Totals</strong></td>
                            <td><strong>$3,550.00 Dr</strong></td>
                            <td><strong>$29,500.00</strong></td>
                            <td><strong>$27,250.00</strong></td>
                            <td><strong>$5,800.00 Dr</strong></td>
                        </tr>
                    </tbody>
                </table>

                <table id="detailed-ledger-table" style="display: none;">
                    <thead>
                        <tr>
                            <th>Transaction Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Closing Balance (Dr/Cr)</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                
                <div class="pagination" id="pagination" style="display: none;">
                    <button class="btn btn-secondary" id="prev-btn" disabled>Previous</button>
                    <span id="page-info">Page 1 of 1</span>
                    <button class="btn btn-secondary" id="next-btn" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Ledger Modal -->
    <div class="modal" id="share-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Share Ledger</h3>
                <button class="close-btn" id="close-share-modal">&times;</button>
            </div>
            <div class="form-group">
                <label class="form-label" for="shareable-url">Shareable Ledger URL</label>
                <input type="text" class="form-control" id="shareable-url" value="https://fuelingsys.erp/ledger/SUP001" readonly>
            </div>
            <div class="form-group">
                <label class="form-label" for="supplier-username">Supplier's Username</label>
                <input type="text" class="form-control" id="supplier-username" value="SUP001" readonly>
            </div>
            <div class="form-group">
                <label class="form-label" for="supplier-password">Supplier's Password</label>
                <div class="password-field">
                    <input type="password" class="form-control" id="supplier-password" value="••••••••">
                    <button class="password-toggle" id="toggle-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="copy-url-btn">
                    <i class="fas fa-copy"></i> Copy URL
                </button>
                <button class="btn btn-secondary" id="close-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <script>
        window.currencySymbol = '<?php echo $currency_symbol; ?>';
        window.allSymbols = <?php echo json_encode(array_map(fn($s) => ['symbol' => $s['symbol'], 'name' => $s['name']], $all_symbols)); ?>;
    </script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="../../../assets/js/financial_reports/supplier_ledger/supplier-ledger.js"></script>
</body>
</html>