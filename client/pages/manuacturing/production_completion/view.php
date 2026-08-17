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
$id = $_GET['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Completion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_completion/view.css">
</head>
<body>
    <div class="main-content">
        <div class="view-container">
            <div class="card">
                <div class="card-header">
                    <h2>Production Completion Details</h2>
                    <div style="display:flex; gap:12px;">
                        <button class="btn btn-secondary" onclick="window.location.href='list.php'">
                            <i class="las la-arrow-left"></i> Back
                        </button>
                        <button class="btn btn-primary" onclick="window.open('print.php?id=<?php echo $id; ?>', '_blank')">
                            <i class="las la-print"></i> Print
                        </button>
                    </div>
                </div>

                <div id="completionDetails">
                    <div style="text-align:center; padding:32px;">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
        const COMPLETION_ID = <?php echo $id; ?>;
    </script>
    <script src="../../../assets/js/manufacturing/production_completion/view.js?v=<?php echo time(); ?>"></script>
</body>
</html>
