let companies = [];
let stats = {};
let pagination = {};
let currentPage = 1;
const itemsPerPage = 10;

// Sample data for demonstration (keeping for reference)
const sampleCompanies = [
    {
        id: 1,
        code: 'COMP-001',
        name: 'ABC Corporation',
        industry: 'Manufacturing',
        email: 'contact@abccorp.com',
        phone: '+1 (555) 123-4567',
        registrationNo: 'REG-123456789',
        status: 'active',
        createdDate: '2023-01-15'
    },
    {
        id: 2,
        code: 'COMP-002',
        name: 'XYZ Industries',
        industry: 'Technology',
        email: 'info@xyzind.com',
        phone: '+1 (555) 234-5678',
        registrationNo: 'REG-234567890',
        status: 'active',
        createdDate: '2023-02-20'
    },
    {
        id: 3,
        code: 'COMP-003',
        name: 'Global Energy Solutions',
        industry: 'Energy',
        email: 'support@globalenergy.com',
        phone: '+1 (555) 345-6789',
        registrationNo: 'REG-345678901',
        status: 'active',
        createdDate: '2023-03-10'
    },
    {
        id: 4,
        code: 'COMP-004',
        name: 'Metro Retail Group',
        industry: 'Retail',
        email: 'hello@metrogroup.com',
        phone: '+1 (555) 456-7890',
        registrationNo: 'REG-456789012',
        status: 'inactive',
        createdDate: '2023-04-05'
    },
    {
        id: 5,
        code: 'COMP-005',
        name: 'Innovate Tech Ltd',
        industry: 'Technology',
        email: 'contact@innovatetech.com',
        phone: '+1 (555) 567-8901',
        registrationNo: 'REG-567890123',
        status: 'active',
        createdDate: '2023-05-12'
    },
    {
        id: 6,
        code: 'COMP-006',
        name: 'Prime Manufacturing Co',
        industry: 'Manufacturing',
        email: 'info@primemfg.com',
        phone: '+1 (555) 678-9012',
        registrationNo: 'REG-678901234',
        status: 'active',
        createdDate: '2023-06-18'
    },
    {
        id: 7,
        code: 'COMP-007',
        name: 'Eco Power Systems',
        industry: 'Energy',
        email: 'support@ecopower.com',
        phone: '+1 (555) 789-0123',
        registrationNo: 'REG-789012345',
        status: 'inactive',
        createdDate: '2023-07-22'
    },
    {
        id: 8,
        code: 'COMP-008',
        name: 'City Retail Chain',
        industry: 'Retail',
        email: 'hello@cityretail.com',
        phone: '+1 (555) 890-1234',
        registrationNo: 'REG-890123456',
        status: 'active',
        createdDate: '2023-08-30'
    },
    {
        id: 9,
        code: 'COMP-009',
        name: 'NextGen Solutions',
        industry: 'Technology',
        email: 'contact@nextgensol.com',
        phone: '+1 (555) 901-2345',
        registrationNo: 'REG-901234567',
        status: 'active',
        createdDate: '2023-09-14'
    },
    {
        id: 10,
        code: 'COMP-010',
        name: 'United Manufacturing',
        industry: 'Manufacturing',
        email: 'info@unitedmfg.com',
        phone: '+1 (555) 012-3456',
        registrationNo: 'REG-012345678',
        status: 'active',
        createdDate: '2023-10-08'
    }
];

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

// Update stats cards
function updateStats() {
    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = stats.total || 0;
    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = stats.active || 0;
    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = stats.inactive || 0;
    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = stats.new_this_month || 0;
}

// Update pagination
function updatePagination() {
    const paginationInfo = document.querySelector('.pagination-info');
    const paginationPages = document.querySelector('.pagination-pages');
    const prevBtn = document.querySelector('.pagination-btn:first-child');
    const nextBtn = document.querySelector('.pagination-btn:last-child');

    // Update info text
    paginationInfo.textContent = `Showing ${pagination.showing_from}-${pagination.showing_to} of ${pagination.total_records} companies`;

    // Update page numbers
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

    // Update prev/next buttons
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === pagination.total_pages;
    prevBtn.onclick = () => currentPage > 1 && fetchCompanies(currentPage - 1);
    nextBtn.onclick = () => currentPage < pagination.total_pages && fetchCompanies(currentPage + 1);
}

// Populate the table with company data
function populateTable() {
    const tbody = document.getElementById('companiesTableBody');
    tbody.innerHTML = '';

    companies.forEach(company => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${company.company_code}</td>
            <td>
                <div style="font-weight: 500; color: var(--text-heading);">${company.company_name}</div>
                <div style="font-size: 12px; color: var(--text-subtext);">${company.email || 'N/A'}</div>
            </td>
            <td>${company.industry_type || 'N/A'}</td>
            <td>${company.phone || 'N/A'}</td>
            <td>${company.registration_number || 'N/A'}</td>
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

// Format date for display
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Open company form (placeholder function)
function openCompanyForm() {
    window.location.href = 'company-add.php';
}

// Edit company
async function editCompany(id) {
    try {
        const response = await fetch(`../../../../server/api/system_setup/company-profile/company-edit.php?id=${id}`);
        const result = await response.json();

        if (result.success) {
            const company = result.company;
            document.getElementById('edit_id').value = company.id;
            document.getElementById('edit_company_code').value = company.company_code;
            document.getElementById('edit_company_name').value = company.company_name;
            document.getElementById('edit_legal_name').value = company.legal_name || '';
            document.getElementById('edit_industry_type').value = company.industry_type || '';
            document.getElementById('edit_email').value = company.email || '';
            document.getElementById('edit_phone').value = company.phone || '';
            document.getElementById('edit_mobile_no').value = company.mobile_no || '';
            document.getElementById('edit_website').value = company.website || '';
            document.getElementById('edit_address').value = company.address || '';
            document.getElementById('edit_country_id').value = company.country_id || '';
            document.getElementById('edit_country').value = company.country || '';
            document.getElementById('edit_state').value = company.state || '';
            document.getElementById('edit_city').value = company.city || '';
            document.getElementById('edit_zipcode').value = company.zipcode || '';
            document.getElementById('edit_po_box_no').value = company.po_box_no || '';
            document.getElementById('edit_registration_number').value = company.registration_number || '';
            document.getElementById('edit_tax_identification_number').value = company.tax_identification_number || '';
            document.getElementById('edit_sales_tax_number').value = company.sales_tax_number || '';
            document.getElementById('edit_language_code').value = company.language_code || 'en';
            document.getElementById('edit_timezone').value = company.timezone || 'Asia/Karachi';
            document.getElementById('edit_inventory_valuation_method').value = company.inventory_valuation_method || 'FIFO';
            document.getElementById('edit_is_active').value = company.is_active;

            // Handle logo preview
            const logoPreview = document.getElementById('edit-logo-preview');
            if (company.logo_url) {
                logoPreview.innerHTML = `
                    <img src="../../../assets/uploads/company_logo/${company.logo_url}" alt="Company Logo">
                    <button type="button" class="logo-remove-btn" onclick="removeEditLogo()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;
            } else {
                logoPreview.innerHTML = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
            }

            document.getElementById('editModal').style.display = 'block';
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred while loading company data.');
    }
}

// Close edit modal
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Remove logo from edit modal
function removeEditLogo() {
    document.getElementById('edit-logo-preview').innerHTML = '<span style="color: var(--text-subtext); font-size: 12px;">No logo selected</span>';
    document.getElementById('edit_logo_file').value = '';
}

// Handle edit form submission
document.getElementById('editForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const logoFile = document.getElementById('edit_logo_file').files[0];
    
    // Validate logo file if selected
    if (logoFile) {
        const allowedTypes = ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'application/pdf', 'image/webp', 'image/avif'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(logoFile.type)) {
            alert('Invalid file type. Allowed: PNG, JPG, JPEG, GIF, PDF, WEBP, AVIF');
            return;
        }
        
        if (logoFile.size > maxSize) {
            alert('File size must be less than 5MB');
            return;
        }
    }

    const formData = new FormData();
    formData.append('id', document.getElementById('edit_id').value);
    formData.append('company_name', document.getElementById('edit_company_name').value);
    formData.append('legal_name', document.getElementById('edit_legal_name').value);
    formData.append('industry_type', document.getElementById('edit_industry_type').value);
    formData.append('email', document.getElementById('edit_email').value);
    formData.append('phone', document.getElementById('edit_phone').value);
    formData.append('mobile_no', document.getElementById('edit_mobile_no').value);
    formData.append('website', document.getElementById('edit_website').value);
    formData.append('address', document.getElementById('edit_address').value);
    formData.append('country_id', document.getElementById('edit_country_id').value);
    formData.append('country', document.getElementById('edit_country').value);
    formData.append('state', document.getElementById('edit_state').value);
    formData.append('city', document.getElementById('edit_city').value);
    formData.append('zipcode', document.getElementById('edit_zipcode').value);
    formData.append('po_box_no', document.getElementById('edit_po_box_no').value);
    formData.append('registration_number', document.getElementById('edit_registration_number').value);
    formData.append('tax_identification_number', document.getElementById('edit_tax_identification_number').value);
    formData.append('sales_tax_number', document.getElementById('edit_sales_tax_number').value);
    formData.append('language_code', document.getElementById('edit_language_code').value);
    formData.append('timezone', document.getElementById('edit_timezone').value);
    formData.append('inventory_valuation_method', document.getElementById('edit_inventory_valuation_method').value);
    formData.append('is_active', document.getElementById('edit_is_active').value === '1');
    
    if (logoFile) {
        formData.append('logo_file', logoFile);
    }

    try {
        const response = await fetch('../../../../server/api/system_setup/company-profile/company-edit.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

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

let deleteCompanyId = null;

// Load countries for dropdown
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

// Delete company
function deleteCompany(id) {
    const company = companies.find(c => c.id == id);
    if (company) {
        deleteCompanyId = id;
        document.getElementById('delete_company_name').textContent = company.company_name;
        document.getElementById('deleteModal').style.display = 'block';
    }
}

// Close delete modal
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteCompanyId = null;
}

// Confirm delete
async function confirmDelete() {
    if (!deleteCompanyId) return;

    try {
        const response = await fetch('../../../../server/api/system_setup/company-profile/company-delete.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
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

// Close modal when clicking outside
window.onclick = function (event) {
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// Initialize the table when the page loads
document.addEventListener('DOMContentLoaded', function () {
    loadCountries();
    fetchCompanies();

    // Add search functionality
    document.getElementById('searchInput').addEventListener('input', function (e) {
        const searchTerm = e.target.value.toLowerCase();
        // In a real application, this would filter the table
        console.log('Searching for:', searchTerm);
    });

    // Add filter functionality
    document.getElementById('statusFilter').addEventListener('change', function (e) {
        console.log('Status filter changed to:', e.target.value);
    });

    // Logo upload preview for edit modal
    document.getElementById('edit_logo_file').addEventListener('change', function() {
        const file = this.files[0];
        const logoPreview = document.getElementById('edit-logo-preview');
        
        if (file) {
            const reader = new FileReader();
            reader.addEventListener('load', function() {
                logoPreview.innerHTML = `
                    <img src="${reader.result}" alt="Company Logo">
                    <button type="button" class="logo-remove-btn" onclick="removeEditLogo()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                `;
            });
            reader.readAsDataURL(file);
        }
    });
});