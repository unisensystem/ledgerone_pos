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
    <title>Production Order</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_order/styles.css">
</head>
<body>
    <div class="main-content">
        <div class="po-container">
            <div class="card">
                <div class="card-header">
                    <h2>Production Order</h2>
                </div>

                <div class="form-section">
                    <div class="header-fields">
                        <div class="field-group">
                            <label class="form-label">Order No <span style="color:#E34F4F;">*</span></label>
                            <input type="text" class="form-control" id="orderNo" readonly>
                            <div class="helper-text">Auto-generated</div>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Product <span style="color:#E34F4F;">*</span></label>
                            <input type="text" class="form-control" id="productId" list="productList" placeholder="Search product..." required>
                            <datalist id="productList">
                            </datalist>
                        </div>

                        <div class="field-group">
                            <label class="form-label">BOM <span style="color:#E34F4F;">*</span></label>
                            <input type="text" class="form-control" id="bomId" list="bomList" placeholder="Search BOM..." required>
                            <datalist id="bomList">
                            </datalist>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Branch <span style="color:#E34F4F;">*</span></label>
                            <select class="form-control" id="branchId" required>
                                <option value="">Select Branch</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Machine</label>
                            <select class="form-control" id="machineId">
                                <option value="">Select Machine</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Order Quantity <span style="color:#E34F4F;">*</span></label>
                            <input type="number" class="form-control" id="orderQty" step="0.01" required>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>

                        <div class="field-group">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                    </div>

                    <div style="margin-top: 16px;">
                        <button class="btn btn-secondary" id="loadMaterialsBtn">
                            <i class="las la-sync"></i> Load Materials
                        </button>
                    </div>
                </div>

                <div class="raw-title">Material Requirements</div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Required Qty</th>
                                <th>UOM</th>
                                <th>Available Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="materialsTable">
                            <tr><td colspan="5" style="text-align:center; padding:32px; color:#6B7280;">Click "Load Materials" to calculate requirements</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button class="btn btn-primary" id="saveBtn">Create Production Order</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/production_order/script_new.js"></script>
</body>
</html>
