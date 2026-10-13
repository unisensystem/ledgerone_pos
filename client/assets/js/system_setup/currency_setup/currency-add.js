// Load currencies from API
const currencyName = document.getElementById('currencyName');
const currencySymbol = document.getElementById('currencySymbol');
const currencyDropdown = document.getElementById('currencyDropdown');
const currencyId = document.getElementById('currencyId');
let currenciesData = [];

async function loadCurrencies() {
    try {
        const response = await fetch('../../../../server/api/system_setup/currency_setup/currencies.php');
        const result = await response.json();
        
        if (result.success) {
            currenciesData = result.data;
        } else {
            console.error('API Error:', result.message);
        }
    } catch (error) {
        console.error('Failed to load currencies:', error);
    }
}

// Filter and display currencies
function filterCurrencies(searchTerm) {
    const filtered = currenciesData.filter(currency => 
        currency.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        currency.code.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    currencyDropdown.innerHTML = '';
    
    if (filtered.length > 0 && searchTerm) {
        filtered.forEach(currency => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = `${currency.name} (${currency.code})`;
            item.onclick = () => selectCurrency(currency);
            currencyDropdown.appendChild(item);
        });
        currencyDropdown.style.display = 'block';
    } else {
        currencyDropdown.style.display = 'none';
    }
}

// Select currency
function selectCurrency(currency) {
    currencyName.value = `${currency.name} (${currency.code})`;
    currencyId.value = currency.id;
    currencySymbol.value = currency.symbol;
    currencyDropdown.style.display = 'none';
}

// Search functionality
currencyName.addEventListener('input', function() {
    filterCurrencies(this.value);
});

currencyName.addEventListener('focus', function() {
    if (this.value) filterCurrencies(this.value);
});

// Hide dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.form-group')) {
        currencyDropdown.style.display = 'none';
    }
});

// Check if this is the first currency for tenant
async function checkBaseCurrency() {
    try {
        const response = await fetch('../../../../server/api/system_setup/currency_setup/check-base-currency.php');
        const result = await response.json();
        
        if (result.success) {
            const checkbox = document.getElementById('isBaseCurrency');
            
            if (result.is_first_currency) {
                // First currency - must be base currency
                checkbox.checked = true;
                checkbox.disabled = true;
                document.querySelector('.checkbox-label').style.opacity = '0.6';
            } else if (result.has_base_currency) {
                // Base currency already exists - cannot set another
                checkbox.checked = false;
                checkbox.disabled = true;
                document.querySelector('.checkbox-label').style.opacity = '0.6';
            }
        }
    } catch (error) {
        console.error('Failed to check base currency status');
    }
}

// Load currencies and check base currency on page load
loadCurrencies();
checkBaseCurrency();

// Form validation
const currencyForm = document.getElementById('currencyForm');
const currencyNameError = document.getElementById('currencyNameError');
const baseCurrencyError = document.getElementById('baseCurrencyError');

currencyForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    let isValid = true;

    // Validate currency name
    if (!currencyId.value) {
        currencyNameError.textContent = 'Please select a currency';
        isValid = false;
    } else {
        currencyNameError.textContent = '';
    }

    // Validate base currency checkbox only if it's not disabled (i.e., first currency case)
    const baseCurrencyCheckbox = document.getElementById('isBaseCurrency');
    if (!baseCurrencyCheckbox.disabled && !baseCurrencyCheckbox.checked) {
        baseCurrencyError.textContent = 'You must confirm this is the base currency';
        isValid = false;
    } else {
        baseCurrencyError.textContent = '';
    }

    if (isValid) {
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
        try {
            const response = await fetch('../../../../server/api/system_setup/currency_setup/currency-add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    currency_id: currencyId.value,
                    is_base_currency: document.getElementById('isBaseCurrency').checked
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Currency saved successfully!');
                currencyForm.reset();
                currencySymbol.value = '';
                window.location.href = 'currency-list.php';
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Network error occurred');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Currency';
        }
    }
});

// Reset button functionality
document.getElementById('resetBtn').addEventListener('click', function () {
    currencyName.value = '';
    currencyId.value = '';
    currencySymbol.value = '';
    currencyDropdown.style.display = 'none';
    currencyNameError.textContent = '';
    baseCurrencyError.textContent = '';
    checkBaseCurrency();
});