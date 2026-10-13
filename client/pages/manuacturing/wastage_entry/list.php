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
    <title>Wastage Entries</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/wastage_entry/list.css">
</head>
<body>
    <div class="main-content">
        <div class="list-container">
            <div id="successMessage" style="display:none;" class="success-message">
                <i class="las la-check-circle"></i>
                <span id="successText">Wastage entry saved successfully</span>
            </div>

            <div class="card">
                <div class="list-header">
                    <h2>Wastage Entries</h2>
                    <button class="btn btn-primary" onclick="window.location.href='index.php'">
                        <i class="las la-plus"></i> New Wastage Entry
                    </button>
                </div>

                <div class="filter-bar">
                    <div class="search-wrapper">
                        <i class="las la-search"></i>
                        <input type="search" placeholder="Search by wastage no or order no..." id="searchInput">
                    </div>
                    <div class="filter-group">
                        <select class="filter-select" id="typeFilter">
                            <option value="all">All types</option>
                            <option value="quantity">Quantity</option>
                            <option value="percentage">Percentage</option>
                        </select>
                        <button class="btn btn-secondary" id="resetFiltersBtn">
                            <i class="las la-redo-alt"></i> Reset
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Wastage No</th>
                                <th>Production Order</th>
                                <th>Product</th>
                                <th>Wastage Date</th>
                                <th>Type</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="7" style="text-align:center; padding:32px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p style="color:#2F3B4C; margin-bottom:8px;">Are you sure you want to delete <strong id="deleteWastageNo"></strong>?</p>
                <p style="color:#6B7280; font-size:13px;">This will also reverse the associated stock movements.</p>
                <input type="hidden" id="deleteId">
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button class="btn btn-danger" onclick="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="../../../assets/js/manufacturing/wastage_entry/list.js"></script>
</body>
</html>
