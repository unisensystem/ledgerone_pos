<?php
require_once '../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!$_SESSION['user_id'] ?? null) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent List</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../assets/css/rent/rent.css">
</head>
<body>
    <div class="main-content">
        <div class="container">
            <div id="successMessage" style="display:none;" class="success-message">
                <i class="las la-check-circle"></i>
                <span>Operation successful!</span>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Rent List</h2>
                    <button class="btn btn-primary" onclick="window.location.href='rent-issue.php'"><i class="las la-plus"></i> Issue Rent</button>
                </div>

                <div class="filter-bar">
                    <input type="search" id="searchInput" class="form-control" placeholder="Search...">
                    <select id="statusFilter" class="form-control" onchange="filterData()">
                        <option value="all">All Status</option>
                        <option value="Issued">Issued</option>
                        <option value="Returned">Returned</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Rent No</th>
                                <th>Customer</th>
                                <th>Issue Date</th>
                                <th>Expected Return</th>
                                <th>Rent Amount</th>
                                <th>Late Charges</th>
                                <th>Damage Charges</th>
                                <th>Net Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="10" style="text-align:center; padding:32px;">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="returnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Return Rent</h3>
                <span class="close" onclick="closeReturnModal()">&times;</span>
            </div>
            <div class="modal-body" id="returnModalBody"></div>
        </div>
    </div>

    <script src="../../assets/js/rent/rent-list.js"></script>
</body>
</html>
