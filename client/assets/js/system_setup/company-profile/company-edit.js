document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('company-form');
    const companyId = form.dataset.id;
    const logoInput = document.getElementById('logo_url');
    const logoPreview = document.getElementById('logo-preview');
    const successNotification = document.getElementById('success-notification');
    const errorNotification = document.getElementById('error-notification');
    const NO_LOGO = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
    const API = '../../../../server/api/system_setup/company-profile/company-edit.php';
    const LOGO_DIR = '../../../assets/uploads/company_logo/';
    const FIELDS = ['company_code', 'company_name', 'legal_name', 'industry_type', 'business_type', 'email', 'phone',
        'mobile_no', 'website', 'address', 'country', 'state', 'city', 'zipcode', 'po_box_no', 'registration_number',
        'tax_identification_number', 'sales_tax_number', 'language_code', 'timezone', 'inventory_valuation_method'];
    let removeLogo = false;

    function showLogo(file) {
        if (!file) { logoPreview.innerHTML = NO_LOGO; return; }
        const isPdf = /\.pdf$/i.test(file);
        logoPreview.innerHTML = (isPdf ? '<span style="font-size:12px">PDF</span>'
            : `<img src="${LOGO_DIR}${encodeURI(file)}" alt="Company Logo">`) +
            '<button type="button" class="logo-remove-btn" id="logo-remove-btn">&times;</button>';
        bindRemove();
    }

    function bindRemove() {
        const b = document.getElementById('logo-remove-btn');
        if (b) b.addEventListener('click', function () {
            logoPreview.innerHTML = NO_LOGO;
            logoInput.value = '';
            removeLogo = true;
        });
    }

    async function loadCompany() {
        try {
            const r = await (await fetch(`${API}?id=${companyId}`)).json();
            if (!r.success) {
                errorNotification.textContent = r.message || 'Company not found';
                return showNotification(errorNotification);
            }
            const c = r.company;
            FIELDS.forEach(f => { document.getElementById(f).value = c[f] ?? ''; });
            document.getElementById('business_type').value = c.business_type || 'general';
            document.getElementById('is_active').checked = c.is_active == 1;
            showLogo(c.logo_url);
        } catch (e) {
            errorNotification.textContent = 'Failed to load company';
            showNotification(errorNotification);
        }
    }

    logoInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        removeLogo = false;
        const reader = new FileReader();
        reader.addEventListener('load', function () {
            logoPreview.innerHTML = `<img src="${reader.result}" alt="Company Logo">
                <button type="button" class="logo-remove-btn" id="logo-remove-btn">&times;</button>`;
            bindRemove();
        });
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const name = document.getElementById('company_name').value.trim();
        if (!name) return showNotification(errorNotification);

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
        submitBtn.textContent = 'Updating...';

        const fd = new FormData();
        fd.append('id', companyId);
        FIELDS.filter(f => f !== 'company_code').forEach(f => fd.append(f, document.getElementById(f).value));
        fd.append('company_name', name);
        fd.append('is_active', document.getElementById('is_active').checked);
        fd.append('remove_logo', removeLogo ? '1' : '0');
        if (logoFile) fd.append('logo_file', logoFile);

        try {
            let result = await (await fetch(API, { method: 'POST', body: fd })).json();
            if (!result.success && result.duplicate_warning) {
                if (confirm(result.message)) {
                    fd.append('confirm_duplicate', '1');
                    result = await (await fetch(API, { method: 'POST', body: fd })).json();
                } else {
                    return;
                }
            }
            if (result.success) {
                successNotification.textContent = result.message;
                showNotification(successNotification);
                logoInput.value = '';
                removeLogo = false;
                loadCompany();
            } else {
                errorNotification.textContent = result.message || 'An error occurred while updating the company.';
                showNotification(errorNotification);
            }
        } catch (err) {
            errorNotification.textContent = 'Network error. Please try again.';
            showNotification(errorNotification);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Update Company';
        }
    });

    function showNotification(n) {
        successNotification.style.display = 'none';
        errorNotification.style.display = 'none';
        n.style.display = 'flex';
        setTimeout(() => { n.style.display = 'none'; }, 5000);
    }

    loadCompany();
});