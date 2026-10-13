let currentPage = 1;
let currentFilters = {};

window.addEventListener('load', function () {
    loadTaxRates();
    loadTaxRegimes();
    loadCustomerTypes();
    
    // Event listeners
    document.getElementById('addNewBtn').addEventListener('click', openAddModal);
    document.getElementById('closeModal').addEventListener('click', closeModal);
    document.getElementById('cancelBtn').addEventListener('click', closeModal);
    document.getElementById('taxRateForm').addEventListener('submit', handleFormSubmit);
    
    // Filter event listeners
    document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 500));
    document.getElementById('taxTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('transactionTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    
    // Modal close on outside click
    document.getElementById('taxRateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
});

function loadTaxRates(page = 1) {
    currentPage = page;
    const loadingSpinner = document.getElementById('loadingSpinner');
    const tableBody = document.getElementById('tableBody');
    
    loadingSpinner.style.display = 'block';
    tableBody.innerHTML = '';
    
    let url = `../../../../server/api/inventory/tax-rates/tax-rates-list.php?page=${page}`;
    
    if (currentFilters.search) url += `&search=${encodeURIComponent(currentFilters.search)}`;
    if (currentFilters.tax_type) url += `&tax_type=${encodeURIComponent(currentFilters.tax_type)}`;
    if (currentFilters.transaction_type) url += `&transaction_type=${encodeURIComponent(currentFilters.transaction_type)}`;
    if (currentFilters.status !== undefined) url += `&status=${currentFilters.status}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            loadingSpinner.style.display = 'none';
            
            if (data.success && data.tax_rates) {
                displayTaxRates(data.tax_rates);
                displayPagination(data.pagination);
            } else {
                tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">No tax rates found</td></tr>';
            }
        })
        .catch(error => {
            loadingSpinner.style.display = 'none';
            console.error('Error:', error);
            tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px; color: red;">Error loading tax rates</td></tr>';
        });
}

function loadCustomerTypes() {
    fetch('../../../../server/api/inventory/customer-types/customer-types-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer_types) {
                const select = document.getElementById('customerTypeId');
                if (select) {
                    select.innerHTML = '<option value="">Select Customer Type</option>';
                    data.customer_types.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type.id;
                        option.textContent = type.type_name;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => console.error('Error loading customer types:', error));
}

function loadTaxRegimes() {
    fetch('../../../../server/api/inventory/tax-regimes/tax-regimes-list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tax_regimes) {
                const select = document.getElementById('taxRegimeId');
                if (select) {
                    // Set placeholder option
                    select.innerHTML = '<option value="">Select Tax Regime</option>';
                    
                    // Add regime options
                    data.tax_regimes.forEach(regime => {
                        const option = document.createElement('option');
                        option.value = regime.id;
                        option.textContent = regime.regime_name;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading tax regimes:', error);
            // Continue even if regimes fail to load
        });
}

function displayTaxRates(taxRates) {
    const tableBody = document.getElementById('tableBody');
    tableBody.innerHTML = '';
    
    taxRates.forEach(rate => {
        const row = document.createElement('tr');
        const statusBadge = rate.is_active ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-danger">Inactive</span>';
        const recordType = rate.record_type === 'System' ? 
            '<span class="badge badge-info">System</span>' : 
            '<span class="badge badge-warning">Custom</span>';
        const ratePercentage = parseFloat(rate.rate_percentage) || 0;
        
        row.innerHTML = `
            <td><strong>${rate.tax_name}</strong></td>
            <td>${rate.tax_type}</td>
            <td>${rate.transaction_type}</td>
            <td>${ratePercentage.toFixed(2)}%</td>
            <td>${rate.tax_authority}</td>
            <td>${recordType}</td>
            <td>${statusBadge}</td>
            <td>${rate.effective_from}</td>
            <td>
                <div class="action-buttons">
                    <button type="button" class="btn btn-sm btn-info" onclick="editTaxRate(${rate.id})">Edit</button>
                    ${rate.record_type === 'Custom' ? 
                        `<button type="button" class="btn btn-sm btn-danger" onclick="deleteTaxRate(${rate.id})">Delete</button>` : 
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
    
    // Previous button
    if (pagination.current_page > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'pagination-btn';
        prevBtn.textContent = 'Previous';
        prevBtn.onclick = () => loadTaxRates(pagination.current_page - 1);
        nav.appendChild(prevBtn);
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        const btn = document.createElement('button');
        btn.className = 'pagination-btn' + (i === pagination.current_page ? ' active' : '');
        btn.textContent = i;
        btn.onclick = () => loadTaxRates(i);
        nav.appendChild(btn);
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'pagination-btn';
        nextBtn.textContent = 'Next';
        nextBtn.onclick = () => loadTaxRates(pagination.current_page + 1);
        nav.appendChild(nextBtn);
    }
    
    container.appendChild(nav);
}

function applyFilters() {
    currentFilters = {
        search: document.getElementById('searchInput').value,
        tax_type: document.getElementById('taxTypeFilter').value,
        transaction_type: document.getElementById('transactionTypeFilter').value,
        status: document.getElementById('statusFilter').value || undefined
    };
    loadTaxRates(1);
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('taxTypeFilter').value = '';
    document.getElementById('transactionTypeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    currentFilters = {};
    loadTaxRates(1);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Tax Rate';
    document.getElementById('taxRateForm').reset();
    document.getElementById('taxRateForm').removeAttribute('data-id');
    document.getElementById('effectiveFrom').valueAsDate = new Date();
    document.getElementById('taxRateModal').style.display = 'flex';
}

function editTaxRate(taxRateId) {
    fetch(`../../../../server/api/inventory/tax-rates/tax-rates-get.php?id=${taxRateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rate = data.tax_rate;
                
                // Check if it's a system record
                if (rate.is_system_record) {
                    alert('Cannot edit system tax rates');
                    return;
                }
                
                document.getElementById('modalTitle').textContent = 'Edit Tax Rate';
                document.getElementById('taxRateForm').dataset.id = taxRateId;
                
                // Populate form fields
                document.getElementById('taxAuthority').value = rate.tax_authority || '';
                document.getElementById('taxType').value = rate.tax_type || '';
                document.getElementById('transactionType').value = rate.transaction_type || '';
                document.getElementById('taxName').value = rate.tax_name || '';
                document.getElementById('legalSection').value = rate.legal_section || '';
                document.getElementById('financeActYear').value = rate.finance_act_year || new Date().getFullYear();
                document.getElementById('ratePercentage').value = rate.rate_percentage || '';
                document.getElementById('thresholdMin').value = rate.threshold_min || '';
                document.getElementById('thresholdMax').value = rate.threshold_max || '';
                document.getElementById('customerTypeId').value = rate.customer_type_id || '';
                document.getElementById('partyType').value = rate.party_type || '';
                document.getElementById('deductedBy').value = rate.deducted_by || 'seller';
                document.getElementById('currency').value = rate.currency || 'PKR';
                document.getElementById('effectiveFrom').value = rate.effective_from || '';
                document.getElementById('effectiveTo').value = rate.effective_to || '';
                document.getElementById('description').value = rate.description || '';
                document.getElementById('taxRegimeId').value = rate.tax_regime_id || '';
                
                document.querySelector('input[name="is_filer"]').checked = rate.is_filer == 1;
                document.querySelector('input[name="is_adjustable"]').checked = rate.is_adjustable == 1;
                document.querySelector('input[name="is_refundable"]').checked = rate.is_refundable == 1;
                document.querySelector('input[name="is_final_tax"]').checked = rate.is_final_tax == 1;
                document.querySelector('input[name="is_active"]').checked = rate.is_active == 1;
                
                document.getElementById('taxRateModal').style.display = 'flex';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading tax rate');
        });
}

function deleteTaxRate(taxRateId) {
    if (!confirm('Are you sure you want to delete this tax rate?')) return;
    
    fetch(`../../../../server/api/inventory/tax-rates/tax-rates-delete.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${taxRateId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Tax rate deleted successfully');
            loadTaxRates(currentPage);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting tax rate');
    });
}

function handleFormSubmit(e) {
    e.preventDefault();
    
    const taxRateId = document.getElementById('taxRateForm').dataset.id;
    const formData = new FormData(document.getElementById('taxRateForm'));
    
    // Ensure checkboxes are properly sent even when unchecked
    if (!document.querySelector('input[name="is_filer"]').checked) {
        formData.set('is_filer', '0');
    } else {
        formData.set('is_filer', '1');
    }
    
    if (!document.querySelector('input[name="is_adjustable"]').checked) {
        formData.set('is_adjustable', '0');
    } else {
        formData.set('is_adjustable', '1');
    }
    
    if (!document.querySelector('input[name="is_refundable"]').checked) {
        formData.set('is_refundable', '0');
    } else {
        formData.set('is_refundable', '1');
    }
    
    if (!document.querySelector('input[name="is_final_tax"]').checked) {
        formData.set('is_final_tax', '0');
    } else {
        formData.set('is_final_tax', '1');
    }
    
    if (!document.querySelector('input[name="is_active"]').checked) {
        formData.set('is_active', '0');
    } else {
        formData.set('is_active', '1');
    }
    
    const endpoint = taxRateId ? 
        `../../../../server/api/inventory/tax-rates/tax-rates-edit.php` :
        `../../../../server/api/inventory/tax-rates/tax-rates-add.php`;
    
    if (taxRateId) {
        formData.append('id', taxRateId);
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
            loadTaxRates(currentPage);
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
    document.getElementById('taxRateModal').style.display = 'none';
    document.getElementById('taxRateForm').reset();
    document.getElementById('taxRateForm').removeAttribute('data-id');
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
