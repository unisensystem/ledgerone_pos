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
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Currency List</title>
    <link rel="stylesheet" href="../../../assets/css/system_setup/currency_setup/currency-list.css">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Currency Management</h1>
        </div>

        <div class="page-actions">
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search currencies...">
                <span class="search-icon">🔍</span>
            </div>
            <button class="btn btn-primary" id="addCurrencyBtn">
                <span>+</span>
                <span>Add Currency</span>
            </button>
        </div>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Currency Code</th>
                            <th>Currency Name</th>
                            <th>Symbol</th>
                            <th>Exchange Rate</th>
                            <th>Status</th>
                            <th>Base Currency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="currencyTableBody">
                        <!-- Currency data will be populated here -->
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">
                    Showing 1-8 of 8 currencies
                </div>
                <div class="pagination-controls">
                    <button class="btn-pagination" id="prevPage">‹</button>
                    <button class="btn-pagination active">1</button>
                    <button class="btn-pagination" id="nextPage">›</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Currency Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Currency</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <div class="form-group">
                        <label>Currency Name</label>
                        <select id="editCurrencyName">
                            <option value="">Select Currency</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" id="editCurrencySymbol" readonly>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="editStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" id="editCancelBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editSaveBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Currency</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete currency <strong id="deleteCurrencyName"></strong>?</p>
                <p>This action cannot be undone.</p>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="deleteCancelBtn">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/currency_setup/currency-list.js"></script>
</body>
</html>