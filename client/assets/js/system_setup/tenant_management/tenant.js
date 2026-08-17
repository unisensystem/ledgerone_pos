// Data Storage
let tenantsData = [];
let subscriptionsData = [];
let billingLogsData = [];
let plansData = [];

const API_BASE = '../../../../server/api/system_setup/tenant_management';

// DOM Elements
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');
const addTenantBtn = document.getElementById('addTenantBtn');
const addTenantModal = document.getElementById('addTenantModal');
const closeModalBtn = document.getElementById('closeModal');
const cancelBtn = document.getElementById('cancelBtn');
const saveTenantBtn = document.getElementById('saveTenantBtn');
const tenantForm = document.getElementById('tenantForm');
const addSubscriptionBtn = document.getElementById('addSubscriptionBtn');
const addSubscriptionModal = document.getElementById('addSubscriptionModal');
const closeSubscriptionModalBtn = document.getElementById('closeSubscriptionModal');
const cancelSubscriptionBtn = document.getElementById('cancelSubscriptionBtn');
const saveSubscriptionBtn = document.getElementById('saveSubscriptionBtn');
const subscriptionForm = document.getElementById('subscriptionForm');
const tenantsTableBody = document.getElementById('tenantsTableBody');
const subscriptionsTableBody = document.getElementById('subscriptionsTableBody');
const billingTableBody = document.getElementById('billingTableBody');
const plansTableBody = document.getElementById('plansTableBody');

// Tab Switching
tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const tabId = tab.getAttribute('data-tab');

        // Update active tab
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Show active content
        tabContents.forEach(content => {
            content.classList.remove('active');
            if (content.id === `${tabId}Tab`) {
                content.classList.add('active');
            }
        });
    });
});

// Modal Functions
addTenantBtn.addEventListener('click', () => {
    addTenantModal.classList.add('active');
});

addSubscriptionBtn.addEventListener('click', async () => {
    populateTenantDropdown();
    populatePlanDropdown();
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('subStartDate').value = today;
    document.getElementById('subBillingCycle').value = 'monthly';
    calculateEndDate();
    await generateLicenseKey();
    addSubscriptionModal.classList.add('active');
});

// Generate License Key
async function generateLicenseKey() {
    const startDate = document.getElementById('subStartDate').value;
    const billingCycle = document.getElementById('subBillingCycle').value;
    
    if (!startDate) return;
    
    try {
        const response = await fetch(`${API_BASE}/generate-license-key.php?start_date=${startDate}&billing_cycle=${billingCycle}`);
        const result = await response.json();
        if (result.success) {
            document.getElementById('subLicenseKey').value = result.license_key;
        }
    } catch (error) {
        console.error('Failed to generate license key:', error);
    }
}

// Event listeners setup after DOM is ready
if (document.getElementById('subStartDate')) {
    document.getElementById('subStartDate').addEventListener('change', () => {
        calculateEndDate();
        generateLicenseKey();
    });
}

if (document.getElementById('subBillingCycle')) {
    document.getElementById('subBillingCycle').addEventListener('change', () => {
        updateAmount();
        calculateEndDate();
        generateLicenseKey();
    });
}

// Calculate End Date based on billing cycle and start date
function calculateEndDate() {
    const startDate = document.getElementById('subStartDate').value;
    const billingCycle = document.getElementById('subBillingCycle').value;
    const status = document.getElementById('subStatus').value;
    
    if (startDate) {
        const start = new Date(startDate);
        let endDate;
        
        if (status === 'trialing') {
            endDate = new Date(start);
            endDate.setDate(endDate.getDate() + 30); // 30 days trial
            document.getElementById('endDateLabel').textContent = 'Trial End Date *';
        } else {
            if (billingCycle === 'annual') {
                endDate = new Date(start);
                endDate.setFullYear(endDate.getFullYear() + 1);
            } else {
                endDate = new Date(start);
                endDate.setMonth(endDate.getMonth() + 1);
            }
            document.getElementById('endDateLabel').textContent = 'End Date *';
        }
        
        document.getElementById('subEndDate').value = endDate.toISOString().split('T')[0];
    }
}

// Add event listeners for auto-calculation
document.getElementById('subStatus').addEventListener('change', calculateEndDate);

closeModalBtn.addEventListener('click', () => {
    addTenantModal.classList.remove('active');
    tenantForm.reset();
});

cancelBtn.addEventListener('click', () => {
    addTenantModal.classList.remove('active');
    tenantForm.reset();
});

closeSubscriptionModalBtn.addEventListener('click', () => {
    addSubscriptionModal.classList.remove('active');
    subscriptionForm.reset();
});

cancelSubscriptionBtn.addEventListener('click', () => {
    addSubscriptionModal.classList.remove('active');
    subscriptionForm.reset();
});

saveTenantBtn.addEventListener('click', async () => {
    const tenantData = {
        business_name: document.getElementById('businessName').value,
        legal_name: document.getElementById('legalName').value,
        tax_id: document.getElementById('taxId').value,
        subdomain: document.getElementById('subdomain').value,
        contact_email: document.getElementById('contactEmail').value,
        contact_phone: document.getElementById('contactPhone').value,
        status: document.getElementById('status').value,
        onboarding_stage: document.getElementById('onboardingStage').value,
        language: document.getElementById('language').value
    };

    try {
        const response = await fetch(`${API_BASE}/tenant-add.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(tenantData)
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Tenant saved successfully!');
            addTenantModal.classList.remove('active');
            tenantForm.reset();
            loadTenants();
        } else {
            alert('Error: ' + (result.message || result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Save tenant error:', error);
        alert('Failed to save tenant: ' + error.message);
    }
});

// Load Data from API
async function loadTenants() {
    try {
        const response = await fetch(`${API_BASE}/tenant-list.php`);
        const result = await response.json();
        if (result.success) {
            tenantsData = result.data;
            populateTenantsTable();
        }
    } catch (error) {
        console.error('Failed to load tenants:', error);
    }
}

async function loadSubscriptions() {
    try {
        const response = await fetch(`${API_BASE}/subscription-list.php`);
        const result = await response.json();
        if (result.success) {
            subscriptionsData = result.data;
            populateSubscriptionsTable();
        }
    } catch (error) {
        console.error('Failed to load subscriptions:', error);
    }
}

async function loadBillingLogs() {
    try {
        const response = await fetch(`${API_BASE}/billing-logs.php`);
        const result = await response.json();
        if (result.success) {
            billingLogsData = result.data;
            populateBillingTable();
        }
    } catch (error) {
        console.error('Failed to load billing logs:', error);
    }
}

async function loadPlans() {
    try {
        const response = await fetch(`${API_BASE}/plans-list.php`);
        const result = await response.json();
        if (result.success) {
            plansData = result.data;
            populatePlansTable();
        }
    } catch (error) {
        console.error('Failed to load plans:', error);
    }
}

// Populate Tables
function populateTenantsTable() {
    tenantsTableBody.innerHTML = '';

    tenantsData.forEach(tenant => {
        const row = document.createElement('tr');

        // Format date
        const createdDate = new Date(tenant.created_at);
        const formattedDate = createdDate.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        // Get status badge class
        let statusClass = '';
        switch (tenant.status) {
            case 'active': statusClass = 'status-active'; break;
            case 'pending': statusClass = 'status-pending'; break;
            case 'suspended': statusClass = 'status-suspended'; break;
            case 'deactivated': statusClass = 'status-deactivated'; break;
        }

        row.innerHTML = `
                    <td>
                        <div style="font-weight: 600;">${tenant.business_name}</div>
                        <div style="font-size: 12px; color: var(--grey-300); opacity: 0.7;">${tenant.contact_email || ''}</div>
                    </td>
                    <td>${tenant.subdomain}.innova-tech.link</td>
                    <td><span class="status-badge ${statusClass}">${tenant.status.toUpperCase()}</span></td>
                    <td>${tenant.onboarding_stage ? tenant.onboarding_stage.replace('_', ' ').toUpperCase() : ''}</td>
                    <td>${formattedDate}</td>
                    <td>
                        <div class="action-buttons">
                            <div class="action-btn view" title="View">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="action-btn edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div class="action-btn delete" title="Delete">
                                <i class="fas fa-trash"></i>
                            </div>
                        </div>
                    </td>
                `;

        tenantsTableBody.appendChild(row);
    });
}

function populateSubscriptionsTable() {
    subscriptionsTableBody.innerHTML = '';

    subscriptionsData.forEach(sub => {
        const row = document.createElement('tr');

        // Format dates
        const startDate = new Date(sub.start_date);
        const endDate = new Date(sub.end_date);
        const nextBillingDate = new Date(sub.next_billing_date);

        const formatDate = (date) => {
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        };

        // Get status badge class
        let statusClass = '';
        switch (sub.status) {
            case 'active': statusClass = 'status-active'; break;
            case 'trialing': statusClass = 'status-trialing'; break;
            case 'past_due': statusClass = 'status-pending'; break;
            case 'canceled': statusClass = 'status-deactivated'; break;
        }

        row.innerHTML = `
                    <td>${sub.tenant_name}</td>
                    <td>${sub.plan_name}</td>
                    <td>${sub.billing_cycle.toUpperCase()}</td>
                    <td><span class="status-badge ${statusClass}">${sub.status.toUpperCase()}</span></td>
                    <td>${formatDate(startDate)}</td>
                    <td>${formatDate(endDate)}</td>
                    <td>${formatDate(nextBillingDate)}</td>
                `;

        subscriptionsTableBody.appendChild(row);
    });
}

function populateBillingTable() {
    billingTableBody.innerHTML = '';

    billingLogsData.forEach(log => {
        const row = document.createElement('tr');

        let statusClass = '';
        switch (log.payment_status) {
            case 'success': statusClass = 'status-active'; break;
            case 'pending': statusClass = 'status-pending'; break;
            case 'failed': statusClass = 'status-suspended'; break;
            case 'refunded': statusClass = 'status-deactivated'; break;
        }

        const date = new Date(log.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        row.innerHTML = `
                    <td>${log.invoice_number}</td>
                    <td>${log.tenant_name}</td>
                    <td><strong>${log.currency} ${parseFloat(log.amount_paid).toFixed(2)}</strong></td>
                    <td>${log.payment_method}</td>
                    <td><span class="status-badge ${statusClass}">${log.payment_status.toUpperCase()}</span></td>
                    <td>${date}</td>
                    <td><code style="font-size: 12px;">${log.transaction_id}</code></td>
                `;

        billingTableBody.appendChild(row);
    });
}

function populatePlansTable() {
    plansTableBody.innerHTML = '';

    plansData.forEach(plan => {
        const row = document.createElement('tr');
        const features = [];
        if (plan.has_inventory) features.push('Inventory');
        if (plan.has_accounting) features.push('Accounting');
        if (plan.has_crm) features.push('CRM');
        if (plan.has_manufacturing) features.push('Manufacturing');
        
        row.innerHTML = `
                    <td><strong>${plan.name}</strong></td>
                    <td>$${parseFloat(plan.monthly_price).toFixed(2)}</td>
                    <td>$${parseFloat(plan.annual_price).toFixed(2)}</td>
                    <td>${plan.max_users}</td>
                    <td>${plan.max_storage_mb} MB</td>
                    <td>${features.join(', ') || 'Basic'}</td>
                    <td><span class="status-badge status-active">${plan.is_active ? 'ACTIVE' : 'INACTIVE'}</span></td>
                    <td>
                        <div class="action-buttons">
                            <div class="action-btn edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </div>
                        </div>
                    </td>
                `;

        plansTableBody.appendChild(row);
    });
}

// Populate Dropdowns
function populateTenantDropdown() {
    const searchInput = document.getElementById('subTenantSearch');
    const dropdown = document.getElementById('tenantDropdown');
    const hiddenInput = document.getElementById('subTenantId');
    
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const filtered = tenantsData.filter(tenant => 
            tenant.business_name.toLowerCase().includes(searchTerm)
        );
        
        if (filtered.length > 0 && searchTerm) {
            dropdown.innerHTML = '';
            filtered.forEach(tenant => {
                const item = document.createElement('div');
                item.className = 'dropdown-item';
                item.textContent = tenant.business_name;
                item.dataset.id = tenant.id;
                item.addEventListener('click', () => {
                    searchInput.value = tenant.business_name;
                    hiddenInput.value = tenant.id;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    });
    
    searchInput.addEventListener('focus', () => {
        if (searchInput.value) {
            searchInput.dispatchEvent(new Event('input'));
        }
    });
    
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function populatePlanDropdown() {
    const select = document.getElementById('subPlanId');
    select.innerHTML = '<option value="">Select Plan</option>';
    plansData.forEach(plan => {
        select.innerHTML += `<option value="${plan.id}" data-monthly="${plan.monthly_price}" data-annual="${plan.annual_price}">${plan.name} - $${plan.monthly_price}/mo</option>`;
    });
}

// Update amount when plan or billing cycle changes
function updateAmount() {
    const planSelect = document.getElementById('subPlanId');
    const billingCycle = document.getElementById('subBillingCycle').value;
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    
    if (selectedOption.value) {
        const amount = billingCycle === 'annual' 
            ? selectedOption.getAttribute('data-annual')
            : selectedOption.getAttribute('data-monthly');
        
        document.getElementById('subAmount').value = parseFloat(amount).toFixed(2);
        document.getElementById('amountLabel').textContent = billingCycle === 'annual' ? 'Annual Amount *' : 'Monthly Amount *';
    }
}

document.getElementById('subPlanId').addEventListener('change', updateAmount);
document.getElementById('subBillingCycle').addEventListener('change', () => {
    updateAmount();
    calculateEndDate();
});

// Save Subscription
saveSubscriptionBtn.addEventListener('click', async () => {
    const billingCycle = document.getElementById('subBillingCycle').value;
    const amount = document.getElementById('subAmount').value;
    
    const subscriptionData = {
        tenant_id: document.getElementById('subTenantId').value,
        plan_id: document.getElementById('subPlanId').value,
        billing_cycle: billingCycle,
        amount: amount,
        start_date: document.getElementById('subStartDate').value,
        end_date: document.getElementById('subEndDate').value,
        status: document.getElementById('subStatus').value,
        license_key: document.getElementById('subLicenseKey').value
    };

    try {
        const response = await fetch(`${API_BASE}/subscription-add.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(subscriptionData)
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Subscription saved successfully!');
            addSubscriptionModal.classList.remove('active');
            subscriptionForm.reset();
            loadSubscriptions();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Failed to save subscription');
    }
});

// Initialize - Load Data
loadTenants();
loadSubscriptions();
loadBillingLogs();
loadPlans();

// Add event listeners to action buttons
document.addEventListener('click', (e) => {
    if (e.target.closest('.action-btn.delete')) {
        if (confirm('Are you sure you want to delete this tenant?')) {
            alert('Tenant deleted successfully!');
        }
    }

    if (e.target.closest('.action-btn.edit')) {
        alert('Edit tenant functionality would open here.');
    }

    if (e.target.closest('.action-btn.view')) {
        alert('View tenant details would open here.');
    }
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    if (e.target === addTenantModal) {
        addTenantModal.classList.remove('active');
        tenantForm.reset();
    }
    if (e.target === addSubscriptionModal) {
        addSubscriptionModal.classList.remove('active');
        subscriptionForm.reset();
    }
});