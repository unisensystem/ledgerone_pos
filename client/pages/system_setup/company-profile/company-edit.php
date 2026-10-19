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
$companyId = (int) ($_GET['id'] ?? 0);
if (!$companyId) {
    header('Location: company-list.php');   // change to your list page name
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Edit Company</title>
    <link rel="stylesheet" href="../../../assets/css/system_setup/company-profile/company-add.css">
</head>

<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit Company</h1>
                <p class="page-description">Update company information</p>
            </div>
        </div>

        <div class="notification success" id="success-notification">Company updated successfully!</div>
        <div class="notification error" id="error-notification">Please fill in all required fields correctly.</div>

        <div class="main-card">
            <form id="company-form" data-id="<?= $companyId ?>">
                <div class="form-section">
                    <h2 class="section-title">Basic Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="company_code">Company Code</label>
                            <input type="text" id="company_code" readonly>
                        </div>
                        <div class="form-group">
                            <label for="company_name" class="required">Company Name</label>
                            <input type="text" id="company_name" required>
                        </div>
                        <div class="form-group">
                            <label for="legal_name">Legal Name</label>
                            <input type="text" id="legal_name">
                        </div>
                        <div class="form-group">
                            <label for="industry_type">Industry Type</label>
                            <input type="text" id="industry_type">
                        </div>
                        <div class="form-group">
                            <label for="business_type" class="required">Business Type (POS)</label>
                            <select id="business_type" required>
                                <option value="restaurant">🍔 Restaurant</option>
                                <option value="bakery">🥐 Bakery</option>
                                <option value="karyana">🛒 Karyana Store</option>
                                <option value="general">🏪 General Store</option>
                                <option value="medical">💊 Medical Store</option>
                                <option value="mall">🏬 Mall / Retail</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Contact Information</h2>
                    <div class="form-grid">
                        <div class="form-group"><label for="email">Email</label><input type="email" id="email"></div>
                        <div class="form-group"><label for="phone">Phone</label><input type="text" id="phone"></div>
                        <div class="form-group"><label for="mobile_no">Mobile No</label><input type="text" id="mobile_no"></div>
                        <div class="form-group"><label for="website">Website</label><input type="url" id="website"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Address Information</h2>
                    <div class="form-grid">
                        <div class="form-group full-width"><label for="address">Address</label><textarea id="address"></textarea></div>
                        <div class="form-group"><label for="country">Country</label><input type="text" id="country"></div>
                        <div class="form-group"><label for="state">State</label><input type="text" id="state"></div>
                        <div class="form-group"><label for="city">City</label><input type="text" id="city"></div>
                        <div class="form-group"><label for="zipcode">Zipcode</label><input type="text" id="zipcode"></div>
                        <div class="form-group"><label for="po_box_no">P.O. Box No</label><input type="text" id="po_box_no"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Registration & Tax Information</h2>
                    <div class="form-grid">
                        <div class="form-group"><label for="registration_number">Registration Number</label><input type="text" id="registration_number"></div>
                        <div class="form-group"><label for="tax_identification_number">Tax Identification Number</label><input type="text" id="tax_identification_number"></div>
                        <div class="form-group"><label for="sales_tax_number">Sales Tax Number</label><input type="text" id="sales_tax_number"></div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">System Configuration</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="language_code">Language</label>
                            <select id="language_code">
                                <option value="en">English</option>
                                <option value="ur">Urdu</option>
                                <option value="sd">Sindhi</option>
                                <option value="pa">Punjabi</option>
                                <option value="bal">Balochi</option>
                                <option value="ps">Pashto</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="timezone">Timezone</label><input type="text" id="timezone"></div>
                        <div class="form-group">
                            <label for="inventory_valuation_method">Inventory Valuation Method</label>
                            <select id="inventory_valuation_method">
                                <option value="AVCO">AVCO</option>
                                <option value="FIFO">FIFO</option>
                                <option value="LIFO">LIFO</option>
                                <option value="SPECIFIC_IDENTIFICATION">Specific Identification</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Company Logo</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="logo-upload">
                                <div class="logo-preview" id="logo-preview">
                                    <span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>
                                </div>
                                <label for="logo_url" class="logo-upload-btn">Choose Logo</label>
                                <input type="file" id="logo_url" accept="image/*" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Status Settings</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active">
                                <label for="is_active">Active Company</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Company</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/company-profile/company-edit.js"></script>
</body>

</html>