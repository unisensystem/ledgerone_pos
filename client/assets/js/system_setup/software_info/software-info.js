// Fetch and populate software info
fetch('../../../../server/api/system_setup/software_info/software-info.php')
    .then(res => res.text())
    .then(text => {
        console.log('Response:', text);
        const response = JSON.parse(text);
        if (response.success) {
            const data = response.data;
            document.querySelector('.form-field:nth-child(3) .form-value').textContent = data.tenant_id;
            document.querySelector('.form-field:nth-child(4) .form-value').textContent = data.business_name;
            document.querySelector('.form-field:nth-child(5) .form-value').textContent = data.domain;
            document.querySelector('.form-field:nth-child(6) .form-value').textContent = data.owner_user_id;
            document.querySelector('.form-field:nth-child(7) .form-value').textContent = data.contact_email;
            document.querySelector('.form-field:nth-child(8) .form-value').textContent = data.contact_phone;
            document.querySelector('.license-key').textContent = data.license_key;
            document.querySelector('.subscription-field:nth-child(1) .form-value').innerHTML = `<strong>${data.subscription_type}</strong>`;
            
            if (data.subscription_type === 'Lifetime') {
                document.querySelector('.subscription-field:nth-child(2)').style.display = 'none';
            } else {
                document.querySelector('.subscription-amount').textContent = `Rs ${data.subscription_amount}`;
                document.querySelector('.subscription-field:nth-child(2) .form-helper').textContent = `Per ${data.subscription_type.toLowerCase()}, excluding taxes`;
            }
            
            document.querySelector('.subscription-field:nth-child(3) .form-value').textContent = data.start_date;
            document.querySelector('.subscription-field:nth-child(4) .form-value').textContent = data.end_date;
            document.querySelector('.subscription-field:nth-child(5) .form-value').innerHTML = `<strong>${data.plan_name}</strong>`;
            window.licenseKey = data.license_key;
        } else {
            console.error('API Error:', response.message);
        }
    })
    .catch(err => console.error('Fetch Error:', err));

// Copy license key functionality
document.getElementById('copyLicenseBtn').addEventListener('click', function () {
    const licenseKey = window.licenseKey || 'FS-88C2-B9A7-4E1D-5F3A';
    navigator.clipboard.writeText(licenseKey).then(() => {
        const icon = this.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'fas fa-check';
        this.style.color = 'var(--success)';
        setTimeout(() => {
            icon.className = originalClass;
            this.style.color = '';
        }, 1500);
    });
});



// Check for Updates button
document.querySelector('.btn-primary').addEventListener('click', function () {
    const originalText = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    this.disabled = true;
    setTimeout(() => {
        this.innerHTML = '<i class="fas fa-check-circle"></i> Up to Date';
        this.style.backgroundColor = 'var(--success)';
        setTimeout(() => {
            this.innerHTML = originalText;
            this.style.backgroundColor = '';
            this.disabled = false;
        }, 2000);
    }, 1500);
});

// View Invoices Modal
const modal = document.getElementById('billingModal');
const viewInvoicesBtn = document.getElementById('viewInvoicesBtn');
const closeModal = document.getElementById('closeModal');

viewInvoicesBtn.addEventListener('click', function() {
    modal.classList.add('show');
    loadBillingLogs();
});

closeModal.addEventListener('click', function() {
    modal.classList.remove('show');
});

window.addEventListener('click', function(e) {
    if (e.target === modal) {
        modal.classList.remove('show');
    }
});

function loadBillingLogs() {
    fetch('../../../../server/api/system_setup/software_info/billing-logs.php')
        .then(res => res.json())
        .then(response => {
            const tbody = document.getElementById('billingTableBody');
            if (response.success && response.data.length > 0) {
                tbody.innerHTML = response.data.map(log => `
                    <tr>
                        <td>${log.invoice_number}</td>
                        <td>${log.billing_period}</td>
                        <td>Rs ${log.amount_paid}</td>
                        <td>${log.payment_method}</td>
                        <td><span class="status-badge status-${log.payment_status}">${log.payment_status}</span></td>
                        <td>${log.created_at}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="loading">No billing logs found</td></tr>';
            }
        })
        .catch(err => {
            console.error('Error loading billing logs:', err);
            document.getElementById('billingTableBody').innerHTML = '<tr><td colspan="6" class="loading">Error loading data</td></tr>';
        });
}