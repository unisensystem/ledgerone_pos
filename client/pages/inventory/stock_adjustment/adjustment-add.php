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

$adjustment_id = $_GET['id'] ?? null;
$is_edit = !empty($adjustment_id);

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

$adjustment_data = null;
if ($is_edit) {
    $stmt = $pdo->prepare("
        SELECT sa.adjustment_code, sa.branch_id, b.branch_name, sa.adjustment_type,
               sa.main_reason, sa.primary_reason, sa.secondary_reason, sa.remarks, sa.company_id
        FROM stock_adjustment sa
        JOIN branches b ON sa.branch_id = b.id
        WHERE sa.tenant_id = ? AND sa.id = ?
    ");
    $stmt->execute([$_SESSION['tenant_id'], $adjustment_id]);
    $adjustment_data = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT p.code as product_code, sai.product_id, sai.unit_id, sai.qty, sai.rate, sai.stock_value
        FROM stock_adjustment_items sai
        JOIN products p ON sai.product_id = p.id
        WHERE sai.tenant_id = ? AND sai.adjustment_id = ?
    ");
    $stmt->execute([$_SESSION['tenant_id'], $adjustment_id]);
    $adjustment_items = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Stock Adjustment Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/stock_adjustment/adjustment-add.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="page-title">Stock Adjustment Entry</h1>
            <div class="header-actions">
                <button class="btn btn-ghost" id="helpBtn">
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
        </div>
        
        <div class="form-container">
            <!-- Main Section -->
            <div class="form-section">
                <h2 class="section-title">Adjustment Details</h2>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="field-label required">Adjustment Code</label>
                        <input type="text" class="field-input" id="adjustmentCode" placeholder="Auto-generated" readonly>
                    </div>
                    
                    <div class="form-field">
                        <label class="field-label required">Branch</label>
                        <div class="select-with-search">
                            <input type="text" class="field-input" id="branchInput" placeholder="Search or select branch">
                            <div class="dropdown-options" id="branchOptions">
                                <!-- Options will be populated by JS -->
                            </div>
                        </div>
                        <div class="error-message">Please select a branch</div>
                    </div>
                    
                    <div class="form-field">
                        <label class="field-label required">Company</label>
                        <select class="field-select" id="company" required>
                            <option value="">Select Company</option>
                        </select>
                        <div class="error-message">Please select a company</div>
                    </div>
                    
                    <div class="form-field">
                        <label class="field-label required">Adjustment Type</label>
                        <select class="field-select" id="adjustmentType">
                            <option value="">Select type</option>
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </select>
                        <div class="error-message">Please select an adjustment type</div>
                    </div>
                </div>
                
                <div class="form-field">
                    <label class="field-label required">Main Reason</label>
                    <select class="field-select" id="mainReason">
                        <option value="">Select main reason</option>
                        <option value="physical_loss">Physical Loss/Shrinkage</option>
                        <option value="damage">Damage/Spoilage</option>
                        <option value="obsolescence">Obsolescence/Expiry</option>
                        <option value="accounting">Accounting/Control Adjustments</option>
                        <option value="process">Process-Related Adjustments</option>
                        <option value="natural">Natural/Extraordinary Loss</option>
                    </select>
                    <div class="error-message">Please select a main reason</div>
                </div>
                
                <div class="form-field">
                    <label class="field-label required">Primary Reason</label>
                    <select class="field-select" id="primaryReason" disabled>
                        <option value="">Select primary reason</option>
                    </select>
                    <div class="error-message">Please select a primary reason</div>
                </div>
                
                <div class="form-field">
                    <label class="field-label required">Secondary Reason</label>
                    <select class="field-select" id="secondaryReason" disabled>
                        <option value="">Select secondary reason</option>
                    </select>
                    <div class="error-message">Please select a secondary reason</div>
                </div>
                
                <div class="form-field">
                    <label class="field-label">Remarks</label>
                    <textarea class="field-textarea" id="remarks" placeholder="Enter additional remarks (optional)"></textarea>
                </div>
            </div>
            
            <!-- Items Section -->
            <div class="form-section">
                <h2 class="section-title">Adjustment Items</h2>
                
                <div class="items-table-container">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th width="5%">S#</th>
                                <th width="25%">Product Code</th>
                                <th width="12%">Unit</th>
                                <th width="13%">Quantity</th>
                                <th width="13%">Rate (<?php echo $currency_symbol; ?>)</th>
                                <th width="12%">Gross (<?php echo $currency_symbol; ?>)</th>
                                <th width="20%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Table rows will be generated by JS -->
                        </tbody>
                    </table>
                </div>
                
                <div class="empty-state" id="emptyTableState">
                    <p>No items added yet. Click the "+" button in the table to add an item.</p>
                </div>
                
                <div class="form-actions">
                    <button class="btn btn-ghost" id="addItemBtn">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button class="btn btn-secondary" id="resetBtn">
                <i class="fas fa-redo"></i> Reset Form
            </button>
            <button class="btn btn-primary" id="postBtn">
                <i class="fas fa-check-circle"></i> Post Adjustment
            </button>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <div class="toast-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="toast-message">Adjustment posted successfully!</div>
    </div>

    <script src="../../../assets/js/inventory/stock_adjustment/adjustment-add.js"></script>
    <script>
        window.adjustmentData = <?php echo $is_edit ? json_encode([
            'id' => $adjustment_id,
            'adjustment_code' => $adjustment_data['adjustment_code'],
            'branch_id' => $adjustment_data['branch_id'],
            'branch_name' => $adjustment_data['branch_name'],
            'adjustment_type' => $adjustment_data['adjustment_type'],
            'main_reason' => $adjustment_data['main_reason'],
            'primary_reason' => $adjustment_data['primary_reason'],
            'secondary_reason' => $adjustment_data['secondary_reason'],
            'remarks' => $adjustment_data['remarks'],
            'company_id' => $adjustment_data['company_id'],
            'items' => $adjustment_items
        ]) : 'null'; ?>;
    </script>
</body>
</html>