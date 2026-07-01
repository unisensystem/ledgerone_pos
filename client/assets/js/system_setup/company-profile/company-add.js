document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('company-form');
    const logoInput = document.getElementById('logo_url');
    const logoPreview = document.getElementById('logo-preview');
    const successNotification = document.getElementById('success-notification');
    const errorNotification = document.getElementById('error-notification');
    
    // Load countries on page load
    loadCountries();
    
    function loadCountries() {
        fetch('../../../../server/api/inventory/countries/countries-list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.countries) {
                    const countrySelect = document.getElementById('country_id');
                    data.countries.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.id;
                        option.textContent = country.country_name;
                        countrySelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading countries:', error));
    }
    // Logo upload preview
    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            
            reader.addEventListener('load', function() {
                logoPreview.innerHTML = `
                    <img src="${reader.result}" alt="Company Logo">
                    <button type="button" class="logo-remove-btn" id="logo-remove-btn">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;
                
                // Add remove functionality
                document.getElementById('logo-remove-btn').addEventListener('click', function() {
                    logoPreview.innerHTML = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
                    logoInput.value = '';
                });
            });
            
            reader.readAsDataURL(file);
        }
    });
    
    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Basic validation
        const companyName = document.getElementById('company_name').value;
        
        if (!companyName) {
            showNotification(errorNotification);
            return;
        }
        
        // Validate logo file
        const logoFile = logoInput.files[0];
        if (logoFile) {
            const allowedTypes = ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!allowedTypes.includes(logoFile.type)) {
                errorNotification.textContent = 'Invalid file type. Allowed: PNG, JPG, JPEG, GIF, PDF, WEBP, AVIF';
                showNotification(errorNotification);
                return;
            }
            
            if (logoFile.size > maxSize) {
                errorNotification.textContent = 'File size must be less than 5MB';
                showNotification(errorNotification);
                return;
            }
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        // Collect form data
        const formData = new FormData();
        formData.append('company_name', companyName);
        formData.append('legal_name', document.getElementById('legal_name').value);
        formData.append('industry_type', document.getElementById('industry_type').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('phone', document.getElementById('phone').value);
        formData.append('website', document.getElementById('website').value);
        formData.append('address', document.getElementById('address').value);
        formData.append('country_id', document.getElementById('country_id').value);
        formData.append('country', document.getElementById('country').value);
        formData.append('state', document.getElementById('state').value);
        formData.append('city', document.getElementById('city').value);
        formData.append('zipcode', document.getElementById('zipcode').value);
        formData.append('registration_number', document.getElementById('registration_number').value);
        formData.append('tax_identification_number', document.getElementById('tax_identification_number').value);
        formData.append('sales_tax_number', document.getElementById('sales_tax_number').value);
        formData.append('language_code', document.getElementById('language_code').value);
        formData.append('timezone', document.getElementById('timezone').value);
        formData.append('inventory_valuation_method', document.getElementById('inventory_valuation_method').value);
        formData.append('is_active', document.getElementById('is_active').checked);
        
        if (logoFile) {
            formData.append('logo_file', logoFile);
        }
        
        try {
            const response = await fetch('../../../../server/api/system_setup/company-profile/company-add.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                successNotification.textContent = result.message;
                showNotification(successNotification);
                form.reset();
                logoPreview.innerHTML = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
            } else {
                errorNotification.textContent = result.message || 'An error occurred while saving the company.';
                showNotification(errorNotification);
            }
        } catch (error) {
            errorNotification.textContent = 'Network error. Please try again.';
            showNotification(errorNotification);
        } finally {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                    <polyline points="17,21 17,13 7,13 7,21" />
                    <polyline points="7,3 7,8 15,8" />
                </svg>
                Save Company
            `;
        }
    });
    
    // Reset button
    document.getElementById('resetBtn').addEventListener('click', function() {
        form.reset();
        logoPreview.innerHTML = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
    });
    
    // Helper functions for notifications
    function showNotification(notification) {
        hideNotifications();
        notification.style.display = 'flex';
        
        setTimeout(() => {
            notification.style.display = 'none';
        }, 5000);
    }
    
    function hideNotifications() {
        successNotification.style.display = 'none';
        errorNotification.style.display = 'none';
    }
    

    
});