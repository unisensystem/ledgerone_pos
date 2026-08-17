let currencies = [];
let currenciesData = [];
const tableBody = document.getElementById('currencyTableBody');

// Load all available currencies for dropdown
async function loadAllCurrencies() {
    try {
        const response = await fetch('../../../../server/api/system_setup/currency_setup/currencies.php');
        const result = await response.json();
        if (result.success) {
            currenciesData = result.data;
        }
    } catch (error) {
        console.error('Failed to load all currencies');
    }
}

// Load currencies from API
async function loadCurrencies() {
    try {
        const response = await fetch('../../../../server/api/system_setup/currency_setup/currency-list.php');
        const result = await response.json();
        
        if (result.success) {
            currencies = result.data;
            console.log('Currency data:', currencies);
            renderCurrencies(currencies);
            updatePaginationInfo(currencies.length);
        }
    } catch (error) {
        console.error('Failed to load currencies');
    }
}

// Update pagination info
function updatePaginationInfo(count) {
    document.getElementById('paginationInfo').textContent = 
        `Showing 1-${count} of ${count} currencies`;
}

function renderCurrencies(currencyList) {
    tableBody.innerHTML = '';

    if (currencyList.length === 0) {
        tableBody.innerHTML = `
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon">💱</div>
                                <h3>No currencies found</h3>
                                <p>Try adjusting your search or add a new currency</p>
                            </div>
                        </td>
                    </tr>
                `;
        return;
    }

    currencyList.forEach(currency => {
        const row = document.createElement('tr');
        row.innerHTML = `
                    <td><strong>${currency.code}</strong></td>
                    <td>${currency.name}</td>
                    <td class="symbol-cell"></td>
                    <td>${parseFloat(currency.exchange_rate).toFixed(4)}</td>
                    <td>
                        <span class="status-badge ${currency.is_active ? 'status-active' : 'status-inactive'}">
                            ${currency.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        ${currency.is_base_currency ?
                '<span class="base-currency">★ Base Currency</span>' :
                '-'}
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-icon btn-edit" title="Edit">
                                ✏️
                            </button>
                            <button class="btn-icon btn-delete" title="Delete">
                                🗑️
                            </button>
                        </div>
                    </td>
                `;
        row.querySelector('.symbol-cell').textContent = currency.symbol || '';
        tableBody.appendChild(row);
    });
}

// Load currencies on page load
loadCurrencies();
loadAllCurrencies();

// Search functionality
const searchInput = document.getElementById('searchInput');

searchInput.addEventListener('input', function () {
    const searchTerm = this.value.toLowerCase();
    const filteredCurrencies = currencies.filter(currency =>
        currency.code.toLowerCase().includes(searchTerm) ||
        currency.name.toLowerCase().includes(searchTerm) ||
        currency.symbol.toLowerCase().includes(searchTerm)
    );
    renderCurrencies(filteredCurrencies);
    updatePaginationInfo(filteredCurrencies.length);
});

// Add currency button
document.getElementById('addCurrencyBtn').addEventListener('click', function () {
    window.location.href = 'currency-add.php';
});

// Pagination controls (simplified for this example)
document.getElementById('prevPage').addEventListener('click', function () {
    alert('Previous page');
});

document.getElementById('nextPage').addEventListener('click', function () {
    alert('Next page');
});

// Modal elements
const editModal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');
const editCurrencyName = document.getElementById('editCurrencyName');
const editCurrencySymbol = document.getElementById('editCurrencySymbol');
const editStatus = document.getElementById('editStatus');
const deleteModal = document.getElementById('deleteModal');
const deleteCurrencyName = document.getElementById('deleteCurrencyName');
let currentEditId = null;
let currentDeleteId = null;

// Update currency symbol when currency is selected in edit modal
editCurrencyName.addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    editCurrencySymbol.value = selectedOption.dataset.symbol || '';
});

// Add event listeners to action buttons
document.addEventListener('click', async function (e) {
    if (e.target.closest('.btn-edit')) {
        const row = e.target.closest('tr');
        const currencyCode = row.cells[0].textContent.trim();
        const currency = currencies.find(c => c.code === currencyCode);
        
        if (currency) {
            currentEditId = currency.id;
            
            // Populate currency dropdown
            editCurrencyName.innerHTML = '<option value="">Select Currency</option>';
            currenciesData.forEach(curr => {
                const option = document.createElement('option');
                option.value = curr.id;
                option.textContent = `${curr.name} (${curr.code})`;
                option.dataset.symbol = curr.symbol;
                if (curr.id == currency.currency_id) {
                    option.selected = true;
                    editCurrencySymbol.value = curr.symbol;
                }
                editCurrencyName.appendChild(option);
            });
            
            editStatus.value = currency.is_active ? '1' : '0';
            editModal.style.display = 'block';
        }
    }

    if (e.target.closest('.btn-delete')) {
        const row = e.target.closest('tr');
        const currencyCode = row.cells[0].textContent.trim();
        const currencyName = row.cells[1].textContent.trim();
        const currency = currencies.find(c => c.code === currencyCode);
        
        if (currency) {
            currentDeleteId = currency.id;
            deleteCurrencyName.textContent = `${currencyName} (${currencyCode})`;
            deleteModal.style.display = 'block';
        }
    }

    // Close modals
    if (e.target.classList.contains('close') || e.target.id === 'editCancelBtn') {
        editModal.style.display = 'none';
    }
    
    if (e.target.classList.contains('close') || e.target.id === 'deleteCancelBtn') {
        deleteModal.style.display = 'none';
    }
    
    if (e.target.id === 'deleteConfirmBtn') {
        try {
            const response = await fetch('../../../../server/api/system_setup/currency_setup/currency-delete.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: currentDeleteId })
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Currency deleted successfully!');
                deleteModal.style.display = 'none';
                loadCurrencies();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Network error occurred');
        }
    }
});

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === editModal) {
        editModal.style.display = 'none';
    }
    if (e.target === deleteModal) {
        deleteModal.style.display = 'none';
    }
});

// Edit form submission
editForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    try {
        const response = await fetch('../../../../server/api/system_setup/currency_setup/currency-edit.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentEditId,
                currency_id: editCurrencyName.value,
                is_active: editStatus.value
            })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Currency updated successfully!');
            editModal.style.display = 'none';
            loadCurrencies();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Network error occurred');
    }
});