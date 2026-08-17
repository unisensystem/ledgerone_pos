document.addEventListener('DOMContentLoaded', function () {
    // Set today's date as default for lead date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('leadDate').value = today;

    // Generate a random lead code (in a real app, this would come from the backend)
    function generateLeadCode() {
        const year = new Date().getFullYear();
        const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        return `LD-${year}-${randomNum}`;
    }

    const leadCode = generateLeadCode();
    document.getElementById('leadCodeDisplay').textContent = leadCode;

    // Copy lead code functionality
    document.getElementById('copyCodeBtn').addEventListener('click', function () {
        const code = document.getElementById('leadCodeDisplay').textContent;
        navigator.clipboard.writeText(code).then(() => {
            showToast('Lead code copied to clipboard!', 'success');
        });
    });

    // Help button
    document.getElementById('helpBtn').addEventListener('click', function () {
        showToast('Please fill in all required fields marked with *', 'success');
    });

    // View all leads button
    document.getElementById('viewLeadsBtn').addEventListener('click', function () {
        showToast('Redirecting to leads dashboard...', 'success');
        // In a real app, this would redirect to the leads list page
    });

    // Reset form
    document.getElementById('resetBtn').addEventListener('click', function () {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            document.getElementById('leadsForm').reset();
            document.getElementById('leadDate').value = today;
            document.getElementById('leadCodeDisplay').textContent = generateLeadCode();
            showToast('Form has been reset', 'success');
        }
    });

    // Save lead
    document.getElementById('saveBtn').addEventListener('click', function (e) {
        e.preventDefault();

        // Simple validation
        const requiredFields = ['leadSource', 'leadDate', 'contactName', 'email', 'primaryPhone', 'productInterest'];
        let isValid = true;

        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                field.parentElement.classList.add('error');
                isValid = false;
            } else {
                field.parentElement.classList.remove('error');
            }
        });

        // Email validation
        const emailField = document.getElementById('email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailField.value && !emailRegex.test(emailField.value)) {
            emailField.parentElement.classList.add('error');
            emailField.parentElement.querySelector('.helper-text').textContent = 'Please enter a valid email address';
            isValid = false;
        }

        if (!isValid) {
            showToast('Please fill in all required fields correctly', 'error');
            return;
        }

        // Collect form data
        const formData = {
            leadCode: document.getElementById('leadCodeDisplay').textContent,
            leadSource: document.getElementById('leadSource').value,
            leadDate: document.getElementById('leadDate').value,
            leadStatus: document.getElementById('leadStatus').value,
            contactName: document.getElementById('contactName').value,
            businessName: document.getElementById('businessName').value,
            priority: document.getElementById('priority').value,
            email: document.getElementById('email').value,
            primaryPhone: document.getElementById('primaryPhone').value,
            secondaryPhone: document.getElementById('secondaryPhone').value,
            productInterest: document.getElementById('productInterest').value,
            timestamp: new Date().toLocaleString()
        };

        // In a real app, you would send this data to a backend API
        console.log('Lead data to be saved:', formData);

        // Show success message
        showToast('Lead saved successfully!', 'success',
            `Lead ${formData.leadCode} for ${formData.contactName} has been saved.`);

        // Generate new lead code for next entry
        document.getElementById('leadCodeDisplay').textContent = generateLeadCode();

        // Reset form (optional, could keep data for multiple entries)
        // document.getElementById('leadsForm').reset();
        // document.getElementById('leadDate').value = today;
    });

    // Toast notification function
    function showToast(message, type = 'success', details = '') {
        const toast = document.getElementById('toast');
        const toastIcon = toast.querySelector('.toast-icon');
        const toastMessage = toast.querySelector('.toast-message');
        const toastDetails = document.getElementById('toastDetails');

        // Update toast content
        toastMessage.textContent = message;
        toastDetails.textContent = details;

        // Update toast type
        toast.className = 'toast';
        toast.classList.add(type);

        // Show toast
        toast.classList.add('show');

        // Hide toast after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 5000);
    }

    // Remove error state when user starts typing in a field
    const formInputs = document.querySelectorAll('#leadsForm input, #leadsForm select');
    formInputs.forEach(input => {
        input.addEventListener('input', function () {
            this.parentElement.classList.remove('error');

            // Clear custom error message for email
            if (this.id === 'email') {
                this.parentElement.querySelector('.helper-text').textContent =
                    "We'll never share your email with anyone else.";
            }
        });
    });

    // Format the status indicator display in dropdown
    const statusSelect = document.getElementById('leadStatus');
    statusSelect.innerHTML = `
        <option value="New"><span class="status-indicator status-new"></span> New</option>
        <option value="Contacted"><span class="status-indicator status-contacted"></span> Contacted</option>
        <option value="Qualified"><span class="status-indicator status-qualified"></span> Qualified</option>
        <option value="Proposal Sent"><span class="status-indicator status-proposal"></span> Proposal Sent</option>
    `;
});