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
    <title>Inward Gatepass Entry - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/inventory/inward_gatepass/gatepass-add.css">
</head>

<body>
    <div class="container">
        <div class="page-header">
            <div class="title-section">
                <h1>Inward Gatepass Entry</h1>
                <p>Create new inward gatepass for received goods</p>
            </div>
        </div>

        <div class="form-container">
            <!-- Main Section -->
            <div class="form-section">
                <h2 class="section-title">Main Details</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="igp-code" class="form-label">IGP Code</label>
                        <input type="text" id="igp-code" class="form-input" value="Auto-generated" readonly>
                        <div class="helper-text">Auto-generated gatepass code</div>
                    </div>

                    <div class="form-group">
                        <label for="date" class="form-label required">Date</label>
                        <input type="date" id="date" class="form-input" value="2023-10-15" required>
                        <div class="helper-text">Date of inward gatepass</div>
                    </div>

                    <div class="form-group">
                        <label for="company" class="form-label required">Company</label>
                        <div class="searchable-dropdown" id="company-dropdown">
                            <div class="dropdown-toggle">
                                <input type="text" id="company" class="form-input" placeholder="Select company"
                                    readonly required>
                                <span class="dropdown-arrow">▼</span>
                            </div>
                            <div class="dropdown-menu" id="company-menu">
                                <div class="dropdown-search">
                                    <input type="text" placeholder="Search companies..." id="company-search">
                                </div>
                                <div class="dropdown-items" id="company-options">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="helper-text">Select company</div>
                    </div>

                    <div class="form-group">
                        <label for="supplier" class="form-label required">Supplier</label>
                        <div class="searchable-dropdown" id="supplier-dropdown">
                            <div class="dropdown-toggle">
                                <input type="text" id="supplier" class="form-input" placeholder="Select supplier"
                                    readonly required>
                                <span class="dropdown-arrow">▼</span>
                            </div>
                            <div class="dropdown-menu" id="supplier-menu">
                                <div class="dropdown-search">
                                    <input type="text" placeholder="Search suppliers..." id="supplier-search">
                                </div>
                                <div class="dropdown-items" id="supplier-options">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="helper-text">Select supplier from list</div>
                    </div>

                    <div class="form-group">
                        <label for="branch" class="form-label required">Branch</label>
                        <div class="searchable-dropdown" id="branch-dropdown">
                            <div class="dropdown-toggle">
                                <input type="text" id="branch" class="form-input" placeholder="Select branch" readonly
                                    required>
                                <span class="dropdown-arrow">▼</span>
                            </div>
                            <div class="dropdown-menu" id="branch-menu">
                                <div class="dropdown-search">
                                    <input type="text" placeholder="Search branches..." id="branch-search">
                                </div>
                                <div class="dropdown-items" id="branch-options">
                                    <!-- Options will be populated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="helper-text">Select receiving branch</div>
                    </div>

                    <div class="form-group">
                        <label for="container" class="form-label">Container</label>
                        <select id="container" class="form-input">
                            <option value="">Select Container</option>
                        </select>
                        <div class="helper-text">Optional: Select container if applicable</div>
                    </div>
                </div>
            </div>

            <!-- Items Table Section -->
            <div class="form-section">
                <h2 class="section-title">Items Details</h2>
                <div class="table-container">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th width="50">S#</th>
                                <th>Product</th>
                                <th width="150">Unit</th>
                                <th width="120">Quantity</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="items-table-body">
                            <!-- Rows will be added dynamically -->
                        </tbody>
                    </table>
                </div>

                <div class="add-row-btn">
                    <button type="button" class="btn btn-secondary" id="add-row-btn">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="action-buttons">
                <button type="button" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submit-form">
                    <i class="fas fa-check"></i> Submit Gatepass
                </button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/inventory/inward_gatepass/gatepass-add.js"></script>
</body>

</html>