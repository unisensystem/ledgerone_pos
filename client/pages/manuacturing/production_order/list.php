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
    <title>Production Orders</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_order/list.css">
</head>
<body>
    <div class="main-content">
        <div class="list-container">
            <div id="successMessage" style="display:none;" class="success-message">
                <i class="las la-check-circle"></i>
                <span>Success!</span>
            </div>
            <div class="card">
                <div class="list-header">
                    <h2>Production Orders</h2>
                    <button class="btn btn-primary" onclick="window.location.href='index.php'"><i class="las la-plus"></i> New Order</button>
                </div>

                <div class="filter-bar">
                    <div class="search-wrapper">
                        <i class="las la-search"></i>
                        <input type="search" placeholder="Search orders..." id="searchInput">
                    </div>
                    <div class="filter-group">
                        <select class="filter-select" id="statusFilter">
                            <option value="all">All status</option>
                            <option value="Planned">Planned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        <button class="btn btn-secondary" id="resetFiltersBtn"><i class="las la-redo-alt"></i> Reset</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Order No</th>
                                <th>Product</th>
                                <th>BOM</th>
                                <th>Branch</th>
                                <th>Order Qty</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="8" style="text-align:center; padding:32px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h3>Change Status</h3>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="statusOrderId">
                <div class="field-group">
                    <label class="form-label">New Status</label>
                    <select class="form-control" id="newStatus">
                        <option value="Planned">Planned</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="updateStatus()">Update</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/production_order/list.js"></script>
</body>
</html>
