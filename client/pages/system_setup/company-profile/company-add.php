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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Company Entry</title>
    <link rel="stylesheet" href="../../../assets/css/system_setup/company-profile/company-add.css">
</head>

<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Company Entry</h1>
                <p class="page-description">Add new company information to the system</p>
            </div>
        </div>

        <div class="notification success" id="success-notification">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5" /></svg>
            Company information saved successfully!
        </div>
        <div class="notification error" id="error-notification">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" /><path d="m15 9-6 6M9 9l6 6" /></svg>
            Please fill in all required fields correctly.
        </div>

        <div class="main-card">
            <form id="company-form">
                <div class="form-section">
                    <h2 class="section-title">Basic Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="company_code" class="required">Company Code</label>
                            <input type="text" id="company_code" name="company_code" placeholder="Automatically generated" readonly>
                        </div>
                        <div class="form-group">
                            <label for="company_name" class="required">Company Name</label>
                            <input type="text" id="company_name" name="company_name" placeholder="Enter company name" required>
                        </div>
                        <div class="form-group">
                            <label for="legal_name">Legal Name</label>
                            <input type="text" id="legal_name" name="legal_name" placeholder="Enter legal name">
                        </div>
                        <div class="form-group">
                            <label for="industry_type">Industry Type</label>
                            <input type="text" id="industry_type" name="industry_type" placeholder="e.g., Manufacturing, Technology">
                        </div>
                        <div class="form-group">
                            <label for="business_type" class="required">Business Type (POS)</label>
                            <select id="business_type" name="business_type" required>
                                <option value="restaurant">🍔 Restaurant</option>
                                <option value="bakery">🥐 Bakery</option>
                                <option value="karyana">🛒 Karyana Store</option>
                                <option value="general" selected>🏪 General Store</option>
                                <option value="medical">💊 Medical Store</option>
                                <option value="mall">🏬 Mall / Retail</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Contact Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="company@example.com">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" placeholder="+1 (555) 123-4567">
                        </div>
                        <div class="form-group">
                            <label for="mobile_no">Mobile No</label>
                            <input type="text" id="mobile_no" name="mobile_no" placeholder="+92 300 1234567">
                        </div>
                        <div class="form-group">
                            <label for="website">Website</label>
                            <input type="url" id="website" name="website" placeholder="https://www.company.com">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Address Information</h2>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Enter company address"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="country_id">Country (Dropdown)</label>
                            <select id="country_id" name="country_id">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="country">Country (Text)</label>
                            <input value="Pakistan" type="text" id="country" name="country" placeholder="United States">
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" placeholder="Sindh">
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" placeholder="Karachi">
                        </div>
                        <div class="form-group">
                            <label for="zipcode">Zipcode</label>
                            <input type="text" id="zipcode" name="zipcode" placeholder="90210">
                        </div>
                        <div class="form-group">
                            <label for="po_box_no">P.O. Box No</label>
                            <input type="text" id="po_box_no" name="po_box_no" placeholder="P.O. Box 12345">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Registration & Tax Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="registration_number">Registration Number</label>
                            <input type="text" id="registration_number" name="registration_number" placeholder="REG-123456789">
                        </div>
                        <div class="form-group">
                            <label for="tax_identification_number">Tax Identification Number</label>
                            <input type="text" id="tax_identification_number" name="tax_identification_number" placeholder="12-3456789">
                        </div>
                        <div class="form-group">
                            <label for="sales_tax_number">Sales Tax Number</label>
                            <input type="text" id="sales_tax_number" name="sales_tax_number" placeholder="ST-123456">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">System Configuration</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="language_code">Language</label>
                            <select id="language_code" name="language_code">
                                <option value="en" selected>English</option>
                                <option value="ur">Urdu</option>
                                <option value="sd">Sindhi</option>
                                <option value="pa">Punjabi</option>
                                <option value="bal">Balochi</option>
                                <option value="ps">Pashto</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <input type="text" id="timezone" name="timezone" value="Asia/Karachi" placeholder="e.g., GMT, UTC, EST">
                        </div>
                        <div class="form-group">
                            <label for="inventory_valuation_method">Inventory Valuation Method</label>
                            <select id="inventory_valuation_method" name="inventory_valuation_method">
                                <option value="AVCO" selected>AVCO</option>
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
                                <input type="file" id="logo_url" name="logo_url" accept="image/*" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">Status Settings</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" checked>
                                <label for="is_active">Active Company</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="resetBtn"><i class="fas fa-undo"></i> Reset Form</button>
                    <button type="submit" class="btn btn-primary">Save Company</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/company-profile/company-add.js"></script>
</body>

</html>