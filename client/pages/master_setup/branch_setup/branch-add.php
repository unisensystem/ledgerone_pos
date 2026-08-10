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
    <title>Branch Entry Form - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/master_setup/branch_setup/branch-add.css">
</head>

<body>
    <div class="container">
        <header class="page-header">
            <h1 class="page-title">Branch Entry Form</h1>
            <p class="page-description">Add a new branch to your LedgerOne ERP system. Fill in the required details
                below.</p>
        </header>

        <form id="branchForm" class="card">
            <div class="card-header">
                <h2 class="card-title">Branch Information</h2>
            </div>

            <div class="form-grid">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Basic Information</h3>

                    <div class="form-group">
                        <label for="branchCode">Branch Code</label>
                        <div class="input-wrapper">
                            <input type="text" id="branchCode" name="branch_code" placeholder="Automatically generated"
                                readonly>
                            <div class="error-message" id="branchCodeError">Please enter a branch code</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="branchName" class="required">Branch Name</label>
                        <div class="input-wrapper">
                            <input type="text" id="branchName" name="branch_name" required>
                            <div class="error-message" id="branchNameError">Please enter a branch name</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="branchType" class="required">Branch Type</label>
                        <div class="input-wrapper">
                            <select id="branchType" name="branch_type" required>
                                <option value="">Select Branch Type</option>
                                <option value="head_office">Head Office</option>
                                <option value="regional_office">Regional Office</option>
                                <option value="branch_office">Branch Office</option>
                                <option value="warehouse">Warehouse</option>
                                <option value="distribution_center">Distribution Center</option>
                                <option value="retail_store">Retail Store</option>
                                <option value="service_center">Service Center</option>
                                <option value="manufacturing_unit">Manufacturing Unit</option>
                            </select>
                            <div class="error-message" id="branchTypeError">Please select a branch type</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="company" class="required">Company</label>
                        <div class="input-wrapper">
                            <select id="company" name="company_id" required>
                                <option value="">Select Company</option>
                            </select>
                            <div class="error-message" id="companyError">Please select a company</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="parentBranch">Parent Branch</label>
                        <div class="input-wrapper searchable-select">
                            <input type="text" id="parentBranch" name="parent_branch_search" placeholder="Search parent branch..." autocomplete="off">
                            <input type="hidden" id="parentBranchId" name="parent_branch_id">
                            <div class="dropdown-list" id="parentBranchDropdown"></div>
                        </div>
                    </div>
                </div>

                <!-- Location Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Location Information</h3>

                    <div class="form-group full-width">
                        <label for="address">Address</label>
                        <div class="input-wrapper">
                            <textarea id="address" name="address"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="country">Country</label>
                        <div class="input-wrapper">
                            <input type="text" id="country" name="country">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="state">State/Province</label>
                        <div class="input-wrapper">
                            <input type="text" id="state" name="state">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="city">City</label>
                        <div class="input-wrapper">
                            <input type="text" id="city" name="city">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="zipcode">ZIP/Postal Code</label>
                        <div class="input-wrapper">
                            <input type="text" id="zipcode" name="zipcode">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h3 class="section-title">Contact Information</h3>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <div class="input-wrapper">
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="manager">Manager</label>
                        <div class="input-wrapper searchable-select">
                            <input type="text" id="manager" name="manager_search" placeholder="Search manager..." autocomplete="off">
                            <input type="hidden" id="managerId" name="manager_id">
                            <div class="dropdown-list" id="managerDropdown"></div>
                        </div>
                    </div>
                </div>

                <!-- Branch Settings Section -->
                <div class="form-section">
                    <h3 class="section-title">Branch Settings</h3>

                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="isActive" name="is_active" checked>
                            <label for="isActive">Active Branch</label>
                        </div>

                        <div class="checkbox-item">
                            <input type="checkbox" id="allowsSales" name="allows_sales" checked>
                            <label for="allowsSales">Allows Sales</label>
                        </div>

                        <div class="checkbox-item">
                            <input type="checkbox" id="allowsInventory" name="allows_inventory" checked>
                            <label for="allowsInventory">Allows Inventory</label>
                        </div>

                        <div class="checkbox-item">
                            <input type="checkbox" id="isDefault" name="is_default">
                            <label for="isDefault">Default Branch</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="resetBtn">
                    <i class="fas fa-undo"></i>
                    Reset Form
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus icon"></i> Create Branch
                </button>
            </div>
        </form>
    </div>

    <script src="../../../assets/js/master_setup/branch_setup/branch-add.js"></script>
</body>

</html>