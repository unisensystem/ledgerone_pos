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
    <title>Software Info - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/system_setup/software_info/software-info.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">FS</div>
                <div class="logo-text">
                    <h1>LedgerOne ERP</h1>
                    <p>Software Information Panel</p>
                </div>
            </div>
            <div class="subscription-badge">
                <i class="fas fa-check-circle"></i>
                Subscription Active
            </div>
        </div>
        
        <!-- Page Title -->
        <div class="page-title">
            <h2>Software Information</h2>
            <div class="status-active">System Operational</div>
        </div>
        
        <!-- Main Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Software Details</div>
                    <div class="card-subtitle">Read-only information about your LedgerOne ERP installation</div>
                </div>
                <button class="btn btn-ghost">
                    <i class="fas fa-edit"></i> Request Changes
                </button>
            </div>
            
            <!-- Form Grid -->
            <div class="form-grid">
                <!-- Software Name -->
                <div class="form-field">
                    <label class="form-label">Software Name</label>
                    <div class="form-value read-only">
                        LedgerOne ERP
                    </div>
                </div>
                
                <!-- Software Company -->
                <div class="form-field">
                    <label class="form-label">Software Company</label>
                    <div class="form-value read-only">
                        INNOVATECH UNIVERSAL (SMC-PRIVATE) LIMITED
                    </div>
                </div>
                
                <!-- Tenant ID -->
                <div class="form-field">
                    <label class="form-label">Tenant ID</label>
                    <div class="form-value read-only">
                        TN-7F8B2C9A
                    </div>
                </div>
                
                <!-- Business Name -->
                <div class="form-field">
                    <label class="form-label">Business Name</label>
                    <div class="form-value read-only">
                        Global Fuel Solutions Inc.
                    </div>
                </div>
                
                <!-- Domain -->
                <div class="form-field">
                    <label class="form-label">Domain</label>
                    <div class="form-value read-only">
                        https://ledgerone.innova-tech.link
                    </div>
                </div>
                
                <!-- Owner User ID -->
                <div class="form-field">
                    <label class="form-label">Owner User ID</label>
                    <div class="form-value read-only">
                        admin@globalfuels.com
                    </div>
                </div>
                
                <!-- Contact Email -->
                <div class="form-field">
                    <label class="form-label">Contact Email</label>
                    <div class="form-value read-only">
                        support@globalfuels.com
                    </div>
                </div>
                
                <!-- Contact Phone -->
                <div class="form-field">
                    <label class="form-label">Contact Phone</label>
                    <div class="form-value read-only">
                        +1 (555) 123-4567
                    </div>
                </div>
                
                <!-- License Key -->
                <div class="form-field license-key-container">
                    <label class="form-label">License Key</label>
                    <div class="form-value read-only license-key">
                        FS-88C2-B9A7-4E1D-5F3A
                    </div>
                    <button class="copy-btn" id="copyLicenseBtn" title="Copy to clipboard">
                        <i class="far fa-copy"></i>
                    </button>
                    <div class="form-helper">Valid until Dec 31, 2024</div>
                </div>
            </div>
        </div>
        
        <!-- Subscription Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Subscription Details</div>
                    <div class="card-subtitle">Your current subscription plan and billing information</div>
                </div>
                <button class="btn btn-ghost" id="viewInvoicesBtn">
                    <i class="fas fa-file-invoice-dollar"></i> View Invoices
                </button>
            </div>
            
            <div class="subscription-card">
                <!-- Subscription Type -->
                <div class="subscription-field">
                    <label class="form-label">Subscription Type</label>
                    <div class="form-value read-only">
                        <strong>Yearly</strong>
                    </div>
                </div>
                
                <!-- Subscription Amount -->
                <div class="subscription-field">
                    <label class="form-label">Subscription Amount</label>
                    <div class="subscription-amount">
                        $2,499.00
                    </div>
                    <div class="form-helper">Per year, excluding taxes</div>
                </div>
                
                <!-- Subscription Start Date -->
                <div class="subscription-field">
                    <label class="form-label">Subscription Start Date</label>
                    <div class="form-value read-only">
                        Jan 15, 2024
                    </div>
                </div>
                
                <!-- Subscription End Date -->
                <div class="subscription-field">
                    <label class="form-label">Subscription End Date</label>
                    <div class="form-value read-only">
                        Jan 14, 2025
                    </div>
                    <div class="form-helper">Auto-renewal enabled</div>
                </div>
                
                <!-- Plan Name -->
                <div class="subscription-field">
                    <label class="form-label">Plan Name</label>
                    <div class="form-value read-only">
                        <strong>Enterprise Plus</strong>
                    </div>
                    <div class="form-helper">Unlimited users, premium support</div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-secondary">
                <i class="fas fa-download"></i> Export Details
            </button>
            <button class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> Check for Updates
            </button>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Need help? Contact our support team at support@innova-tech.link or call +92 (346) 891-8711</p>
        </div>
    </div>

    <!-- Billing Logs Modal -->
    <div id="billingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Billing Logs & Invoices</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <table class="billing-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Billing Period</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="billingTableBody">
                        <tr>
                            <td colspan="6" class="loading">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/software_info/software-info.js"></script>
</body>
</html>