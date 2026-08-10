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
$order_id = $_GET['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Production Order</title>
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
                    <h2>Edit Production Order</h2>
                </div>

                <div class="form-section">
                    <div class="header-fields">
                        <div class="field-group">
                            <label class="form-label">Order No</label>
                            <input type="text" class="form-control" id="orderNo" readonly>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Product <span style="color:#E34F4F;">*</span></label>
                            <input type="text" class="form-control" id="productId" list="productList" placeholder="Search product..." required>
                            <datalist id="productList"></datalist>
                        </div>

                        <div class="field-group">
                            <label class="form-label">BOM <span style="color:#E34F4F;">*</span></label>
                            <input type="text" class="form-control" id="bomId" list="bomList" placeholder="Search BOM..." required>
                            <datalist id="bomList"></datalist>
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
                            <i class="las la-sync"></i> Recalculate Materials
                        </button>
                    </div>
                </div>

                <div class="raw-title">Material Requirements</div>

                <div class="table-responsive">
                    <table id="materialsTableEl">
                        <thead id="materialsTableHead">
                            <tr>
                                <th>Material</th>
                            </tr>
                        </thead>
                        <tbody id="materialsTable">
                            <tr><td colspan="10" style="text-align:center; padding:32px; color:#6B7280;">Loading materials...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="window.location.href='list.php'">Cancel</button>
                    <button class="btn btn-primary" id="updateBtn">Update Order</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
        const ORDER_ID = <?php echo $order_id; ?>;
    </script>
    <script src="../../../assets/js/manufacturing/production_order/edit.js"></script>
</body>
</html>
