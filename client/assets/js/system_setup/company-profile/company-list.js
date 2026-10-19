let companies = [];
let stats = {};
let pagination = {};
let currentPage = 1;
const itemsPerPage = 10;
let removeLogoFlag = false;
let deleteCompanyId = null;

const EDIT_API = '../../../../server/api/system_setup/company-profile/company-edit.php';
const NO_LOGO = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
const BUSINESS_TYPES = {
    restaurant: '🍔 Restaurant',
    bakery: '🥐 Bakery',
    karyana: '🛒 Karyana Store',
    general: '🏪 General Store',
    medical: '💊 Medical Store',
    mall: '🏬 Mall / Retail'
};

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
}

// Fetch companies from API
async function fetchCompanies(page = 1) {
    try {
        const response = await fetch(`../../../../server/api/system_setup/company-profile/company-list.php?page=${page}&limit=${itemsPerPage}`);
        const result = await response.json();

        if (result.success) {
            companies = result.companies;
            stats = result.stats;
            pagination = result.pagination;
            currentPage = page;
            populateTable();
            updateStats();
            updatePagination();
        } else {
            console.error('Failed to fetch companies:', result.message);
        }
    } catch (error) {
        console.error('Error fetching companies:', error);
    }
}

function updateStats() {
    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = stats.total || 0;
    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = stats.active || 0;
    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = stats.inactive || 0;
    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = stats.new_this_month || 0;
}

function updatePagination() {
    const paginationInfo = document.querySelector('.pagination-info');
    const paginationPages = document.querySelector('.pagination-pages');
    const prevBtn = document.querySelector('.pagination-btn:first-child');
    const nextBtn = document.querySelector('.pagination-btn:last-child');

    paginationInfo.textContent = `Showing ${pagination.showing_from}-${pagination.showing_to} of ${pagination.total_records} companies`;

    paginationPages.innerHTML = '';
    const startPage = Math.max(1, currentPage - 1);
    const endPage = Math.min(pagination.total_pages, startPage + 2);

    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('div');
        pageBtn.className = `page-number ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.onclick = () => fetchCompanies(i);
        paginationPages.appendChild(pageBtn);
    }

    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === pagination.total_pages;
    prevBtn.onclick = () => currentPage > 1 && fetchCompanies(currentPage - 1);
    nextBtn.onclick = () => currentPage < pagination.total_pages && fetchCompanies(currentPage + 1);
}

function populateTable() {
    const tbody = document.getElementById('companiesTableBody');
    tbody.innerHTML = '';

    companies.forEach(company => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${esc(company.company_code)}</td>
            <td>
                <div style="font-weight: 500; color: var(--text-heading);">${esc(company.company_name)}</div>
                <div style="font-size: 12px; color: var(--text-subtext);">${esc(company.email || 'N/A')}</div>
            </td>
            <td>${esc(company.industry_type || 'N/A')}</td>
            <td>${BUSINESS_TYPES[company.business_type] || BUSINESS_TYPES.general}</td>
            <td>${esc(company.phone || 'N/A')}</td>
            <td>${esc(company.registration_number || 'N/A')}</td>
            <td>
                <div class="status-badge ${company.is_active == 1 ? 'status-active' : 'status-inactive'}">
                    <div class="status-dot"></div>
                    ${company.is_active == 1 ? 'Active' : 'Inactive'}
                </div>
            </td>
            <td>${formatDate(company.created_at)}</td>
            <td>
                <div class="actions-cell">
                    <button class="action-btn edit" onclick="editCompany(${company.id})" title="Edit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="action-btn delete" onclick="deleteCompany(${company.id})" title="Delete">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function openCompanyForm() {
    window.location.href = 'company-add.php';
}

// ---------- Edit ----------
const EDIT_TEXT_FIELDS = ['company_code', 'company_name', 'legal_name', 'industry_type', 'email', 'phone', 'mobile_no',
    'website', 'address', 'country', 'state', 'city', 'zipcode', 'po_box_no', 'registration_number',
    'tax_identification_number', 'sales_tax_number'];

function logoHtml(file) {
    if (!file) return NO_LOGO;
    const body = /\.pdf$/i.test(file)
        ? '<span style="font-size:12px">PDF</span>'
        : `<img src="../../../assets/uploads/company_logo/${encodeURI(file)}" alt="Company Logo">`;
    return body + '<button type="button" class="logo-remove-btn" onclick="removeEditLogo()">&times;</button>';
}

async function editCompany(id) {
    try {
        const response = await fetch(`${EDIT_API}?id=${id}`);
        const result = await response.json();

        if (result.success) {
            const c = result.company;
            removeLogoFlag = false;
            document.getElementById('edit_id').value = c.id;
            EDIT_TEXT_FIELDS.forEach(f => { document.getElementById('edit_' + f).value = c[f] || ''; });
            document.getElementById('edit_business_type').value = c.business_type || 'general';
            document.getElementById('edit_country_id').value = c.country_id || '';
            document.getElementById('edit_language_code').value = c.language_code || 'en';
            document.getElementById('edit_timezone').value = c.timezone || 'Asia/Karachi';
            document.getElementById('edit_inventory_valuation_method').value = c.inventory_valuation_method || 'FIFO';
            document.getElementById('edit_is_active').value = c.is_active;
            document.getElementById('edit_logo_file').value = '';
            document.getElementById('edit-logo-preview').innerHTML = logoHtml(c.logo_url);

            document.getElementById('editModal').style.display = 'block';
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred while loading company data.');
    }
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function removeEditLogo() {
    document.getElementById('edit-logo-preview').innerHTML = NO_LOGO;
    document.getElementById('edit_logo_file').value = '';
    removeLogoFlag = true;
}

document.getElementById('editForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const logoFile = document.getElementById('edit_logo_file').files[0];
    if (logoFile) {
        const allowedTypes = ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
        if (!allowedTypes.includes(logoFile.type)) {
            alert('Invalid file type. Allowed: PNG, JPG, JPEG, GIF, PDF, WEBP, AVIF');
            return;
        }
        if (logoFile.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB');
            return;
        }
    }

    const formData = new FormData();
    formData.append('id', document.getElementById('edit_id').value);
    EDIT_TEXT_FIELDS.filter(f => f !== 'company_code')
        .forEach(f => formData.append(f, document.getElementById('edit_' + f).value));
    ['business_type', 'country_id', 'language_code', 'timezone', 'inventory_valuation_method']
        .forEach(f => formData.append(f, document.getElementById('edit_' + f).value));
    formData.append('is_active', document.getElementById('edit_is_active').value === '1');
    formData.append('remove_logo', removeLogoFlag ? '1' : '0');
    if (logoFile) formData.append('logo_file', logoFile);

    try {
        let result = await (await fetch(EDIT_API, { method: 'POST', body: formData })).json();

        // near-duplicate name -> ask for confirmation
        if (!result.success && result.duplicate_warning) {
            if (!confirm(result.message)) return;
            formData.append('confirm_duplicate', '1');
            result = await (await fetch(EDIT_API, { method: 'POST', body: formData })).json();
        }

        if (result.success) {
            alert(result.message);
            closeEditModal();
            fetchCompanies(currentPage);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred while updating the company.');
    }
});

// ---------- Countries ----------
function loadCountries() {
    fetch('../../../../server/api/inventory/countries/countries-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.countries) {
                const countrySelect = document.getElementById('edit_country_id');
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

// ---------- Delete ----------
function deleteCompany(id) {
    const company = companies.find(c => c.id == id);
    if (company) {
        deleteCompanyId = id;
        document.getElementById('delete_company_name').textContent = company.company_name;
        document.getElementById('deleteModal').style.display = 'block';
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteCompanyId = null;
}

async function confirmDelete() {
    if (!deleteCompanyId) return;

    try {
        const response = await fetch('../../../../server/api/system_setup/company-profile/company-delete.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: deleteCompanyId })
        });
        const result = await response.json();

        if (result.success) {
            closeDeleteModal();
            fetchCompanies(currentPage);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred while deleting the company.');
    }
}

window.onclick = function (event) {
    if (event.target === document.getElementById('editModal')) closeEditModal();
    if (event.target === document.getElementById('deleteModal')) closeDeleteModal();
};

document.addEventListener('DOMContentLoaded', function () {
    loadCountries();
    fetchCompanies();

    // Client-side search on the current page
    document.getElementById('searchInput').addEventListener('input', function (e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('#companiesTableBody tr').forEach(tr => {
            tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });

    document.getElementById('edit_logo_file').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        removeLogoFlag = false;
        const reader = new FileReader();
        reader.addEventListener('load', function () {
            document.getElementById('edit-logo-preview').innerHTML =
                `<img src="${reader.result}" alt="Company Logo">
                 <button type="button" class="logo-remove-btn" onclick="removeEditLogo()">&times;</button>`;
        });
        reader.readAsDataURL(file);
    });
});