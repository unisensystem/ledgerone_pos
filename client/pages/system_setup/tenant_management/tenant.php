<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management - FuelingSys ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/system_setup/tenant_management/tenant.css">
</head>
<body>
    <div class="app-container">
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <header class="page-header">
                <div class="page-title">
                    <h1>Tenant Management</h1>
                    <p>Manage all businesses using LedgerOne ERP</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <button class="btn btn-primary" id="addTenantBtn">
                        <i class="fas fa-plus"></i> Add Tenant
                    </button>
                </div>
            </header>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="tenants">Tenants</div>
                <div class="tab" data-tab="subscriptions">Subscriptions</div>
                <div class="tab" data-tab="billing">Billing Logs</div>
                <div class="tab" data-tab="plans">Plans</div>
            </div>
            
            <!-- Tenants Tab -->
            <div class="tab-content active" id="tenantsTab">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Tenants</h3>
                        <div class="search-box">
                            <input type="text" placeholder="Search tenants..." style="width: 250px;">
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table id="tenantsTable">
                            <thead>
                                <tr>
                                    <th>Business Name</th>
                                    <th>Subdomain</th>
                                    <th>Status</th>
                                    <th>Onboarding</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tenantsTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="padding: 16px; text-align: center; color: var(--grey-300);">
                        Showing 8 of 124 tenants
                    </div>
                </div>
            </div>
            
            <!-- Subscriptions Tab -->
            <div class="tab-content" id="subscriptionsTab">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Active Subscriptions</h3>
                        <button class="btn btn-primary" id="addSubscriptionBtn">
                            <i class="fas fa-plus"></i> Add Subscription
                        </button>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tenant</th>
                                    <th>Plan</th>
                                    <th>Billing Cycle</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Next Billing</th>
                                </tr>
                            </thead>
                            <tbody id="subscriptionsTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Billing Logs Tab -->
            <div class="tab-content" id="billingTab">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Billing History</h3>
                        <div>
                            <select style="width: 200px;">
                                <option>Last 30 Days</option>
                                <option>Last 90 Days</option>
                                <option>This Year</option>
                                <option>All Time</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Tenant</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Transaction ID</th>
                                </tr>
                            </thead>
                            <tbody id="billingTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Plans Tab -->
            <div class="tab-content" id="plansTab">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Available Plans</h3>
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Plan
                        </button>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Plan Name</th>
                                    <th>Monthly Price</th>
                                    <th>Annual Price</th>
                                    <th>Max Users</th>
                                    <th>Max Storage</th>
                                    <th>Features</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="plansTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Tenant Modal -->
    <div class="modal" id="addTenantModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Tenant</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="tenantForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="businessName">Business Name *</label>
                            <input type="text" id="businessName" required>
                        </div>
                        <div class="form-group">
                            <label for="legalName">Legal Name</label>
                            <input type="text" id="legalName">
                        </div>
                        <div class="form-group">
                            <label for="taxId">Tax ID</label>
                            <input type="text" id="taxId">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subdomain">Subdomain *</label>
                            <div style="display: flex; align-items: center;">
                                <input type="text" id="subdomain" required value="fuelingsys">
                                <span style="margin-left: 8px;">.innova-tech.link</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status">
                                <option value="pending">Pending</option>
                                <option value="active" selected>Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="deactivated">Deactivated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="language">Language</label>
                            <select id="language">
                                <option value="en" selected>English</option>
                                <option value="es">Spanish</option>
                                <option value="fr">French</option>
                                <option value="de">German</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contactEmail">Contact Email</label>
                            <input type="email" id="contactEmail">
                        </div>
                        <div class="form-group">
                            <label for="contactPhone">Contact Phone</label>
                            <input type="tel" id="contactPhone">
                        </div>
                        <div class="form-group">
                            <label for="onboardingStage">Onboarding Stage</label>
                            <select id="onboardingStage">
                                <option value="initial" selected>Initial</option>
                                <option value="company_setup">Company Setup</option>
                                <option value="users_invited">Users Invited</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" id="saveTenantBtn">Save Tenant</button>
            </div>
        </div>
    </div>

    <!-- Add Subscription Modal -->
    <div class="modal" id="addSubscriptionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Subscription</h3>
                <button class="modal-close" id="closeSubscriptionModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="subscriptionForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subTenantId">Tenant *</label>
                            <input type="text" id="subTenantSearch" placeholder="Search tenant..." autocomplete="off">
                            <div id="tenantDropdown" class="dropdown-list" style="display:none;"></div>
                            <input type="hidden" id="subTenantId" required>
                        </div>
                        <div class="form-group">
                            <label for="subPlanId">Plan *</label>
                            <select id="subPlanId" required></select>
                        </div>
                        <div class="form-group">
                            <label for="subBillingCycle">Billing Cycle *</label>
                            <select id="subBillingCycle" required>
                                <option value="monthly" selected>Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subAmount" id="amountLabel">Monthly Amount *</label>
                            <input type="number" id="subAmount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="subLicenseKey">License Key *</label>
                            <input type="text" id="subLicenseKey" required>
                        </div>
                        <div class="form-group"></div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subStartDate">Start Date *</label>
                            <input type="date" id="subStartDate" required>
                        </div>
                        <div class="form-group">
                            <label for="subEndDate" id="endDateLabel">End Date *</label>
                            <input type="date" id="subEndDate" required>
                        </div>
                        <div class="form-group">
                            <label for="subStatus">Status</label>
                            <select id="subStatus">
                                <option value="active" selected>Active</option>
                                <option value="trialing">Trialing</option>
                                <option value="past_due">Past Due</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelSubscriptionBtn">Cancel</button>
                <button class="btn btn-primary" id="saveSubscriptionBtn">Save Subscription</button>
            </div>
        </div>
    </div>

    <script src="../../../assets/js/system_setup/tenant_management/tenant.js"></script>
</body>
</html>