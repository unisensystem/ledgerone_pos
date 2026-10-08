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
$initialSearch = $_GET['search'] ?? '';

// Cache-busting via filemtime: Cloudflare/browsers cache static .js/.css
// for hours (max-age=14400 here), while this PHP page itself isn't cached
// the same way — without this, a deployed JS/CSS fix can sit stale for up
// to 4 hours after the page's own HTML has already updated, producing a
// confusing half-updated page. filemtime() only changes the URL when the
// file's actual content changes, so normal long-term caching between
// deploys is preserved.
$cssVersion = @filemtime(__DIR__ . '/../../../assets/css/sale/sale_order/order-list.css') ?: time();
$jsVersion = @filemtime(__DIR__ . '/../../../assets/js/sale/sale_order/order-list.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Sale Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/sale/sale_order/order-list.css?v=<?= $cssVersion ?>">
</head>
<body class="light-theme">
    <div class="container">
        <div class="header">
            <h1 class="page-title">Sale Orders</h1>
        </div>

        <div class="card">
            <h2 class="card-title">Filters & Search</h2>
            <div class="filters">
                <div class="form-group">
                    <label for="dateFrom">Date From</label>
                    <input type="date" id="dateFrom">
                </div>
                <div class="form-group">
                    <label for="dateTo">Date To</label>
                    <input type="date" id="dateTo">
                </div>
                <div class="form-group">
                    <label for="companyFilter">Company</label>
                    <select id="companyFilter">
                        <option value="">All Companies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="customerFilter">Customer</label>
                    <select id="customerFilter">
                        <option value="">All Customers</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Partially Fulfilled">Partially Fulfilled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="orderStatusFilter">Order Status</label>
                    <select id="orderStatusFilter">
                        <option value="">All (Posted + Draft)</option>
                        <option value="Posted">Posted only</option>
                        <option value="Draft">Draft only (needs review)</option>
                    </select>
                </div>
            </div>

            <div class="actions">
                <div class="search-box">
                    <i class="fas fa-search"></i>
<input type="text" id="searchInput" placeholder="Search by invoice number, customer..." value="<?= htmlspecialchars($initialSearch) ?>">
                </div>
                <button class="btn btn-primary" id="finalizeSelectedBtn" style="display: none;">
                    <i class="fas fa-check-double"></i> Finalize Selected
                </button>
                <button class="btn btn-primary" id="newInvoiceBtn">
                    <i class="fas fa-plus"></i> New Order
                </button>
            </div>

            <div class="table-container">
                <table id="invoicesTable">
                    <thead>
                        <tr>
                            <th width="4%" id="selectAllHeaderCell" style="display: none; text-align: center;"><input type="checkbox" id="selectAllCheckbox"></th>
                            <th width="10%">Order No</th>
                            <th width="12%">Date</th>
                            <th width="18%">Customer</th>
                            <th width="8%">Items</th>
                            <th width="12%">Total Amount</th>
                            <th width="10%">Status</th>
                            <th width="26%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <div class="pagination-info" id="paginationInfo">
                    Showing 0 to 0 of 0 entries
                </div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Dynamic pagination buttons will be generated here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <h3 class="modal-title">Confirm Delete</h3>
            <p>Are you sure you want to delete this sale order? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Order Fulfillment Modal -->
    <div class="modal" id="fulfillmentModal">
        <div class="modal-content" style="width: 800px; max-width: 95%;">
            <h3 class="modal-title">Order Fulfillment Status</h3>
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default);">Product</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">Ordered</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">Served</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">Remaining</th>
                            <th style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="fulfillmentTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="closeFulfillmentBtn">Close</button>
            </div>
        </div>
    </div>

<script src="../../../assets/js/sale/sale_order/order-list.js?v=<?= $jsVersion ?>"></script>
<?php if ($initialSearch): ?>
<script>
    window.addEventListener('load', function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
        }
    });
</script>
<?php endif; ?>
</body>
</html>