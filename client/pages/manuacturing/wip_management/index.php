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
// Check if running on production or local
if (strpos($host, 'unisensystems.com') !== false) {
    $base_url = $protocol . '://' . $host;
} else {
    $base_url = $protocol . '://' . $host . '/ledgerone_erp';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WIP Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/wip_management/styles.css">
</head>
<body>
    <div class="main-content">
        <div class="wip-container">
            <div class="card">
                <div class="card-header">
                    <h2>Work In Progress (WIP) Management</h2>
                </div>

                <div class="form-section">
                    <div class="header-fields">
                        <div class="field-group">
                            <label class="form-label">WIP #</label>
                            <input type="text" class="form-control" id="wipNumber" readonly>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Production Order <span style="color:#E34F4F;">*</span></label>
                            <select class="form-control" id="productionOrderId" required>
                                <option value="">Select Production Order</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control" id="branchName" readonly>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Machine</label>
                            <input type="text" class="form-control" id="machineName" readonly>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Issue Date <span style="color:#E34F4F;">*</span></label>
                            <input type="date" class="form-control" id="issueDate" required>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Total Items</label>
                            <input type="text" class="form-control" id="totalItems" readonly>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Total Cost</label>
                            <input type="text" class="form-control" id="totalCost" readonly>
                        </div>
                    </div>
                </div>

                <div class="raw-title">
                    Materials Issued
                    <div class="autocomplete-toggle">
                        <label class="toggle-switch">
                            <input type="checkbox" id="autocompleteToggle">
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Auto-fill Required Qty</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead></thead>
                        <tbody id="materialsTable">
                            <tr><td colspan="20" style="text-align:center; padding:32px; color:#6B7280;">Select a production order to load materials</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button class="btn btn-primary" id="issueBtn">Issue Materials</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/wip_management/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
