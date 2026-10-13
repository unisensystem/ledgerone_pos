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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Stock Repack</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/products/product-add.css?v=<?= filemtime(__DIR__ . '/../../../assets/css/inventory/products/product-add.css') ?>">
    <link rel="stylesheet" href="../../../assets/css/inventory/products/stock-repack.css?v=<?= filemtime(__DIR__ . '/../../../assets/css/inventory/products/stock-repack.css') ?>">
</head>
<body>
    <div class="container">
        <h1>Stock Repack</h1>
        <p class="form-description">Add more bags to a child product (e.g. ATTA 5KG) from its parent's loose stock (e.g. ATTA in Kg)</p>

        <form id="repackForm" class="card">
            <div class="form-section">
                <h2 class="section-title">Select Product</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Product</label>
                        <div class="custom-dropdown">
                            <input type="text" id="productSearch" placeholder="Search product (e.g. ATTA 5KG)..." autocomplete="off">
                            <input type="hidden" id="productId" name="productId">
                            <div class="dropdown-list" id="productDropdown" style="display: none;"></div>
                        </div>
                        <div class="helper-text">Parent product is picked up automatically</div>
                    </div>
                </div>

                <div class="repack-info" id="repackInfo" style="display: none;">
                    <div class="repack-info-item">
                        <label>Parent Product</label>
                        <div class="repack-info-value" id="infoParent">-</div>
                    </div>
                    <div class="repack-info-item">
                        <label>Pack Size</label>
                        <div class="repack-info-value" id="infoPackSize">-</div>
                    </div>
                    <div class="repack-info-item">
                        <label>Parent Stock Available</label>
                        <div class="repack-info-value" id="infoParentKg">-</div>
                    </div>
                    <div class="repack-info-item">
                        <label>Current Bags of This Product</label>
                        <div class="repack-info-value" id="infoChildBags">-</div>
                    </div>
                    <div class="repack-info-item">
                        <label>Max Bags You Can Add</label>
                        <div class="repack-info-value" id="infoMaxBags">-</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">Add Bags</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label class="required" id="qtyLabel">Bags to Add</label>
                        <input type="number" id="qtyBags" name="qtyBags" step="1" min="1" disabled>
                        <div class="helper-text" id="qtyHelper">Select a product first</div>
                    </div>
                </div>

                <div class="repack-preview" id="repackPreview" style="display: none;"></div>
            </div>

            <div class="form-section">
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" id="resetBtn">Reset</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Add Stock</button>
                </div>
            </div>
        </form>
    </div>

    <script src="../../../assets/js/inventory/products/stock-repack.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/inventory/products/stock-repack.js') ?>"></script>
</body>
</html>