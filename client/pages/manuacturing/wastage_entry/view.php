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

$wastage_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$wastage_id) {
    header('Location: list.php');
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
    <title>View Wastage Entry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/wastage_entry/view.css">
</head>
<body>
    <div class="main-content">
        <div class="view-container">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 id="wastageNoTitle">Loading...</h2>
                        <div id="wastageTypeBadge"></div>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="window.location.href='list.php'">
                            <i class="las la-arrow-left"></i> Back to List
                        </button>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="section-title">Entry Details</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Wastage No</div>
                            <div class="detail-value" id="dWastageNo">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Production Order</div>
                            <div class="detail-value" id="dOrderNo">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Product</div>
                            <div class="detail-value" id="dProductName">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Branch</div>
                            <div class="detail-value" id="dBranchName">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Wastage Date</div>
                            <div class="detail-value" id="dWastageDate">—</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Wastage Type</div>
                            <div class="detail-value" id="dWastageType">—</div>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <div class="detail-label">Remarks</div>
                            <div class="detail-value" id="dRemarks">—</div>
                        </div>
                    </div>
                </div>

                <!-- Finished Good Wastage -->
                <div id="fgSection" style="display:none; margin-top:24px;">
                    <div class="section-title">Finished Good Wastage</div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Finished Good</th>
                                    <th>Order Qty</th>
                                    <th>UOM</th>
                                    <th id="fgInputHeader">Wastage Input</th>
                                    <th>Wastage Qty</th>
                                </tr>
                            </thead>
                            <tbody id="fgTable"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Raw Material Wastage -->
                <div id="materialsSection" style="display:none; margin-top:24px;">
                    <div class="section-title">Raw Material Wastage</div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Material</th>
                                    <th>Ordered Qty</th>
                                    <th>UOM</th>
                                    <th id="inputHeader">Wastage Input</th>
                                    <th>Wastage Qty</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTable">
                                <tr><td colspan="6" style="text-align:center; padding:32px;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
        const WASTAGE_ID = <?php echo $wastage_id; ?>;
    </script>
    <script src="../../../assets/js/manufacturing/wastage_entry/view.js"></script>
</body>
</html>
