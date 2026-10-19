document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('company-form');
    const logoInput = document.getElementById('logo_url');
    const logoPreview = document.getElementById('logo-preview');
    const successNotification = document.getElementById('success-notification');
    const errorNotification = document.getElementById('error-notification');
    const NO_LOGO = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
    const API = '../../../../server/api/system_setup/company-profile/company-add.php';

    loadCountries();

    function loadCountries() {
        fetch('../../../../server/api/inventory/countries/countries-list.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.countries) {
                    const sel = document.getElementById('country_id');
                    data.countries.forEach(c => {
                        const o = document.createElement('option');
                        o.value = c.id;
                        o.textContent = c.country_name;
                        sel.appendChild(o);
                    });
                }
            })
            .catch(e => console.error('Error loading countries:', e));
    }

    logoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.addEventListener('load', function () {
            logoPreview.innerHTML = `<img src="${reader.result}" alt="Company Logo">
                <button type="button" class="logo-remove-btn" id="logo-remove-btn">&times;</button>`;
            document.getElementById('logo-remove-btn').addEventListener('click', function () {
                logoPreview.innerHTML = NO_LOGO;
                logoInput.value = '';
            });
        });
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const companyName = document.getElementById('company_name').value.trim();

        if (!companyName) return showNotification(errorNotification);

        const logoFile = logoInput.files[0];
        if (logoFile) {
            const allowed = ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
            if (!allowed.includes(logoFile.type)) {
                errorNotification.textContent = 'Invalid file type. Allowed: PNG, JPG, JPEG, GIF, PDF, WEBP, AVIF';
                return showNotification(errorNotification);
            }
            if (logoFile.size > 5 * 1024 * 1024) {
                errorNotification.textContent = 'File size must be less than 5MB';
                return showNotification(errorNotification);
            }
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        const formData = new FormData();
        ['legal_name', 'industry_type', 'business_type', 'email', 'phone', 'mobile_no', 'website', 'address',
         'country_id', 'country', 'state', 'city', 'zipcode', 'po_box_no', 'registration_number',
         'tax_identification_number', 'sales_tax_number', 'language_code', 'timezone', 'inventory_valuation_method']
            .forEach(id => formData.append(id, document.getElementById(id).value));
        formData.append('company_name', companyName);
        formData.append('is_active', document.getElementById('is_active').checked);
        if (logoFile) formData.append('logo_file', logoFile);

        try {
            let result = await (await fetch(API, { method: 'POST', body: formData })).json();

            if (!result.success && result.duplicate_warning) {
                if (confirm(result.message)) {
                    formData.append('confirm_duplicate', '1');
                    result = await (await fetch(API, { method: 'POST', body: formData })).json();
                } else {
                    return;
                }
            }

            if (result.success) {
                successNotification.textContent = result.message;
                showNotification(successNotification);
                form.reset();
                logoPreview.innerHTML = NO_LOGO;
            } else {
                errorNotification.textContent = result.message || 'An error occurred while saving the company.';
                showNotification(errorNotification);
            }
        } catch (err) {
            errorNotification.textContent = 'Network error. Please try again.';
            showNotification(errorNotification);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Company';
        }
    });

    document.getElementById('resetBtn').addEventListener('click', function () {
        form.reset();
        logoPreview.innerHTML = NO_LOGO;
    });

    function showNotification(n) {
        successNotification.style.display = 'none';
        errorNotification.style.display = 'none';
        n.style.display = 'flex';
        setTimeout(() => { n.style.display = 'none'; }, 5000);
    }
});