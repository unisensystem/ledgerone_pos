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

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . '/ledgerone_erp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wastage Entry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/wastage_entry/styles.css">
</head>
<body>
    <div class="main-content">
        <div class="we-container">
            <div class="card">
                <div class="card-header">
                    <h2>Wastage Entry</h2>
                </div>

                <div class="form-section">
                    <div class="header-fields">
                        <div class="field-group">
                            <label class="form-label">Wastage No <span class="required">*</span></label>
                            <input type="text" class="form-control" id="wastageNo" readonly>
                            <div class="helper-text">Auto-generated</div>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Production Order <span class="required">*</span></label>
                            <input type="text" class="form-control" id="productionOrderInput" list="orderList" placeholder="Search production order..." required>
                            <datalist id="orderList"></datalist>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Wastage Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="wastageDate" required>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Wastage Type <span class="required">*</span></label>
                            <select class="form-control" id="wastageType">
                                <option value="quantity">Quantity</option>
                                <option value="percentage">Percentage (%)</option>
                            </select>
                        </div>

                        <div class="field-group" style="grid-column: span 4;">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" id="remarks" placeholder="Optional notes...">
                        </div>
                    </div>

                    <div style="margin-top: 16px;">
                        <button class="btn btn-secondary" id="loadMaterialsBtn">
                            <i class="las la-sync"></i> Load Materials
                        </button>
                    </div>
                </div>

                <!-- Finished Good Wastage -->
                <div id="fgSection" style="display:none;">
                    <div class="section-title">Finished Good Wastage</div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Finished Good</th>
                                    <th>Order Qty</th>
                                    <th>UOM</th>
                                    <th id="fgInputHeader">Wastage Qty</th>
                                    <th>Wastage Qty (Calculated)</th>
                                </tr>
                            </thead>
                            <tbody id="fgTable"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Raw Material Wastage -->
                <div id="materialsSection" style="display:none;">
                    <div class="section-title">Raw Material Wastage</div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Ordered Qty</th>
                                    <th>UOM</th>
                                    <th id="wastageInputHeader">Wastage Qty</th>
                                    <th>Wastage Qty (Calculated)</th>
                                </tr>
                            </thead>
                            <tbody id="materialsTable">
                                <tr>
                                    <td colspan="5" class="empty-state">Select a production order and click "Load Materials"</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Placeholder when nothing loaded yet -->
                <div id="emptyPlaceholder">
                    <div class="table-responsive">
                        <table>
                            <tbody>
                                <tr><td colspan="5" class="empty-state">Select a production order and click "Load Materials"</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button class="btn btn-primary" id="saveBtn">
                        <i class="las la-save"></i> Save Wastage Entry
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/wastage_entry/script_new.js"></script>
</body>
</html>
