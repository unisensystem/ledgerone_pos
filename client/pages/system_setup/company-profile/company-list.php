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
    <title>LedgerOne ERP - Companies</title>
    <link rel="stylesheet" href="../../../assets/css/system_setup/company-profile/company-list.css">
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <div>
                    <h1 class="page-title">Companies</h1>
                    <p class="page-description">Manage and view all registered companies in the system</p>
                </div>
            </div>
            <button class="btn btn-primary" onclick="openCompanyForm()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Add Company
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value">0</div>
                <div class="stat-label">Total Companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">0</div>
                <div class="stat-label">Active Companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">0</div>
                <div class="stat-label">Inactive Companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">0</div>
                <div class="stat-label">New This Month</div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="actions-bar">
            <div class="search-box">
                <input type="text" placeholder="Search companies..." id="searchInput">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.3-4.3"/>
                </svg>
            </div>
            <div class="action-buttons">
                <button class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 3H2l10 12.5L22 3z"/>
                        <path d="M2 3l7 18 3-9 3 9 7-18"/>
                    </svg>
                    Filter
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <div class="filter-label">Status</div>
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="filter-group">
                <div class="filter-label">Sort By</div>
                <select class="filter-select" id="sortBy">
                    <option value="name">Company Name</option>
                    <option value="date">Registration Date</option>
                    <option value="status">Status</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Company Code</th>
                            <th>Company Name</th>
                            <th>Industry</th>
                            <th>Contact</th>
                            <th>Registration No.</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="companiesTableBody">
                        <!-- Companies will be populated here by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info">Showing 0-0 of 0 companies</div>
                <div class="pagination-controls">
                    <button class="pagination-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>
                    <div class="pagination-pages">
                    </div>
                    <button class="pagination-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Company</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Code</label>
                        <input type="text" id="edit_company_code" readonly>
                    </div>
                    <div class="form-group">
                        <label>Company Name *</label>
                        <input type="text" id="edit_company_name" name="company_name" required>
                    </div>
                    <div class="form-group">
                        <label>Legal Name</label>
                        <input type="text" id="edit_legal_name" name="legal_name">
                    </div>
                    <div class="form-group">
                        <label>Industry Type</label>
                        <input type="text" id="edit_industry_type" name="industry_type">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="edit_email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" id="edit_phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Mobile No</label>
                        <input type="text" id="edit_mobile_no" name="mobile_no">
                    </div>
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" id="edit_website" name="website">
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea id="edit_address" name="address"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Country (Dropdown)</label>
                        <select id="edit_country_id" name="country_id">
                            <option value="">Select Country</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Country (Text)</label>
                        <input type="text" id="edit_country" name="country">
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <input type="text" id="edit_state" name="state">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" id="edit_city" name="city">
                    </div>
                    <div class="form-group">
                        <label>Zipcode</label>
                        <input type="text" id="edit_zipcode" name="zipcode">
                    </div>
                    <div class="form-group">
                        <label>P.O. Box No</label>
                        <input type="text" id="edit_po_box_no" name="po_box_no">
                    </div>
                    <div class="form-group">
                        <label>Registration Number</label>
                        <input type="text" id="edit_registration_number" name="registration_number">
                    </div>
                    <div class="form-group">
                        <label>Tax ID Number</label>
                        <input type="text" id="edit_tax_identification_number" name="tax_identification_number">
                    </div>
                    <div class="form-group">
                        <label>Sales Tax Number</label>
                        <input type="text" id="edit_sales_tax_number" name="sales_tax_number">
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <select id="edit_language_code" name="language_code">
                            <option value="en">English</option>
                            <option value="ur">Urdu</option>
                            <option value="sd">Sindhi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <select id="edit_timezone" name="timezone">
                            <option value="UTC">UTC</option>
                            <option value="GMT">GMT</option>
                            <option value="EST">Eastern Standard Time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Inventory Method</label>
                        <select id="edit_inventory_valuation_method" name="inventory_valuation_method">
                            <option value="FIFO">FIFO</option>
                            <option value="LIFO">LIFO</option>
                            <option value="AVCO">AVCO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="edit_is_active" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Company Logo</label>
                        <div class="logo-upload">
                            <div class="logo-preview" id="edit-logo-preview">
                                <span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>
                            </div>
                            <label for="edit_logo_file" class="logo-upload-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14,2 14,8 20,8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                    <polyline points="10,9 9,9 8,9" />
                                </svg>
                                Choose Logo
                            </label>
                            <input type="file" id="edit_logo_file" name="logo_file" accept="image/*" style="display: none;">
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Company</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="width: 400px;">
            <div class="modal-header">
                <h3>Delete Company</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div style="padding: 24px;">
                <p>Are you sure you want to delete this company?</p>
                <p><strong id="delete_company_name"></strong></p>
                <p style="color: var(--text-subtext); font-size: 14px;">This action cannot be undone.</p>
                <div class="modal-actions" style="margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="button" class="btn" style="background: var(--error); color: white;" onclick="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/company-profile/company-list.js"></script>
</body>
</html>