<?php
require_once '../../../../includes/dashboard.php';
require_once '../../../../includes/encryption.php';
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
    <title>Customer Ledger Report | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/customer_ledger/customer-ledger.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Customer Ledger Report</h1>
            <div class="ledger-type">
                <div class="ledger-type-btn active" id="summary-ledger-btn">Summary Ledger</div>
                <div class="ledger-type-btn" id="detailed-ledger-btn">Detailed Ledger</div>
            </div>
        </div>

        <div class="card" style="background: #f0f9ff; border-left: 4px solid #1f7bff; margin-bottom: 16px;">
            <p style="margin: 0; font-size: 14px; color: #1f7bff;"><strong>Tip:</strong> Press Space bar in Customer Name field to see all customers, or type to search for a specific customer</p>
        </div>

        <div class="card">
            <h2 class="card-title">Filters</h2>
            <div class="filters">
                <div class="form-group">
                    <label class="form-label" for="company">Company</label>
                    <select class="form-control" id="company">
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
                    <label class="form-label" for="customer-code">Customer Code</label>
                    <input type="text" class="form-control" id="customer-search" placeholder="Search customers..." autocomplete="off">
                    <select class="form-control" id="customer-code" style="display: none;">
                        <option value="">Select Customer</option>
                    </select>
                    <div id="customer-dropdown" class="customer-dropdown"></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="distribution">Distribution</label>
                    <select class="form-control" id="distribution">
                        <option value="">All Distributions</option>
                    </select>
                </div>

                <!-- City / Zone / Area / Salesman filters (Summary Ledger only) — City -> Zone
                     -> Area cascade, plus an independent Salesman (employee) filter. All are
                     optional and narrow which customers appear in the Summary Ledger list. -->
                <div class="form-group">
                    <label class="form-label" for="city-filter">City</label>
                    <select class="form-control" id="city-filter">
                        <option value="">All Cities</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="zone-filter">Zone</label>
                    <select class="form-control" id="zone-filter">
                        <option value="">All Zones</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="area-filter">Area</label>
                    <select class="form-control" id="area-filter">
                        <option value="">All Areas</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="salesman-filter">Salesman</label>
                    <select class="form-control" id="salesman-filter">
                        <option value="">All Salesmen</option>
                    </select>
                </div>

                <div class="form-group" id="sub-account-group" style="display: none;">
                    <label class="form-label" for="sub-account">Sub Account</label>
                    <select class="form-control" id="sub-account">
                        <option value="">All Sub Accounts</option>
                    </select>
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

                <!-- Sort By field (Summary Ledger only) — works together with Sort Order
                     below to sort by an actual column (e.g. Closing Balance highest to lowest
                     or lowest to highest), replacing the old Min/Max amount range filter. -->
                <div class="form-group" id="sort-field-group">
                    <label class="form-label" for="sort-field">Sort By</label>
                    <select class="form-control" id="sort-field">
                        <option value="closing_balance" selected>Closing Balance (Dr/Cr)</option>
                        <option value="opening_balance">Opening Balance</option>
                        <option value="total_debit">Debit (Dr)</option>
                        <option value="total_credit">Credit (Cr)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sort-order">Sort Order</label>
                    <select class="form-control" id="sort-order">
                        <option value="asc" selected>Less to Greater</option>
                        <option value="desc">Greater to Less</option>
                    </select>
                </div>
                <label class="form-group" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" id="round-off-toggle">
                    <span class="form-label" style="margin:0;">Round Off</span>
                </label>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="load-ledger-btn">
                    <i class="fas fa-sync-alt"></i> Load Ledger
                </button>
                <button class="btn btn-secondary" id="reset-filters-btn">
                    <i class="fas fa-undo"></i> Reset Filters
                </button>
                <button class="btn btn-ghost" id="share-ledger-btn">
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
            <p style="margin: 0; font-size: 14px; color: #1f7bff;"><strong>Help:</strong> Dr (Debit) = Amount customer owes you | Cr (Credit) = Amount you owe to customer</p>
        </div>

        <div class="card">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <h2 class="card-title" id="ledger-title" style="margin: 0;">Summary Ledger</h2>
                <button class="btn btn-secondary" id="toggle-all-rows-btn" style="display: none;">
                    <i class="fas fa-expand-alt"></i> Expand All
                </button>
            </div>
            <div class="table-container">
                <table id="summary-ledger-table">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Opening Balance</th>
                            <th>Debit (Dr)</th>
                            <th>Credit (Cr)</th>
                            <th>Closing Balance (Dr/Cr)</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>

                                <table id="detailed-ledger-table" style="display: none;">
                    <thead>
                        <tr>
                            <th>Transaction Date</th>
                            <th>Received Date</th>
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
                <input type="text" class="form-control" id="shareable-url" value="https://fuelingsys.erp/ledger/CUS001" readonly>
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
        
        function encryptCustomerCode(customerId) {
            const key = 'LedgerOne2024SecretKey';
            const keyPadded = key.padEnd(32, '0');
            const iv = CryptoJS.SHA256(customerId.toString() + key).toString().substring(0, 32);
            const encrypted = CryptoJS.AES.encrypt(customerId.toString(), CryptoJS.enc.Utf8.parse(keyPadded), {
                iv: CryptoJS.enc.Hex.parse(iv),
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7
            });
            return encodeURIComponent(encrypted.toString());
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="../../../assets/js/financial_reports/customer_ledger/customer-ledger.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/financial_reports/customer_ledger/customer-ledger.js'); ?>"></script>
</body>
</html>