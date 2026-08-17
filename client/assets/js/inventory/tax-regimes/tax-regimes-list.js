let currentPage = 1;
let currentFilters = {};

const TAX_BASE_OPTIONS = {
    item: [
        { value: 'trade_price',  label: 'Trade Price' },
        { value: 'mrp',          label: 'MRP' },
        { value: 'import_value', label: 'Import Value' },
        { value: 'turnover',     label: 'Turnover' },
    ],
    invoice: [
        { value: 'total_bill',           label: 'Total Bill' },
        { value: 'value_excl_sales_tax', label: 'Value Excluding Sales Tax (Total Bill - Discount Amt)' },
        { value: 'net_amount',           label: 'Net Amount' },
        { value: 'remaining_balance',    label: 'Remaining Balance' },
    ]
};

function updateTaxBaseOptions(level, selectedValue) {
    const select = document.getElementById('taxBase');
    const options = TAX_BASE_OPTIONS[level] || TAX_BASE_OPTIONS.item;
    select.innerHTML = '<option value="">Select Tax Base</option>';
    options.forEach(opt => {
        const el = document.createElement('option');
        el.value = opt.value;
        el.textContent = opt.label;
        if (selectedValue && opt.value === selectedValue) el.selected = true;
        select.appendChild(el);
    });
}

function loadCountries() {
    fetch('../../../../server/api/inventory/countries/countries-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.countries) {
                const countrySelect = document.getElementById('countryId');
                const countryFilter = document.getElementById('countryFilter');
                data.countries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.country_name;
                    countrySelect.appendChild(option);
                    
                    const filterOption = document.createElement('option');
                    filterOption.value = country.id;
                    filterOption.textContent = country.country_name;
                    countryFilter.appendChild(filterOption);
                });
            }
        })
        .catch(error => console.error('Error loading countries:', error));
}

window.addEventListener('load', function () {
    loadCountries();
    loadTaxRegimes();
    
    document.getElementById('addNewBtn').addEventListener('click', openAddModal);
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelBtn').addEventListener('click', closeModal);
    document.getElementById('taxRegimeForm').addEventListener('submit', handleFormSubmit);
    
    document.getElementById('applicationLevel').addEventListener('change', function() {
        updateTaxBaseOptions(this.value);
    });

    document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));
    document.getElementById('countryFilter').addEventListener('change', applyFilters);
    document.getElementById('taxAuthorityFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    
    document.getElementById('taxRegimeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
});

function loadTaxRegimes(page = 1) {
    currentPage = page;
    const loadingSpinner = document.getElementById('loadingSpinner');
    const tableBody = document.getElementById('tableBody');
    
    loadingSpinner.style.display = 'block';
    tableBody.innerHTML = '';
    
    let url = `../../../../server/api/inventory/tax-regimes/tax-regimes-list.php?page=${page}`;
    
    if (currentFilters.search) url += `&search=${encodeURIComponent(currentFilters.search)}`;
    if (currentFilters.country_id) url += `&country_id=${encodeURIComponent(currentFilters.country_id)}`;
    if (currentFilters.tax_authority) url += `&tax_authority=${encodeURIComponent(currentFilters.tax_authority)}`;
    if (currentFilters.status !== undefined) url += `&status=${currentFilters.status}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            loadingSpinner.style.display = 'none';
            
            if (data.success && data.tax_regimes) {
                displayTaxRegimes(data.tax_regimes);
                displayPagination(data.pagination);
            } else {
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">No tax regimes found</td></tr>';
            }
        })
        .catch(error => {
            loadingSpinner.style.display = 'none';
            console.error('Error:', error);
            tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px; color: red;">Error loading tax regimes</td></tr>';
        });
}

function displayTaxRegimes(taxRegimes) {
    const tableBody = document.getElementById('tableBody');
    tableBody.innerHTML = '';
    
    taxRegimes.forEach(regime => {
        const row = document.createElement('tr');
        const statusBadge = regime.is_active ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-danger">Inactive</span>';
        const recordType = regime.record_type === 'System' ? 
            '<span class="badge badge-info">System</span>' : 
            '<span class="badge badge-warning">Custom</span>';
        
        row.innerHTML = `
            <td><strong>${regime.regime_name}</strong></td>
            <td>${regime.regime_code}</td>
            <td>${regime.country_name || 'N/A'}</td>
            <td>${regime.tax_authority}</td>
            <td>${regime.tax_base}</td>
            <td>${regime.applies_at_stage}</td>
            <td>${statusBadge}</td>
            <td>${regime.effective_from}</td>
            <td>
                <div class="action-buttons">
                    <button type="button" class="btn btn-sm btn-info" onclick="editTaxRegime(${regime.id})">Edit</button>
                    ${regime.record_type === 'Custom' ? 
                        `<button type="button" class="btn btn-sm btn-danger" onclick="deleteTaxRegime(${regime.id})">Delete</button>` : 
                        `<button type="button" class="btn btn-sm btn-secondary" disabled title="System records cannot be deleted">Delete</button>`
                    }
                </div>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

function displayPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    container.innerHTML = '';
    
    if (pagination.total_pages <= 1) return;
    
    const nav = document.createElement('nav');
    nav.className = 'pagination';
    
    if (pagination.current_page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.textContent = 'Previous';
        prevBtn.onclick = () => loadTaxRegimes(pagination.current_page - 1);
        nav.appendChild(prevBtn);
    }
    
    for (let i = 1; i <= pagination.total_pages; i++) {
        const btn = document.createElement('button');
        btn.className = 'pagination-btn' + (i === pagination.current_page ? ' active' : '');
        btn.textContent = i;
        btn.onclick = () => loadTaxRegimes(i);
        nav.appendChild(btn);
    }
    
    if (pagination.current_page < pagination.total_pages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.textContent = 'Next';
        nextBtn.onclick = () => loadTaxRegimes(pagination.current_page + 1);
        nav.appendChild(nextBtn);
    }
    
    container.appendChild(nav);
}

function applyFilters() {
    currentFilters = {
        search: document.getElementById('searchInput').value,
        country_id: document.getElementById('countryFilter').value,
        tax_authority: document.getElementById('taxAuthorityFilter').value,
        status: document.getElementById('statusFilter').value || undefined
    };
    loadTaxRegimes(1);
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('countryFilter').value = '';
    document.getElementById('taxAuthorityFilter').value = '';
    document.getElementById('statusFilter').value = '';
    currentFilters = {};
    loadTaxRegimes(1);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Tax Regime';
    document.getElementById('taxRegimeForm').reset();
    document.getElementById('taxRegimeForm').removeAttribute('data-id');
    document.getElementById('effectiveFrom').valueAsDate = new Date();
    document.getElementById('countryId').value = '';
    document.getElementById('applicationLevel').value = 'item';
    updateTaxBaseOptions('item');
    document.getElementById('taxRegimeModal').style.display = 'flex';
}

function editTaxRegime(regimeId) {
    fetch(`../../../../server/api/inventory/tax-regimes/tax-regimes-get.php?id=${regimeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const regime = data.tax_regime;
                
                if (regime.is_system_record) {
                    alert('Cannot edit system tax regimes');
                    return;
                }
                
                document.getElementById('modalTitle').textContent = 'Edit Tax Regime';
                document.getElementById('taxRegimeForm').dataset.id = regimeId;
                
                document.getElementById('regimeName').value = regime.regime_name || '';
                document.getElementById('regimeCode').value = regime.regime_code || '';
                document.getElementById('countryId').value = regime.country_id || '';
                document.getElementById('taxAuthority').value = regime.tax_authority || '';
                const appLevel = regime.application_level || 'item';
                document.getElementById('applicationLevel').value = appLevel;
                updateTaxBaseOptions(appLevel, regime.tax_base || '');
                document.getElementById('legalReference').value = regime.legal_reference || '';
                document.getElementById('financeActYear').value = regime.finance_act_year || new Date().getFullYear();
                document.getElementById('formulaTemplate').value = regime.formula_template || '';
                document.getElementById('appliesAtStage').value = regime.applies_at_stage || 'all';
                document.getElementById('effectiveFrom').value = regime.effective_from || '';
                document.getElementById('effectiveTo').value = regime.effective_to || '';
                document.getElementById('description').value = regime.description || '';
                
                document.querySelector('input[name="is_tax_inclusive"]').checked = regime.is_tax_inclusive == 1;
                document.querySelector('input[name="is_single_stage"]').checked = regime.is_single_stage == 1;
                document.querySelector('input[name="downstream_exempt"]').checked = regime.downstream_exempt == 1;
                document.querySelector('input[name="is_adjustable"]').checked = regime.is_adjustable == 1;
                document.querySelector('input[name="is_refundable"]').checked = regime.is_refundable == 1;
                document.querySelector('input[name="is_final_tax"]').checked = regime.is_final_tax == 1;
                document.querySelector('input[name="is_active"]').checked = regime.is_active == 1;
                
                document.getElementById('taxRegimeModal').style.display = 'flex';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading tax regime');
        });
}

function deleteTaxRegime(regimeId) {
    if (!confirm('Are you sure you want to delete this tax regime?')) return;
    
    fetch(`../../../../server/api/inventory/tax-regimes/tax-regimes-delete.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${regimeId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tax regime deleted successfully');
            loadTaxRegimes(currentPage);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting tax regime');
    });
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const regimeId = document.getElementById('taxRegimeForm').dataset.id;
    const formData = new FormData(document.getElementById('taxRegimeForm'));
    
    const checkboxes = ['is_tax_inclusive', 'is_single_stage', 'downstream_exempt', 'is_adjustable', 'is_refundable', 'is_final_tax', 'is_active'];
    checkboxes.forEach(checkbox => {
        formData.set(checkbox, document.querySelector(`input[name="${checkbox}"]`).checked ? '1' : '0');
    });
    
    const endpoint = regimeId ? 
        `../../../../server/api/inventory/tax-regimes/tax-regimes-edit.php` :
        `../../../../server/api/inventory/tax-regimes/tax-regimes-add.php`;
    
    if (regimeId) {
        formData.append('id', regimeId);
    }
    
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal();
            loadTaxRegimes(currentPage);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving');
    });
}

function closeModal() {
    document.getElementById('taxRegimeModal').style.display = 'none';
    document.getElementById('taxRegimeForm').reset();
    document.getElementById('taxRegimeForm').removeAttribute('data-id');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
