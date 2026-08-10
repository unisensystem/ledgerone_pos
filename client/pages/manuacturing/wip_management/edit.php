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

$wip_id = $_GET['id'] ?? null;
if (!$wip_id) {
    header('Location: list.php');
    exit();
}

$view_mode = isset($_GET['mode']) && $_GET['mode'] === 'view';

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
    <title>Edit WIP</title>
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
                    <h2><?php echo $view_mode ? 'View WIP Issue' : 'Edit WIP Issue'; ?></h2>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="las la-arrow-left"></i> Back to List
                    </a>
                </div>

                <div class="form-section">
                    <div class="header-fields">
                        <div class="field-group">
                            <label class="form-label">WIP #</label>
                            <input type="text" class="form-control" id="wipNumber" readonly>
                        </div>

                        <div class="field-group">
                            <label class="form-label">Production Order</label>
                            <input type="text" class="form-control" id="productionOrder" readonly>
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
                            <label class="form-label">Issue Date</label>
                            <input type="date" class="form-control" id="issueDate" <?php echo $view_mode ? 'readonly' : ''; ?>>
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

                <div class="raw-title">Materials Issued</div>

                <div class="table-responsive">
                    <table>
                        <thead></thead>
                        <tbody id="materialsTable">
                            <tr><td colspan="20" style="text-align:center; padding:32px; color:#6B7280;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <a href="list.php" class="btn btn-secondary"><?php echo $view_mode ? 'Close' : 'Cancel'; ?></a>
                    <?php if (!$view_mode): ?>
                    <button class="btn btn-primary" id="updateBtn">Update WIP</button>
                    <?php endif; ?>
                    <a href="print.php?id=<?php echo $wip_id; ?>" target="_blank" class="btn btn-primary">
                        <i class="las la-print"></i> Print
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
        const WIP_ID = <?php echo $wip_id; ?>;
        const VIEW_MODE = <?php echo $view_mode ? 'true' : 'false'; ?>;
    </script>
    <script src="../../../assets/js/manufacturing/wip_management/edit.js"></script>
</body>
</html>
