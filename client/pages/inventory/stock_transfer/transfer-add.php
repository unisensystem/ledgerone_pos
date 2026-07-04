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

// Get base currency symbol
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer Entry | LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_transfer/transfer-add.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <!-- Main Section -->
            <div class="form-section">
                <h2 class="section-title">Stock Transfer Details</h2>
                <div class="form-grid">
                    <!-- Transfer Code -->
                    <div class="form-group">
                        <label class="form-label required" for="transfer-code">Transfer Code</label>
                        <input 
                            type="text" 
                            id="transfer-code" 
                            class="form-input readonly"
                            placeholder="Auto-generated"
                            readonly
                        >
                        <div class="helper-text">Auto-generated transfer code</div>
                    </div>

                    <!-- Date -->
                    <div class="form-group">
                        <label class="form-label required" for="date">Date</label>
                        <input 
                            type="date" 
                            id="date" 
                            class="form-input" 
                            value=""
                            required
                        >
                    </div>

                    <!-- Company -->
                    <div class="form-group">
                        <label class="form-label required" for="company">Company</label>
                        <select id="company" class="form-input" required>
                            <option value="">Select Company</option>
                        </select>
                    </div>

                    <!-- From Branch -->
                    <div class="form-group">
                        <label class="form-label required" for="from-branch">From Branch</label>
                        <div class="searchable-dropdown">
                            <div class="dropdown-input-container">
                                <input 
                                    type="text" 
                                    id="from-branch" 
                                    class="form-input" 
                                    placeholder="Search or select branch"
                                    required
                                >
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="dropdown-options" id="from-branch-options">
                                <!-- Options will be populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- To Branch -->
                    <div class="form-group">
                        <label class="form-label required" for="to-branch">To Branch</label>
                        <div class="searchable-dropdown">
                            <div class="dropdown-input-container">
                                <input 
                                    type="text" 
                                    id="to-branch" 
                                    class="form-input" 
                                    placeholder="Search or select branch"
                                    required
                                >
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="dropdown-options" id="to-branch-options">
                                <!-- Options will be populated by JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Container -->
                    <div class="form-group">
                        <label class="form-label" for="container">Container</label>
                        <select id="container" class="form-input">
                            <option value="">Select Container</option>
                        </select>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group" style="grid-column: span 3;">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea 
                            id="remarks" 
                            class="form-textarea" 
                            placeholder="Add any additional notes about this transfer"
                        ></textarea>
                    </div>
                </div>
            </div>

            <!-- Items Table Section -->
            <div class="form-section">
                <h2 class="section-title">Transfer Items</h2>
                <div class="table-container">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th width="3%">S#</th>
                                <th width="18%">Product Code</th>
                                <th width="12%">Engine No</th>
                                <th width="12%">Chassis No</th>
                                <th width="10%">Unit</th>
                                <th width="10%">Quantity</th>
                                <th width="12%">Rate (<?php echo $currency_symbol; ?>)</th>
                                <th width="12%">Stock Value</th>
                                <th width="11%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="items-table-body">
                            <!-- Rows will be added dynamically by JS -->
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 16px;">
                    <button id="add-item-btn" class="btn btn-secondary">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <div class="actions-left">
                    <h2>Stock Transfer Entry</h2>
                </div>
                <div class="actions-right">
                    <button id="reset-btn" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                    <button id="post-transfer-btn" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Transfer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/inventory/stock_transfer/transfer-add.js"></script>
</body>
</html>