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
    <title>WIP List</title>
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
                    <h2>Work In Progress List</h2>
                    <a href="index.php" class="btn btn-primary">
                        <i class="las la-plus"></i> New WIP Issue
                    </a>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>WIP #</th>
                                <th>Production Order</th>
                                <th>Branch</th>
                                <th>Issue Date</th>
                                <th>Total Items</th>
                                <th>Total Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="wipListTable">
                            <tr><td colspan="7" style="text-align:center; padding:32px; color:#6B7280;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/wip_management/list.js"></script>
</body>
</html>
