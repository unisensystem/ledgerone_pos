<?php
require_once '../../../../includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne · Customer list (light)</title>
    <!-- Inter font (optional, matches form) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/customer_supplier/customer_test/customer-list.css">
</head>
<body>
    <div class="list-card">
        <!-- header with title and "New customer" (links to form ideally) -->
        <div class="list-header">
            <h2>Customer list</h2>
            <button class="btn-primary" id="newCustomerBtn">+ New customer</button>
        </div>

        <!-- search / filter (simple client-side filter) -->
        <div class="filter-bar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by name, code, email...">
                <span>⌕</span>
            </div>
            <!-- optional filter placeholder -->
        </div>

        <!-- table -->
        <div class="table-wrapper">
            <table id="customerTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Customer name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th></th> <!-- actions -->
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- data populated via js -->
                </tbody>
            </table>
        </div>

        <!-- simple pagination (static for demo) -->
        <div class="pagination">
            <div class="pagination-btn">←</div>
            <div class="pagination-btn active">1</div>
            <div class="pagination-btn">2</div>
            <div class="pagination-btn">3</div>
            <div class="pagination-btn">4</div>
            <div class="pagination-btn">→</div>
        </div>
    </div>

    <script src="../../../assets/js/customer_supplier/customer_test/customer-list.js"></script>

    <!-- Design notes:
        - Table fully respects light theme: header #F7F9FC, border #E1E6EE, row height 44px,
          alternating row #FAFBFD, hover #F0F6FF.
        - Buttons reuse primary and ghost styles from DS (ghost edit/delete).
        - Card uses same padding/shadow as form.
        - Search input matches form input styles.
        - Pagination elements are minimal but fit corporate style.
    -->
</body>
</html>