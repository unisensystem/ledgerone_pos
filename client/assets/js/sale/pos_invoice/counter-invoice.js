// Global state
let currentInvoice = {
    items: [],
    customer: null,
    branch: null,
    currency: null,
    project: null
};

let isSaving = false;
let justSaved = false; // true from a successful save until the cart is cleared
let lastSavedInvoiceId = null;
let preSelectedProduct = null;

let productsData = [];
let customersData = [];
let branchesData = [];
let companiesData = [];
let currenciesData = [];
let bankAccountsData = [];
let uomData = [];
let supplierMenData = [];
let projectsData = [];

// Default shortcuts
const defaultShortcuts = {
    product: 'F1',
    customer: 'F2',
    branch: 'F3',
    qty: 'F4',
    price: 'F6',
    quickPay: 'F9',
    save: 'F10',
    draft: 'F8',
    clear: 'F12',
    return: 'F7',
    nextField: 'Tab',
    prevField: 'Shift+Tab'
};

let shortcuts = { ...defaultShortcuts };
let isAddingProduct = false;

// Returns the price to use for a product based on the Price Setting (Trade/Wholesale/MRP), default MRP
function getEffectivePrice(product) {
    if (!product) return 0;
    const setting = localStorage.getItem('counterPriceSetting') || 'mrp';
    let price;
    if (setting === 'trade_price') {
        price = product.trade_price;
    } else if (setting === 'wholesale_price') {
        price = product.wholesale_price;
    } else {
        price = product.mrp;
    }
    const parsed = parseFloat(price) || 0;

    // Child product with no price of its own: fall back to the parent's price
    if (parsed <= 0 && product.parent_product_id) {
        const parent = productsData.find(p => p.id == product.parent_product_id);
        if (parent && parent !== product) return getEffectivePrice(parent);
    }
    return parsed;
}

// Manual print mode: ON = no auto print after save, a PRINT button is shown instead. OFF = auto print (default)
function isManualPrintMode() {
    return localStorage.getItem('manualPrintMode') === 'true';
}

function showPrintButton() {
    const btn = document.getElementById('printInvoiceBtn');
    if (btn) btn.style.display = '';
}

function hidePrintButton() {
    const btn = document.getElementById('printInvoiceBtn');
    if (btn) btn.style.display = 'none';
}

function printLastInvoice() {
    if (!lastSavedInvoiceId) {
        alert('No saved invoice to print!');
        return;
    }
    window.open(`thermal-print.php?id=${lastSavedInvoiceId}&autoprint=1`, '_blank');
    document.getElementById('quickProduct').focus();
}

// Load data on page load
document.addEventListener('DOMContentLoaded', async function() {
    await Promise.all([
        loadProducts(),
        loadCustomers(),
        loadCurrencies(),
        loadBankAccounts(),
        loadUOM(),
        loadSupplierMen(),
        loadProjects()
    ]);
    
    // Load branches and companies and wait for them to complete
    await loadBranches();
    await loadCompanies();
    
    loadShortcuts();

    document.getElementById('counterSelect')?.addEventListener('change', function() {
        if (this.value && currentInvoice.branch) {
            localStorage.setItem(`lastCounter_${currentInvoice.branch}`, this.value);
        }
    });

    updateDateTime();
    setInterval(updateDateTime, 1000);
    await loadNextInvoiceNo();
    
    // Load stock after branch is set
    await loadStockData();
    updateStockDisplay();
    setInterval(() => loadStockData(), 30000);
    
    document.getElementById('branchSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const branchOptions = document.getElementById('branchOptions');
        
        if (searchTerm.length === 0) {
            branchOptions.style.display = 'none';
            return;
        }
        
        const matches = branchesData.filter(b => 
            b.branch_name.toLowerCase().includes(searchTerm) ||
            b.branch_code.toLowerCase().includes(searchTerm)
        );
        
        if (matches.length === 0) {
            branchOptions.style.display = 'none';
        } else {
            branchOptions.innerHTML = '';
            matches.forEach(branch => {
                const option = document.createElement('div');
                option.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
                const branchText = branch.parent_branch_name 
                    ? `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type}) - Parent: ${branch.parent_branch_name}`
                    : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                option.textContent = branchText;
                option.dataset.value = branch.id;
                option.dataset.text = branchText;
                
                option.addEventListener('click', function() {
                    document.getElementById('branchSearch').value = this.dataset.text;
                    document.getElementById('branch').value = this.dataset.value;
                    currentInvoice.branch = parseInt(this.dataset.value);
                    localStorage.setItem('lastSelectedBranch', this.dataset.value);
                    branchOptions.style.display = 'none';
                    loadStockData();
                    loadCounters(parseInt(this.dataset.value));
                });

                branchOptions.appendChild(option);
            });
            branchOptions.style.display = 'block';
        }
    });

    document.getElementById('branchSearch').addEventListener('focus', function() {
        if (branchesData.length > 0) {
            const branchOptions = document.getElementById('branchOptions');
            branchOptions.innerHTML = '';
            branchesData.forEach(branch => {
                const option = document.createElement('div');
                option.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
                const branchText = branch.parent_branch_name
                    ? `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type}) - Parent: ${branch.parent_branch_name}`
                    : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                option.textContent = branchText;
                option.dataset.value = branch.id;
                option.dataset.text = branchText;

                option.addEventListener('click', function() {
                    document.getElementById('branchSearch').value = this.dataset.text;
                    document.getElementById('branch').value = this.dataset.value;
                    currentInvoice.branch = parseInt(this.dataset.value);
                    localStorage.setItem('lastSelectedBranch', this.dataset.value);
                    branchOptions.style.display = 'none';
                    loadStockData();
                    loadCounters(parseInt(this.dataset.value));
                });
                
                branchOptions.appendChild(option);
            });
            branchOptions.style.display = 'block';
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#branchSearch') && !e.target.closest('#branchOptions')) {
            document.getElementById('branchOptions').style.display = 'none';
        }
        if (!e.target.closest('#companySearch') && !e.target.closest('#companyOptions')) {
            document.getElementById('companyOptions').style.display = 'none';
        }
    });
    
    document.getElementById('companySearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const companyOptions = document.getElementById('companyOptions');
        
        if (searchTerm.length === 0) {
            companyOptions.style.display = 'none';
            return;
        }
        
        const matches = companiesData.filter(c => 
            c.company_name.toLowerCase().includes(searchTerm) ||
            (c.legal_name && c.legal_name.toLowerCase().includes(searchTerm))
        );
        
        if (matches.length === 0) {
            companyOptions.style.display = 'none';
        } else {
            companyOptions.innerHTML = '';
            matches.forEach(company => {
                const option = document.createElement('div');
                option.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
                option.textContent = company.company_name;
                option.dataset.value = company.id;
                option.dataset.text = company.company_name;
                
                option.addEventListener('click', function() {
                    document.getElementById('companySearch').value = this.dataset.text;
                    document.getElementById('company').value = this.dataset.value;
                    currentInvoice.company = parseInt(this.dataset.value);
                    localStorage.setItem('lastSelectedCompany', this.dataset.value);
                    companyOptions.style.display = 'none';
                });
                
                companyOptions.appendChild(option);
            });
            companyOptions.style.display = 'block';
        }
    });
    
    document.getElementById('companySearch').addEventListener('focus', function() {
        if (companiesData.length > 0) {
            const companyOptions = document.getElementById('companyOptions');
            companyOptions.innerHTML = '';
            companiesData.forEach(company => {
                const option = document.createElement('div');
                option.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
                option.textContent = company.company_name;
                option.dataset.value = company.id;
                option.dataset.text = company.company_name;
                
                option.addEventListener('click', function() {
                    document.getElementById('companySearch').value = this.dataset.text;
                    document.getElementById('company').value = this.dataset.value;
                    currentInvoice.company = parseInt(this.dataset.value);
                    localStorage.setItem('lastSelectedCompany', this.dataset.value);
                    companyOptions.style.display = 'none';
                });
                
                companyOptions.appendChild(option);
            });
            companyOptions.style.display = 'block';
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#branchSearch') && !e.target.closest('#branchOptions')) {
            document.getElementById('branchOptions').style.display = 'none';
        }
        if (!e.target.closest('#companySearch') && !e.target.closest('#companyOptions')) {
            document.getElementById('companyOptions').style.display = 'none';
        }
    });
    
    document.getElementById('currency').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        currentInvoice.currency = parseInt(selectedOption.getAttribute('data-id'));
    });
    
    document.getElementById('project').addEventListener('change', function() {
        currentInvoice.project = this.value ? parseInt(this.value) : null;
        localStorage.setItem('lastSelectedProject', this.value || '');
    });
    
    document.getElementById('paymentMethod').addEventListener('change', function() {
        const bankContainer = document.getElementById('bankAccountContainer');
        if (this.value === 'bank_transfer') {
            bankContainer.style.display = 'flex';
        } else {
            bankContainer.style.display = 'none';
            document.getElementById('bankAccount').value = '';
        }
    });
    
    // Auto-add children checkbox
    const autoAddCheckbox = document.getElementById('autoAddChildren');
    autoAddCheckbox.checked = localStorage.getItem('autoAddChildren') === 'true';
    autoAddCheckbox.addEventListener('change', function() {
        localStorage.setItem('autoAddChildren', this.checked);
    });

    // Manual print switch — ON: no auto print after save, PRINT button shown. OFF: auto print (default)
    const manualPrintCheckbox = document.getElementById('manualPrintToggle');
    if (manualPrintCheckbox) {
        manualPrintCheckbox.checked = isManualPrintMode();
        manualPrintCheckbox.addEventListener('change', function() {
            localStorage.setItem('manualPrintMode', this.checked);
            if (!this.checked) hidePrintButton();
        });
    }

    const printInvoiceBtn = document.getElementById('printInvoiceBtn');
    if (printInvoiceBtn) {
        printInvoiceBtn.addEventListener('click', printLastInvoice);
    }

    // Price Setting — which product price field to use when adding items (default: MRP)
    const priceSettingSelect = document.getElementById('priceSetting');
    if (priceSettingSelect) {
        priceSettingSelect.value = localStorage.getItem('counterPriceSetting') || 'mrp';
        priceSettingSelect.addEventListener('change', function() {
            localStorage.setItem('counterPriceSetting', this.value);
        });
    }

    // "Buy by Amount" checkbox — optional feature, off by default (not every business wants it)
    // Elements may not be present in all invoice views (e.g. counter-invoice omits this panel)
    const enableAmountCheckbox = document.getElementById('enableAmountEntry');
    const quickAmountGroup = document.getElementById('quickAmountGroup');
    const quickAmountInput = document.getElementById('quickAmount');
    if (enableAmountCheckbox && quickAmountGroup && quickAmountInput) {
        enableAmountCheckbox.checked = localStorage.getItem('enableAmountEntry') === 'true';
        quickAmountGroup.style.display = enableAmountCheckbox.checked ? '' : 'none';
        enableAmountCheckbox.addEventListener('change', function() {
            localStorage.setItem('enableAmountEntry', this.checked);
            quickAmountGroup.style.display = this.checked ? '' : 'none';
            quickAmountInput.value = '';
        });

        quickAmountInput.addEventListener('input', calculateQtyFromAmount);
        quickAmountInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                calculateQtyFromAmount();
                document.getElementById('quickAddBtn').click();
            }
        });
    }

    document.getElementById('shortcutSettingsBtn').addEventListener('click', function() {
        document.getElementById('shortcutSettingsModal').style.display = 'flex';
    });
    
    document.getElementById('closeShortcutSettingsBtn').addEventListener('click', function() {
        document.getElementById('shortcutSettingsModal').style.display = 'none';
    });
    
    document.getElementById('saveShortcutSettingsBtn').addEventListener('click', saveShortcutSettings);
    
    document.getElementById('resetShortcutsBtn').addEventListener('click', resetShortcuts);
    
    // Shortcut input listeners
    ['shortcutProduct', 'shortcutCustomer', 'shortcutBranch', 'shortcutQty', 'shortcutPrice', 'shortcutQuickPay', 'shortcutSave', 'shortcutDraft', 'shortcutClear', 'shortcutReturn', 'shortcutNextField', 'shortcutPrevField'].forEach(id => {
        const input = document.getElementById(id);
        input.addEventListener('keydown', function(e) {
            e.preventDefault();
            let key = e.key;
            if (key.length === 1) key = key.toUpperCase();
            const parts = [];
            if (e.ctrlKey) parts.push('Ctrl');
            if (e.altKey) parts.push('Alt');
            if (e.shiftKey && key.length > 1) parts.push('Shift');
            if (key !== 'Control' && key !== 'Alt' && key !== 'Shift' && key !== 'Meta') {
                parts.push(key);
            }
            if (parts.length > 0) {
                this.value = parts.join('+');
            }
        });
    });
    
    // Global shortcut listener
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('shortcutSettingsModal').style.display === 'flex') return;
        
        let key = e.key;
        if (key.length === 1 && key.match(/[a-z]/i)) key = key.toUpperCase();
        
        let combo = '';
        if (e.ctrlKey) combo += 'Ctrl+';
        if (e.altKey) combo += 'Alt+';
        if (e.shiftKey && key.length > 1) combo += 'Shift+';
        combo += key;
        
        const activeElement = document.activeElement;
        const isInput = activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'SELECT');
        
        // Priority: Enter key in product field always selects product
        if (key === 'Enter' && activeElement && (activeElement.id === 'quickProduct' || activeElement.id === 'quickQty')) {
            return; // Let the field's own Enter handler work
        }
        
        if (combo === shortcuts.product) {
            e.preventDefault();
            document.getElementById('quickProduct').focus();
        } else if (combo === shortcuts.customer) {
            e.preventDefault();
            document.getElementById('customerSearch').focus();
        } else if (combo === shortcuts.branch) {
            e.preventDefault();
            document.getElementById('branchSearch').focus();
        } else if (combo === shortcuts.qty) {
            e.preventDefault();
            document.getElementById('quickQty').focus();
        } else if (combo === shortcuts.price) {
            e.preventDefault();
            focusLastItemPrice();
        } else if (combo === shortcuts.quickPay) {
            e.preventDefault();
            quickPay();
        } else if (combo === shortcuts.save) {
            e.preventDefault();
            e.stopPropagation();
            saveInvoice();
        } else if (combo === shortcuts.draft) {
            e.preventDefault();
            e.stopPropagation();
            saveInvoice('Draft');
        } else if (combo === shortcuts.clear && !isInput) {
            e.preventDefault();
            if (confirm('Clear current invoice?')) {
                currentInvoice.items = [];
                renderItems();
                updateSummary();
                document.getElementById('invoiceDiscountPercent').value = 0;
                document.getElementById('invoiceDiscountAmount').value = 0;
                document.getElementById('amountReceived').value = 0;
                document.getElementById('amountReturned').value = 0;
            }
        } else if (combo === shortcuts.return) {
            e.preventDefault();
            document.getElementById('returnBtn').click();
        } else if (combo === shortcuts.nextField) {
            e.preventDefault();
            const focusableElements = Array.from(document.querySelectorAll(
                'input:not([type="hidden"]):not([disabled]):not([readonly]):not([tabindex="-1"]), ' +
                'select:not([disabled]):not([tabindex="-1"]), ' +
                'button:not([disabled]):not([tabindex="-1"])'
            )).filter(el => {
                const style = window.getComputedStyle(el);
                return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
            });
            const currentIndex = focusableElements.indexOf(document.activeElement);
            if (currentIndex < focusableElements.length - 1) {
                focusableElements[currentIndex + 1].focus();
            } else {
                focusableElements[0].focus();
            }
        } else if (combo === shortcuts.prevField) {
            e.preventDefault();
            const focusableElements = Array.from(document.querySelectorAll(
                'input:not([type="hidden"]):not([disabled]):not([readonly]):not([tabindex="-1"]), ' +
                'select:not([disabled]):not([tabindex="-1"]), ' +
                'button:not([disabled]):not([tabindex="-1"])'
            )).filter(el => {
                const style = window.getComputedStyle(el);
                return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
            });
            const currentIndex = focusableElements.indexOf(document.activeElement);
            if (currentIndex > 0) {
                focusableElements[currentIndex - 1].focus();
            } else {
                focusableElements[focusableElements.length - 1].focus();
            }
        }
    });
    
    document.getElementById('returnBtn').addEventListener('click', function() {
        document.getElementById('returnIframe').src = '../sale_return/counter-return.php';
        document.getElementById('returnModal').style.display = 'flex';
    });
    
    document.getElementById('closeReturnBtn').addEventListener('click', function() {
        document.getElementById('returnModal').style.display = 'none';
        document.getElementById('returnIframe').src = '';
    });
    
    document.getElementById('closeCustomerBtn').addEventListener('click', function() {
        document.getElementById('customerModal').style.display = 'none';
        document.getElementById('customerIframe').src = '';
    });
    
    document.getElementById('closeProductBtn').addEventListener('click', function() {
        document.getElementById('productModal').style.display = 'none';
        document.getElementById('productIframe').src = '';
    });
    
    document.getElementById('draftsBtn').addEventListener('click', function() {
        loadDraftInvoices();
        document.getElementById('draftsModal').style.display = 'flex';
    });
    
    document.getElementById('closeDraftsBtn').addEventListener('click', function() {
        document.getElementById('draftsModal').style.display = 'none';
        document.getElementById('draftsModal').removeEventListener('keydown', handleDraftNavigation);
    });
    
    document.getElementById('clearBtn').addEventListener('click', function() {
        if (confirm('Clear current invoice?')) {
            currentInvoice.items = [];
            renderItems();
            updateSummary();
            document.getElementById('invoiceDiscountPercent').value = 0;
            document.getElementById('invoiceDiscountAmount').value = 0;
            document.getElementById('amountReceived').value = 0;
            document.getElementById('amountReturned').value = 0;
        }
    });
    
    document.getElementById('quickAddBtn').addEventListener('click', async function() {
        const productInput = document.getElementById('quickProduct').value.trim();
        const qty = parseFloat(document.getElementById('quickQty').value) || 1;

        if (!productInput) return;

        if (preSelectedProduct) {
            const p = preSelectedProduct;
            preSelectedProduct = null;
            selectProduct(p);
            return;
        }

        // Search for partial matches
        const partialMatches = productsData.filter(p =>
            p.name.toLowerCase().includes(productInput.toLowerCase())
        );

        const product = productsData.find(p =>
            p.name.trim().toLowerCase() === productInput.toLowerCase() ||
            p.code.toLowerCase() === productInput.toLowerCase() ||
            (p.barcode && p.barcode.toLowerCase() === productInput.toLowerCase()) ||
            (p.qr_code && p.qr_code.toLowerCase() === productInput.toLowerCase())
        );

        if (product) {
            selectProduct(product);
        } else if (partialMatches.length === 1) {
            selectProduct(partialMatches[0]);
        } else if (partialMatches.length > 1) {
            alert(`Multiple products found (${partialMatches.length}). Please be more specific.`);
        } else {
            alert('Product not found!');
        }
    });
    
    document.getElementById('quickProduct').addEventListener('keydown', function(e) {
        const suggestions = document.getElementById('productSuggestions');
        
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            if (suggestions.style.display === 'block') {
                const selected = suggestions.querySelector('.suggestion-item.selected');
                if (selected) {
                    const productName = selected.querySelector('strong').textContent;
                    const product = productsData.find(p => p.name === productName);
                    if (product) {
                        this.value = product.name;
                        preSelectedProduct = product;
                        suggestions.style.display = 'none';
                        document.getElementById('quickQty').focus();
                        document.getElementById('quickQty').select();
                    }
                    return;
                }
                const firstOption = suggestions.querySelector('.suggestion-item');
                if (firstOption) {
                    const productName = firstOption.querySelector('strong').textContent;
                    const product = productsData.find(p => p.name === productName);
                    if (product) {
                        this.value = product.name;
                        preSelectedProduct = product;
                        suggestions.style.display = 'none';
                        document.getElementById('quickQty').focus();
                        document.getElementById('quickQty').select();
                    }
                    return;
                }
            }
            
            // Handle exact barcode/QR match when suggestions are hidden
            const productInput = this.value.trim().toLowerCase();
            const product = productsData.find(p => 
                (p.barcode && p.barcode.toLowerCase() === productInput) ||
                (p.qr_code && p.qr_code.toLowerCase() === productInput)
            );
            if (product) {
                this.value = product.name;
                suggestions.style.display = 'none';
                document.getElementById('quickQty').focus();
                document.getElementById('quickQty').select();
            }
            return;
        }
        
        if (suggestions.style.display !== 'block') return;
        
        const items = suggestions.querySelectorAll('.suggestion-item');
        if (items.length === 0) return;
        
        let selected = suggestions.querySelector('.suggestion-item.selected');
        let currentIndex = selected ? Array.from(items).indexOf(selected) : -1;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (selected) {
                selected.classList.remove('selected');
                selected.style.background = 'white';
            }
            currentIndex = (currentIndex + 1) % items.length;
            items[currentIndex].classList.add('selected');
            items[currentIndex].style.background = 'var(--surface-1)';
            items[currentIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (selected) {
                selected.classList.remove('selected');
                selected.style.background = 'white';
            }
            currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
            items[currentIndex].classList.add('selected');
            items[currentIndex].style.background = 'var(--surface-1)';
            items[currentIndex].scrollIntoView({ block: 'nearest' });
        }
    });
    
    document.getElementById('quickQty').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            const btn = document.getElementById('quickAddBtn');
            if (btn) {
                btn.click();
            }
        }
    });
    
    document.getElementById('quickProduct').addEventListener('input', function(e) {
        preSelectedProduct = null;
        const searchTerm = e.target.value.trim().toLowerCase();
        const suggestions = document.getElementById('productSuggestions');
        
        if (searchTerm.length < 2) {
            suggestions.style.display = 'none';
            return;
        }
        
        const matches = productsData.filter(p => 
            p.name.toLowerCase().includes(searchTerm) ||
            p.code.toLowerCase().includes(searchTerm) ||
            (p.parent_product_name && p.parent_product_name.toLowerCase().includes(searchTerm)) ||
            (p.barcode && p.barcode.toLowerCase() === searchTerm) ||
            (p.qr_code && p.qr_code.toLowerCase() === searchTerm)
        );

        if (matches.length === 0) {
            suggestions.style.display = 'none';
        } else if (matches.length === 1 && matches[0].barcode && matches[0].barcode.toLowerCase() === searchTerm) {
            // Auto-select if exact barcode match
            this.value = matches[0].name;
            suggestions.style.display = 'none';
            // Auto-focus qty field for barcode scanner
            setTimeout(() => {
                document.getElementById('quickQty').focus();
                document.getElementById('quickQty').select();
            }, 100);
        } else if (matches.length === 1 && matches[0].qr_code && matches[0].qr_code.toLowerCase() === searchTerm) {
            // Auto-select if exact QR code match
            this.value = matches[0].name;
            suggestions.style.display = 'none';
            // Auto-focus qty field for QR scanner
            setTimeout(() => {
                document.getElementById('quickQty').focus();
                document.getElementById('quickQty').select();
            }, 100);
        } else {
            suggestions.innerHTML = '';
            matches.forEach(product => {
                const div = document.createElement('div');
                div.className = 'suggestion-item';
                div.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default); background: white;';
                div.innerHTML = `<strong>${product.name}</strong><br><small style="color: var(--subtext);">${product.code} - ${getEffectivePrice(product)}${product.parent_product_id && product.parent_product_name ? ' (Child of ' + product.parent_product_name + ')' : ''}</small>`;
                div.addEventListener('mouseenter', function() {
                    suggestions.querySelectorAll('.suggestion-item').forEach(el => {
                        el.classList.remove('selected');
                        el.style.background = 'white';
                    });
                    this.classList.add('selected');
                    this.style.background = 'var(--surface-1)';
                });
                div.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.background = 'white';
                    }
                });
                div.addEventListener('click', function() {
                    document.getElementById('quickProduct').value = product.name;
                    preSelectedProduct = product;
                    suggestions.style.display = 'none';
                    document.getElementById('quickQty').focus();
                    document.getElementById('quickQty').select();
                });
                suggestions.appendChild(div);
            });
            suggestions.style.display = 'block';
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.quick-entry')) {
            document.getElementById('productSuggestions').style.display = 'none';
        }
    });
    document.getElementById('quickPayBtn').addEventListener('click', quickPay);
    document.getElementById('saveInvoiceBtn').addEventListener('click', function(e) {
        e.preventDefault();
        saveInvoice();
    });
    document.getElementById('saveDraftBtn').addEventListener('click', function(e) {
        e.preventDefault();
        saveInvoice('Draft');
    });
    document.getElementById('amountReceived').addEventListener('input', function() {
        autoCalculateAmountReturned();
        updateSummary();
    });
    document.getElementById('amountReturned').addEventListener('input', function() {
        updateBalanceFromReturned();
    });
    document.getElementById('amountReturned').addEventListener('input', function() {
        updateBalanceFromReturned();
    });
    document.getElementById('invoiceDiscountPercent').addEventListener('input', updateInvoiceDiscount);
    document.getElementById('invoiceDiscountAmount').addEventListener('input', updateInvoiceDiscount);
});

async function loadDraftInvoices() {
    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/get-drafts.php');
        const data = await response.json();
        
        const tbody = document.getElementById('draftsTableBody');
        tbody.innerHTML = '';
        
        if (data.success && data.drafts.length > 0) {
            data.drafts.forEach((draft, index) => {
                const row = tbody.insertRow();
                row.dataset.draftId = draft.id;
                row.style.cursor = 'pointer';
                if (index === 0) row.classList.add('selected-draft');
                
                row.innerHTML = `
                    <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${draft.bill_no}</td>
                    <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${new Date(draft.sale_date).toLocaleDateString()}</td>
                    <td style="padding: 8px; border-bottom: 1px solid var(--border-default);">${draft.customer_name}</td>
                    <td style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: right;">${draft.currency_symbol}${parseFloat(draft.net_amount).toFixed(2)}</td>
                    <td style="padding: 8px; border-bottom: 1px solid var(--border-default); text-align: center;">
                        <button class="btn btn-primary btn-sm" onclick="loadDraft(${draft.id})" style="padding: 4px 8px; margin-right: 4px;">
                            <i class="fas fa-edit"></i> Load
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteDraft(${draft.id})" style="padding: 4px 8px;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('button')) {
                        tbody.querySelectorAll('tr').forEach(r => r.classList.remove('selected-draft'));
                        this.classList.add('selected-draft');
                    }
                });
            });
            
            // Add keyboard navigation
            document.getElementById('draftsModal').addEventListener('keydown', handleDraftNavigation);
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No draft invoices found</td></tr>';
        }
    } catch (error) {
        console.error('Error loading drafts:', error);
        document.getElementById('draftsTableBody').innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--error);">Error loading drafts</td></tr>';
    }
}

function handleDraftNavigation(e) {
    const tbody = document.getElementById('draftsTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr[data-draft-id]'));
    if (rows.length === 0) return;
    
    const selected = tbody.querySelector('tr.selected-draft');
    let currentIndex = selected ? rows.indexOf(selected) : 0;
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (selected) selected.classList.remove('selected-draft');
        currentIndex = (currentIndex + 1) % rows.length;
        rows[currentIndex].classList.add('selected-draft');
        rows[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (selected) selected.classList.remove('selected-draft');
        currentIndex = currentIndex <= 0 ? rows.length - 1 : currentIndex - 1;
        rows[currentIndex].classList.add('selected-draft');
        rows[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selected) {
            const draftId = selected.dataset.draftId;
            if (draftId) loadDraft(parseInt(draftId));
        }
    }
}

window.deleteDraft = async function(draftId) {
    if (!confirm('Delete this draft invoice?')) return;
    
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-delete.php?id=${draftId}`, {
            method: 'DELETE'
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification('Draft deleted successfully');
            loadDraftInvoices();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error deleting draft: ' + error.message);
    }
};

window.loadDraft = async function(draftId) {
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${draftId}`);
        const data = await response.json();
        
        if (data.success) {
            const invoice = data.invoice;
            
            // Clear current invoice
            currentInvoice.items = [];
            
            // Set customer
            const draftCustomer = customersData.find(c => c.id == invoice.customer_id);
            if (draftCustomer) selectCustomer(draftCustomer.id, `${draftCustomer.customer_code} - ${draftCustomer.customer_name}`);
            
            // Set branch
            const branch = branchesData.find(b => b.id == invoice.branch_id);
            if (branch) {
                const branchText = branch.parent_branch_name 
                    ? `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type}) - Parent: ${branch.parent_branch_name}`
                    : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                document.getElementById('branchSearch').value = branchText;
                document.getElementById('branch').value = invoice.branch_id;
                currentInvoice.branch = invoice.branch_id;
            }
            
            // Set currency
            const currencyOption = Array.from(document.getElementById('currency').options).find(opt => opt.getAttribute('data-id') == invoice.currency_id);
            if (currencyOption) {
                document.getElementById('currency').value = currencyOption.value;
                currentInvoice.currency = invoice.currency_id;
            }
            
            // Set project
            if (invoice.project_id) {
                document.getElementById('project').value = invoice.project_id;
                currentInvoice.project = invoice.project_id;
            }
            
            // Set payment method
            document.getElementById('paymentMethod').value = invoice.payment_method || 'cash';
            if (invoice.payment_method === 'bank_transfer') {
                document.getElementById('bankAccountContainer').style.display = 'flex';
            }
            
            // Load items
            data.items.forEach(item => {
                if (!item.parent_row_id) {
                    const uom = uomData.find(u => u.id == item.uom_id);
                    const invoiceItem = {
                        id: Date.now() + Math.random(),
                        product: item.product_name,
                        productId: item.product_id,
                        unit: uom ? uom.uom_name : 'PCS',
                        uomId: item.uom_id,
                        qty: parseFloat(item.quantity),
                        price: parseFloat(item.sale_price),
                        discountPercent: parseFloat(item.discount_percent || 0),
                        gstPercent: parseFloat(item.gst_percent || 0)
                    };
                    
                    invoiceItem.gross = invoiceItem.qty * invoiceItem.price;
                    invoiceItem.discountAmount = invoiceItem.gross * (invoiceItem.discountPercent / 100);
                    invoiceItem.gstAmount = (invoiceItem.gross - invoiceItem.discountAmount) * (invoiceItem.gstPercent / 100);
                    invoiceItem.net = invoiceItem.gross - invoiceItem.discountAmount + invoiceItem.gstAmount;
                    
                    currentInvoice.items.push(invoiceItem);
                }
            });
            
            // Set discount
            document.getElementById('invoiceDiscountAmount').value = invoice.total_discount_amount || 0;
            
            // Set amounts
            document.getElementById('amountReceived').value = invoice.amount_paid || 0;
            document.getElementById('amountReturned').value = invoice.amount_returned || 0;
            
            renderItems();
            updateSummary();
            
            document.getElementById('draftsModal').style.display = 'none';
            showNotification('Draft loaded successfully');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error loading draft: ' + error.message);
    }
};

async function loadProducts() {
    // include_children=1 so child products also show in the Quick Add dropdown
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-products.php?include_children=1');
    const data = await response.json();
    if (data.success) productsData = data.products;
}

async function loadCustomers() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-customers.php');
    const data = await response.json();
    if (data.success) {
        customersData = data.customers;
        const optionsDiv = document.getElementById('customerOptions');
        optionsDiv.innerHTML = '';
        data.customers.forEach(customer => {
            const div = document.createElement('div');
            div.className = 'dropdown-option';
            div.style.cssText = 'padding: 8px 10px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
            div.textContent = `${customer.customer_code} - ${customer.customer_name}`;
            div.dataset.id = customer.id;
            div.addEventListener('click', function() {
                selectCustomer(customer.id, this.textContent);
                document.getElementById('customer')._justSelected = true;
                fetchCustomerBalance(customer.id);
                const c = customersData.find(x => x.id == customer.id);
                if (c && c.supplier_man_id) document.getElementById('supplierMan').value = c.supplier_man_id;
                else document.getElementById('supplierMan').value = '';
            });
            optionsDiv.appendChild(div);
        });
        initCustomerSearch();
        const cashCustomer = data.customers.find(c => c.customer_name === 'Cash Customer');
        if (cashCustomer) {
            selectCustomer(cashCustomer.id, `${cashCustomer.customer_code} - ${cashCustomer.customer_name}`);
            await fetchCustomerBalance(cashCustomer.id);
        }
    }
}

function selectCustomer(id, text) {
    document.getElementById('customer').value = id;
    document.getElementById('customerSearch').value = text;
    document.getElementById('customerOptions').style.display = 'none';
    currentInvoice.customer = parseInt(id);
}

function initCustomerSearch() {
    const search = document.getElementById('customerSearch');
    const optionsDiv = document.getElementById('customerOptions');
    let selectedIndex = -1;

    function getVisible() {
        return Array.from(optionsDiv.getElementsByClassName('dropdown-option'))
            .filter(o => o.style.display !== 'none');
    }

    function updateSelection(visible) {
        visible.forEach((o, i) => {
            o.style.backgroundColor = i === selectedIndex ? '#1a6cdc' : '';
            o.style.color = i === selectedIndex ? 'white' : '';
        });
        if (selectedIndex >= 0 && visible[selectedIndex]) {
            visible[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function filterOptions() {
        const term = search.value.toLowerCase();
        Array.from(optionsDiv.getElementsByClassName('dropdown-option')).forEach(o => {
            o.style.display = o.textContent.toLowerCase().includes(term) ? 'block' : 'none';
            o.style.backgroundColor = '';
            o.style.color = '';
        });
    }

    search.addEventListener('click', function(e) {
        e.stopPropagation();
        search.select();
        optionsDiv.style.display = 'block';
        filterOptions();
    });

    search.addEventListener('focus', function() {
        search._prevValue = search.value;
        search.value = '';
        selectedIndex = -1;
        optionsDiv.style.display = 'block';
        filterOptions();
    });

    search.addEventListener('blur', function() {
        setTimeout(() => {
            if (!document.getElementById('customer')._justSelected && search._prevValue) {
                if (!document.getElementById('customer').value) search.value = search._prevValue;
            }
            document.getElementById('customer')._justSelected = false;
        }, 200);
    });

    search.addEventListener('input', function() {
        selectedIndex = -1;
        filterOptions();
        optionsDiv.style.display = 'block';
    });

    search.addEventListener('keydown', function(e) {
        const visible = getVisible();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, visible.length - 1);
            updateSelection(visible);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection(visible);
                } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && visible[selectedIndex]) {
                visible[selectedIndex].click();
            } else if (visible.length > 0) {
                visible[0].click();
            } else if (search.value.trim()) {
                createCustomerByName(search.value.trim());
            }
        }
    });

    document.addEventListener('click', function() {
        optionsDiv.style.display = 'none';
    });
}
async function createCustomerByName(name) {
    if (createCustomerByName._busy) return;

    const companyId = currentInvoice.company || document.getElementById('company').value;
    if (!companyId) {
        alert('Please select a company first!');
        return;
    }

    createCustomerByName._busy = true;
    try {
const response = await fetch('../../../../server/api/sale/pos_invoice/quick-add-customer.php', {
                method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customerName: name, companyId: parseInt(companyId) })
        });
        const data = await response.json();

        if (!data.success) {
            alert('Error: ' + data.message);
            return;
        }

        const c = data.customer;
        customersData.push(c);

        // Add to dropdown list (without reloading everything)
        const label = `${c.customer_code} - ${c.customer_name}`;
        const div = document.createElement('div');
        div.className = 'dropdown-option';
        div.style.cssText = 'padding: 8px 10px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
        div.textContent = label;
        div.dataset.id = c.id;
        div.addEventListener('click', function () {
            selectCustomer(c.id, label);
            document.getElementById('customer')._justSelected = true;
            fetchCustomerBalance(c.id);
            document.getElementById('supplierMan').value = '';
        });
        document.getElementById('customerOptions').appendChild(div);

        selectCustomer(c.id, label);
        document.getElementById('customer')._justSelected = true;
        await fetchCustomerBalance(c.id);
        document.getElementById('supplierMan').value = '';
        showNotification('New customer added: ' + c.customer_name);
        document.getElementById('quickProduct').focus();
    } catch (error) {
        alert('Error adding customer: ' + error.message);
    } finally {
        createCustomerByName._busy = false;
    }
}

async function fetchCustomerBalance(customerId) {
    try {
        const response = await fetch(`../../../../server/api/financial_reports/customer_ledger/customer-ledger.php?customer_id=${customerId}&type=summary`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            const balance = parseFloat(data.data[0].closing_balance || 0);
            currentInvoice.previousBalance = balance >= 0 
                ? `Dr ${balance.toFixed(2)}` 
                : `Cr ${Math.abs(balance).toFixed(2)}`;
        } else {
            currentInvoice.previousBalance = 'Dr 0.00';
        }
    } catch (error) {
        console.error('Error fetching customer balance:', error);
        currentInvoice.previousBalance = 'Dr 0.00';
    }
}

async function loadBranches() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-branches.php');
    const data = await response.json();
    if (data.success) {
        branchesData = data.branches;
        const branchOptions = document.getElementById('branchOptions');
        branchOptions.innerHTML = '';
        
        data.branches.forEach(branch => {
            const option = document.createElement('div');
            option.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default);';
            const branchText = branch.parent_branch_name 
                ? `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type}) - Parent: ${branch.parent_branch_name}`
                : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
            option.textContent = branchText;
            option.dataset.value = branch.id;
            option.dataset.text = branchText;
            
            option.addEventListener('click', function() {
                document.getElementById('branchSearch').value = this.dataset.text;
                document.getElementById('branch').value = this.dataset.value;
                currentInvoice.branch = parseInt(this.dataset.value);
                localStorage.setItem('lastSelectedBranch', this.dataset.value);
                branchOptions.style.display = 'none';
                loadStockData();
                loadCounters(parseInt(this.dataset.value));
            });

            branchOptions.appendChild(option);
        });

        // Load from localStorage or set first branch
        const savedBranchId = localStorage.getItem('lastSelectedBranch');
        if (savedBranchId && data.branches.find(b => b.id == savedBranchId)) {
            const savedBranch = data.branches.find(b => b.id == savedBranchId);
            const branchText = savedBranch.parent_branch_name
                ? `${savedBranch.branch_code} - ${savedBranch.branch_name} (${savedBranch.branch_type}) - Parent: ${savedBranch.parent_branch_name}`
                : `${savedBranch.branch_code} - ${savedBranch.branch_name} (${savedBranch.branch_type})`;
            document.getElementById('branchSearch').value = branchText;
            document.getElementById('branch').value = savedBranchId;
            currentInvoice.branch = parseInt(savedBranchId);
            loadCounters(parseInt(savedBranchId));
        } else if (data.branches.length > 0) {
            const firstBranch = data.branches[0];
            const branchText = firstBranch.parent_branch_name
                ? `${firstBranch.branch_code} - ${firstBranch.branch_name} (${firstBranch.branch_type}) - Parent: ${firstBranch.parent_branch_name}`
                : `${firstBranch.branch_code} - ${firstBranch.branch_name} (${firstBranch.branch_type})`;
            document.getElementById('branchSearch').value = branchText;
            document.getElementById('branch').value = firstBranch.id;
            currentInvoice.branch = firstBranch.id;
            loadCounters(firstBranch.id);
        }
    }
}

async function loadCounters(branchId) {
    const sel = document.getElementById('counterSelect');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Select Counter --</option>';
    if (!branchId) return;
    try {
        const res = await fetch(`../../../../server/api/sale/pos_invoice/get-counters.php?branch_id=${branchId}`);
        const data = await res.json();
        if (data.success && data.counters.length > 0) {
            data.counters.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.counter_name;
                sel.appendChild(opt);
            });
            // Restore last selected counter for this branch
            const saved = localStorage.getItem(`lastCounter_${branchId}`);
            if (saved && data.counters.find(c => c.id == saved)) {
                sel.value = saved;
            }
        }
    } catch (e) {
        // Non-fatal — counters optional
    }
}

async function loadCompanies() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-companies.php');
    const data = await response.json();
    if (data.success) {
        companiesData = data.companies;
        
        const savedCompanyId = localStorage.getItem('lastSelectedCompany');
        if (savedCompanyId && data.companies.find(c => c.id == savedCompanyId)) {
            const savedCompany = data.companies.find(c => c.id == savedCompanyId);
            document.getElementById('companySearch').value = savedCompany.company_name;
            document.getElementById('company').value = savedCompanyId;
            currentInvoice.company = parseInt(savedCompanyId);
        } else if (data.companies.length > 0) {
            const firstCompany = data.companies[0];
            document.getElementById('companySearch').value = firstCompany.company_name;
            document.getElementById('company').value = firstCompany.id;
            currentInvoice.company = firstCompany.id;
        }
    }
}

async function loadCurrencies() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-currencies.php');
    const data = await response.json();
    if (data.success) {
        currenciesData = data.currencies;
        const currencySelect = document.getElementById('currency');
        currencySelect.innerHTML = '<option value="">Select Currency</option>';
        data.currencies.forEach(currency => {
            const option = document.createElement('option');
            option.value = currency.code;
            option.textContent = `${currency.code} - ${currency.symbol}`;
            option.setAttribute('data-id', currency.currency_id);
            if (currency.is_base_currency == 1) {
                option.selected = true;
                currentInvoice.currency = currency.currency_id;
            }
            currencySelect.appendChild(option);
        });
    }
}

async function loadBankAccounts() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-bank-accounts.php');
    const data = await response.json();
    if (data.success) {
        bankAccountsData = data.accounts;
        const bankSelect = document.getElementById('bankAccount');
        bankSelect.innerHTML = '<option value="">Select Bank Account</option>';
        data.accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${account.account_title} - ${account.account_number}`;
            bankSelect.appendChild(option);
        });
    }
}

async function loadUOM() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-uom.php');
    const data = await response.json();
    if (data.success) {
        uomData = data.uoms;
    }
}

function updateDateTime() {
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toTimeString().split(' ')[0];
    document.getElementById('currentDateTime').textContent = `${dateStr} ${timeStr}`;
}

async function loadNextInvoiceNo() {
    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/get-next-invoice-number.php');
        const data = await response.json();
        if (data.success) {
            document.getElementById('invoiceNo').textContent = data.nextNumber;
        }
    } catch (error) {
        console.error('Error loading invoice number:', error);
    }
}

async function loadStockData() {
    const branchId = currentInvoice.branch;
    if (!branchId) return;
    
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-stock.php?branch_id=${branchId}`);
        const data = await response.json();
        if (data.success) {
            updateStockDisplay();
        }
    } catch (error) {
        console.error('Error loading stock:', error);
    }
}

function updateStockDisplay() {
    const stockList = document.getElementById('stockList');
    stockList.innerHTML = '<div style="text-align: center; color: var(--subtext); padding: 8px;">Stock tracking active</div>';
    document.getElementById('stockUpdateTime').textContent = new Date().toLocaleTimeString();
}

async function loadCurrencies() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-currencies.php');
    const data = await response.json();
    if (data.success) {
        currenciesData = data.currencies;
        const currencySelect = document.getElementById('currency');
        currencySelect.innerHTML = '<option value="">Select Currency</option>';
        data.currencies.forEach(currency => {
            const option = document.createElement('option');
            option.value = currency.code;
            option.textContent = `${currency.code} - ${currency.symbol}`;
            option.setAttribute('data-id', currency.currency_id);
            if (currency.is_base_currency == 1) {
                option.selected = true;
                currentInvoice.currency = currency.currency_id;
            }
            currencySelect.appendChild(option);
        });
    }
}

async function loadBankAccounts() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-bank-accounts.php');
    const data = await response.json();
    if (data.success) {
        bankAccountsData = data.accounts;
        const bankSelect = document.getElementById('bankAccount');
        bankSelect.innerHTML = '<option value="">Select Bank Account</option>';
        data.accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${account.account_title} - ${account.account_number}`;
            bankSelect.appendChild(option);
        });
    }
}

async function loadUOM() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-uom.php');
    const data = await response.json();
    if (data.success) {
        uomData = data.uoms;
    }
}

function updateDateTime() {
    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toTimeString().split(' ')[0];
    document.getElementById('currentDateTime').textContent = `${dateStr} ${timeStr}`;
}

async function loadNextInvoiceNo() {
    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/get-next-invoice-number.php');
        const data = await response.json();
        if (data.success) {
            document.getElementById('invoiceNo').textContent = data.nextNumber;
        }
    } catch (error) {
        console.error('Error loading invoice number:', error);
    }
}

// Resolves the product currently referenced by the Quick Add Product field,
// the same matching logic used by the quickAddBtn handler (exact match, then a single partial match).
function resolveQuickProduct() {
    if (preSelectedProduct) return preSelectedProduct;

    const productInput = document.getElementById('quickProduct').value.trim().toLowerCase();
    if (!productInput) return null;

    const exactMatch = productsData.find(p =>
        p.name.trim().toLowerCase() === productInput ||
        p.code.toLowerCase() === productInput ||
        (p.barcode && p.barcode.toLowerCase() === productInput) ||
        (p.qr_code && p.qr_code.toLowerCase() === productInput)
    );
    if (exactMatch) return exactMatch;

    const partialMatches = productsData.filter(p => p.name.toLowerCase().includes(productInput));
    return partialMatches.length === 1 ? partialMatches[0] : null;
}

// "Buy by Amount" — customer says "Rs 100 of Sugar", we work out the quantity from the product's price.
// Only wired up when the "Enable Buy by Amount" setting is on; otherwise quickAmount stays hidden and unused.
function calculateQtyFromAmount() {
    const amount = parseFloat(document.getElementById('quickAmount').value);
    if (!amount || amount <= 0) return;

    const product = resolveQuickProduct();
    if (!product) return;

    const price = getEffectivePrice(product);
    if (!price || price <= 0) return;

    const qty = amount / price;
    document.getElementById('quickQty').value = qty.toFixed(3);
}

function selectProduct(product) {
    if (isAddingProduct) {
        console.log('Already adding product, skipping...');
        return;
    }
    isAddingProduct = true;
    checkStockAndProceed(product);
}

async function checkStockAndProceed(product) {
    const branchId = currentInvoice.branch;
    if (!branchId) {
        alert('Please select a branch first!');
        isAddingProduct = false;
        return;
    }
    
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-stock.php?product_id=${product.id}&branch_id=${branchId}`);
        const data = await response.json();
        
        if (data.success) {
            const availableStock = Math.floor(data.stock);
            const requestedQty = parseFloat(document.getElementById('quickQty').value) || 1;
            
            const isCounterMode = document.body.classList.contains('counter-mode');
            const quickQtyInput = document.getElementById('quickQty');
            
            if (isCounterMode) {
                if (availableStock === 0) {
                    quickQtyInput.style.background = '#3d1f1f';
                    quickQtyInput.style.borderColor = '#e34f4f';
                    quickQtyInput.style.color = '#ff6b6b';
                } else if (requestedQty > availableStock) {
                    quickQtyInput.style.background = '#3d2f1f';
                    quickQtyInput.style.borderColor = '#e8b23f';
                    quickQtyInput.style.color = '#ffd700';
                } else {
                    quickQtyInput.style.background = '#1f3d2f';
                    quickQtyInput.style.borderColor = '#2fbf71';
                    quickQtyInput.style.color = '#00ff88';
                }
            } else {
                if (availableStock === 0) {
                    quickQtyInput.style.background = '#ffebee';
                    quickQtyInput.style.borderColor = '#e34f4f';
                    quickQtyInput.style.color = '#c62828';
                } else if (requestedQty > availableStock) {
                    quickQtyInput.style.background = '#fff3e0';
                    quickQtyInput.style.borderColor = '#e8b23f';
                    quickQtyInput.style.color = '#f57c00';
                } else {
                    quickQtyInput.style.background = '#e8f5e9';
                    quickQtyInput.style.borderColor = '#2fbf71';
                    quickQtyInput.style.color = '#2e7d32';
                }
            }
            
            // Low stock popup removed: product is added directly (qty field colour still shows stock status)
        }
    } catch (error) {
        console.error('Error checking stock:', error);
    }
    
    // Check if product has children
    if (product.parent_product_id === null) {
        fetch(`../../../../server/api/sale/pos_invoice/get-child-products.php?parent_id=${product.id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.children.length > 0) {
                    // Check setting for auto-add or manual selection
                    const autoAddChildren = localStorage.getItem('autoAddChildren') === 'true';
                    
                    if (autoAddChildren) {
                        // Auto-add parent with all children at default quantities
                        const children = data.children.map(child => {
                            const childUom = uomData.find(u => u.id == child.default_unit_id);
                            return {
                                id: child.id,
                                name: child.name,
                                qty: 1,
                                price: getEffectivePrice(product) / data.children.length,
                                stock_affects: child.stock_affects,
                                invoice_affects: child.invoice_affects,
                                uomId: child.default_unit_id || 9,
                                unit: childUom ? childUom.uom_name : 'PCS'
                            };
                        });
                        addProductWithChildren(product, children);
                    } else {
                        // Children are now selectable directly from the Quick Add dropdown,
                        // so the manual child-selection modal is skipped here.
                        addProductToInvoice(product);
                    }
                } else {
                    addProductToInvoice(product);
                }
            });
    } else {
        addProductToInvoice(product);
    }
}

function showChildProductModal(parent, children) {
    // Remove any existing modal first
    const existingModal = document.querySelector('div[style*="fixed"][id^="childModal"]');
    if (existingModal) existingModal.remove();
    
    const modal = document.createElement('div');
    modal.id = 'childModal_' + Date.now();
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;';
    
    const content = document.createElement('div');
    content.style.cssText = 'background: white; padding: 20px; border-radius: 8px; max-width: 600px; width: 90%;';
    content.innerHTML = `
        <h3 style="margin-bottom: 16px; color: var(--heading);">Select Child Products for ${parent.name}</h3>
        <div id="childProductsList" style="max-height: 300px; overflow-y: auto;"></div>
        <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: flex-end;">
            <button class="btn btn-secondary" onclick="this.closest('div[style*=fixed]').remove()">Cancel</button>
            <button class="btn btn-primary" id="addChildProductsBtn">Add to Invoice</button>
        </div>
    `;
    
    const childList = content.querySelector('#childProductsList');
    children.forEach(child => {
        const childProduct = productsData.find(p => p.id == child.id);
        const childPrice = childProduct && getEffectivePrice(childProduct) > 0 ? getEffectivePrice(childProduct) : getEffectivePrice(parent);
        
        const div = document.createElement('div');
        div.style.cssText = 'padding: 8px; border-bottom: 1px solid var(--border-default); display: grid; grid-template-columns: auto 1fr 100px 100px; gap: 8px; align-items: center;';
        div.innerHTML = `
            <input type="checkbox" class="child-check" data-id="${child.id}" data-name="${child.name}" data-stock="${child.stock_affects}" data-invoice="${child.invoice_affects}">
            <span>${child.name}</span>
            <input type="number" class="child-qty" min="0" value="0" style="padding: 4px; border: 1px solid var(--border-default); border-radius: 4px;" placeholder="Qty">
            <input type="number" class="child-price" min="0" value="${childPrice.toFixed(2)}" step="0.01" style="padding: 4px; border: 1px solid var(--border-default); border-radius: 4px;" placeholder="Price">
        `;
        childList.appendChild(div);
    });
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    content.querySelector('#addChildProductsBtn').onclick = function() {
        const selectedChildren = [];
        childList.querySelectorAll('.child-check:checked').forEach(check => {
            const row = check.closest('div');
            const qty = parseFloat(row.querySelector('.child-qty').value) || 0;
            const price = parseFloat(row.querySelector('.child-price').value) || 0;
            if (qty > 0) {
                const childProduct = children.find(c => c.id == check.dataset.id);
                const childUom = uomData.find(u => u.id == childProduct.default_unit_id);
                selectedChildren.push({
                    id: check.dataset.id,
                    name: check.dataset.name,
                    qty: qty,
                    price: price,
                    stock_affects: check.dataset.stock,
                    invoice_affects: check.dataset.invoice,
                    uomId: childProduct.default_unit_id || 9,
                    unit: childUom ? childUom.uom_name : 'PCS'
                });
            }
        });
        
        if (selectedChildren.length > 0) {
            addProductWithChildren(parent, selectedChildren);
        } else {
            addProductToInvoice(parent);
        }
        modal.remove();
    };
}

function addProductWithChildren(parent, children) {
    // Parent qty = sum of all children quantities
    const qty = children.reduce((sum, child) => sum + child.qty, 0);

    // Calculate parent price from sum of children prices
    const totalChildPrice = children.reduce((sum, child) => sum + (child.price * child.qty), 0);

    // Get UOM name from product's default_unit_id
    const uom = uomData.find(u => u.id == parent.default_unit_id);

    const item = {
        id: Date.now(),
        product: parent.name,
        productId: parent.id,
        unit: uom ? uom.uom_name : 'PCS',
        uomId: parent.default_unit_id || 9,
        qty: qty,
        price: totalChildPrice / qty,
        discountPercent: 0,
        gstPercent: parseFloat(parent.sales_tax || 0),
        children: children
    };

    item.gross = item.qty * item.price;
    item.discountAmount = item.gross * (item.discountPercent / 100);
    item.gstAmount = (item.gross - item.discountAmount) * (item.gstPercent / 100);
    item.net = item.gross - item.discountAmount + item.gstAmount;

    currentInvoice.items.push(item);
    hidePrintButton();
    renderItems();
    updateSummary();

    document.getElementById('quickProduct').value = '';
    document.getElementById('quickQty').value = 1;
    const quickAmountEl = document.getElementById('quickAmount');
    if (quickAmountEl) quickAmountEl.value = '';
    document.getElementById('productSuggestions').style.display = 'none';
    document.getElementById('quickProduct').focus();
    isAddingProduct = false;
}

function addProductToInvoice(product) {
    // Get UOM name from product's default_unit_id
    const uom = uomData.find(u => u.id == product.default_unit_id);

    const item = {
        id: Date.now(),
        product: product.name,
        productId: product.id,
        unit: uom ? uom.uom_name : 'PCS',
        uomId: product.default_unit_id || 9,
        qty: parseFloat(document.getElementById('quickQty').value) || 1,
        price: getEffectivePrice(product),
        discountPercent: parseFloat(product.default_discount || 0),
        gstPercent: parseFloat(product.sales_tax || 0)
    };

    item.gross = item.qty * item.price;
    item.discountAmount = item.gross * (item.discountPercent / 100);
    item.gstAmount = (item.gross - item.discountAmount) * (item.gstPercent / 100);
    item.net = item.gross - item.discountAmount + item.gstAmount;

    currentInvoice.items.push(item);
    hidePrintButton();
    renderItems();
    updateSummary();

    document.getElementById('quickProduct').value = '';
    document.getElementById('quickQty').value = 1;
    const quickAmountEl = document.getElementById('quickAmount');
    if (quickAmountEl) quickAmountEl.value = '';
    document.getElementById('productSuggestions').style.display = 'none';
    document.getElementById('quickProduct').focus();
    isAddingProduct = false;
}

function renderItems() {
    const tbody = document.getElementById('itemsBody');
    tbody.innerHTML = '';
    
    currentInvoice.items.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'item-row';
        row.innerHTML = `
            <div class="text-center">${index + 1}</div>
            <div>${item.product}${item.children ? '<br><small style="color: var(--subtext);">(' + item.children.map(c => c.name + ': ' + c.qty + ' @ ' + formatCurrency(c.price)).join(' | ') + ')</small>' : ''}</div>
            <div>${item.unit}</div>
            <input type="number" value="${item.qty}" min="1" class="text-right item-qty-input" data-item-id="${item.id}" data-product-id="${item.productId}" onchange="updateItemQty(${item.id}, this.value)">
            <input type="number" value="${item.price.toFixed(2)}" step="0.01" class="text-right item-price-input" data-item-id="${item.id}" onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('quickProduct').focus();}" onchange="updateItemPrice(${item.id}, this.value)">
            <div class="readonly">${formatCurrency(item.gross)}</div>
            <input type="number" value="${item.discountPercent}" step="0.1" class="text-right" data-role="disc-pct" data-item-id="${item.id}" onchange="updateItemDiscountPct(${item.id}, this.value)">
            <input type="number" value="${item.discountAmount.toFixed(2)}" step="0.01" class="text-right" data-role="disc-amt" data-item-id="${item.id}" onchange="updateItemDiscountAmt(${item.id}, this.value)">
            <input type="number" value="${item.gstPercent}" step="0.1" class="text-right" onchange="updateItemGst(${item.id}, this.value)">
            <div class="readonly">${formatCurrency(item.net)}</div>
            <div class="text-center">
                <button class="btn btn-danger btn-micro" onclick="removeItem(${item.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        tbody.appendChild(row);
    });
    
    // Check stock for each item and highlight
    checkAllItemsStock();
}

window.updateItemQty = function(itemId, newQty) {
    const item = currentInvoice.items.find(i => i.id === itemId);
    if (item) {
        item.qty = parseFloat(newQty) || 1;
        recalculateItem(item);
        renderItems();
        updateSummary();
    }
};

window.updateItemPrice = function(itemId, newPrice) {
    const item = currentInvoice.items.find(i => i.id === itemId);
    if (item) {
        item.price = parseFloat(newPrice) || 0;
        recalculateItem(item);
        renderItems();
        updateSummary();
    }
};

window.updateItemDiscount = function(itemId, newPercent) {
    const item = currentInvoice.items.find(i => i.id === itemId);
    if (item) {
        item.discountPercent = parseFloat(newPercent) || 0;
        recalculateItem(item);
        renderItems();
        updateSummary();
    }
};

window.updateItemDiscountPct = function(itemId, newPercent) {
    const item = currentInvoice.items.find(i => i.id === itemId);
    if (item) {
        item.discountPercent = parseFloat(newPercent) || 0;
        item.discountAmount = item.gross * (item.discountPercent / 100);
        item.gstAmount = (item.gross - item.discountAmount) * (item.gstPercent / 100);
        item.net = item.gross - item.discountAmount + item.gstAmount;
        renderItems();
        updateSummary();
    }
};

window.updateItemDiscountAmt = function(itemId, newAmt) {
    const item = currentInvoice.items.find(i => i.id === itemId);
    if (item) {
        item.discountAmount = parseFloat(newAmt) || 0;
        item.discountPercent = item.gross > 0 ? (item.discountAmount / item.gross) * 100 : 0;
        item.gstAmount = (item.gross - item.discountAmount) * (item.gstPercent / 100);
        item.net = item.gross - item.discountAmount + item.gstAmount;
        renderItems();
        updateSummary();
    }
};

window.updateItemGst = function(itemId, newPercent) {
    const item = currentInvoice.items.find(i => i.id === itemId);
    if (item) {
        item.gstPercent = parseFloat(newPercent) || 0;
        recalculateItem(item);
        renderItems();
        updateSummary();
    }
};

window.removeItem = function(itemId) {
    currentInvoice.items = currentInvoice.items.filter(i => i.id !== itemId);
    renderItems();
    updateSummary();
};

async function checkAllItemsStock() {
    const branchId = currentInvoice.branch;
    if (!branchId) return;
    
    const isCounterMode = document.body.classList.contains('counter-mode');
    const qtyInputs = document.querySelectorAll('.item-qty-input');
    for (const input of qtyInputs) {
        const productId = input.dataset.productId;
        const qty = parseFloat(input.value) || 0;
        
        if (!productId) continue;
        
        try {
            const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-stock.php?product_id=${productId}&branch_id=${branchId}`);
            const data = await response.json();
            
            if (data.success) {
                const availableStock = Math.floor(data.stock);
                
                if (isCounterMode) {
                    if (availableStock === 0) {
                        input.style.background = '#3d1f1f';
                        input.style.borderColor = '#e34f4f';
                        input.style.color = '#ff6b6b';
                    } else if (qty > availableStock) {
                        input.style.background = '#3d2f1f';
                        input.style.borderColor = '#e8b23f';
                        input.style.color = '#ffd700';
                    } else {
                        input.style.background = '#1f3d2f';
                        input.style.borderColor = '#2fbf71';
                        input.style.color = '#00ff88';
                    }
                } else {
                    if (availableStock === 0) {
                        input.style.background = '#ffebee';
                        input.style.borderColor = '#e34f4f';
                        input.style.color = '#c62828';
                    } else if (qty > availableStock) {
                        input.style.background = '#fff3e0';
                        input.style.borderColor = '#e8b23f';
                        input.style.color = '#f57c00';
                    } else {
                        input.style.background = '#e8f5e9';
                        input.style.borderColor = '#2fbf71';
                        input.style.color = '#2e7d32';
                    }
                }
            }
        } catch (error) {
            console.error('Error checking stock:', error);
        }
    }
}

// Focus (and select) the PRICE input of the last item row so the price can be typed right after QTY.
// ENTER in the price field returns focus to Quick Add Product.
function focusLastItemPrice() {
    const inputs = document.querySelectorAll('.item-price-input');
    if (inputs.length === 0) return;
    const last = inputs[inputs.length - 1];
    last.focus();
    last.select();
}

function loadShortcuts() {
    const saved = localStorage.getItem('pos_shortcuts');
    if (saved) {
        // merge with defaults so newly added shortcuts (e.g. price) exist for already-saved settings
        shortcuts = { ...defaultShortcuts, ...JSON.parse(saved) };
    }
    loadShortcutInputs();
}

function loadShortcutInputs() {
    document.getElementById('shortcutProduct').value = shortcuts.product;
    document.getElementById('shortcutCustomer').value = shortcuts.customer;
    document.getElementById('shortcutBranch').value = shortcuts.branch;
    document.getElementById('shortcutQty').value = shortcuts.qty;
    document.getElementById('shortcutPrice').value = shortcuts.price;
    document.getElementById('shortcutQuickPay').value = shortcuts.quickPay;
    document.getElementById('shortcutSave').value = shortcuts.save;
    document.getElementById('shortcutDraft').value = shortcuts.draft;
    document.getElementById('shortcutClear').value = shortcuts.clear;
    document.getElementById('shortcutReturn').value = shortcuts.return;
    document.getElementById('shortcutNextField').value = shortcuts.nextField;
    document.getElementById('shortcutPrevField').value = shortcuts.prevField;
}

function saveShortcutSettings() {
    shortcuts = {
        price: document.getElementById('shortcutPrice').value || defaultShortcuts.price,
        product: document.getElementById('shortcutProduct').value || defaultShortcuts.product,
        customer: document.getElementById('shortcutCustomer').value || defaultShortcuts.customer,
        branch: document.getElementById('shortcutBranch').value || defaultShortcuts.branch,
        qty: document.getElementById('shortcutQty').value || defaultShortcuts.qty,
        quickPay: document.getElementById('shortcutQuickPay').value || defaultShortcuts.quickPay,
        save: document.getElementById('shortcutSave').value || defaultShortcuts.save,
        draft: document.getElementById('shortcutDraft').value || defaultShortcuts.draft,
        clear: document.getElementById('shortcutClear').value || defaultShortcuts.clear,
        return: document.getElementById('shortcutReturn').value || defaultShortcuts.return,
        nextField: document.getElementById('shortcutNextField').value || defaultShortcuts.nextField,
        prevField: document.getElementById('shortcutPrevField').value || defaultShortcuts.prevField
    };
    localStorage.setItem('pos_shortcuts', JSON.stringify(shortcuts));
    document.getElementById('shortcutSettingsModal').style.display = 'none';
    showNotification('Shortcuts saved successfully!');
}

function resetShortcuts() {
    shortcuts = { ...defaultShortcuts };
    loadShortcutInputs();
}

function recalculateItem(item) {
    item.gross = item.qty * item.price;
    item.discountAmount = item.gross * (item.discountPercent / 100);
    item.gstAmount = (item.gross - item.discountAmount) * (item.gstPercent / 100);
    item.net = item.gross - item.discountAmount + item.gstAmount;
}

function updateSummary() {
    const totalBill = currentInvoice.items.reduce((sum, item) => sum + item.gross, 0);
    const totalDiscount = currentInvoice.items.reduce((sum, item) => sum + item.discountAmount, 0);
    const totalGst = currentInvoice.items.reduce((sum, item) => sum + item.gstAmount, 0);
    
    const invoiceDiscountAmount = parseFloat(document.getElementById('invoiceDiscountAmount').value) || 0;
    const netAmount = currentInvoice.items.reduce((sum, item) => sum + item.net, 0) - invoiceDiscountAmount;
    
    const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
    
    // Update totals footer
    const totalQty = currentInvoice.items.reduce((sum, item) => sum + item.qty, 0);
    document.getElementById('totalQty').textContent = totalQty.toFixed(3);
    document.getElementById('totalGross').textContent = formatCurrency(totalBill);
    document.getElementById('totalDiscAmt').textContent = formatCurrency(totalDiscount);
    document.getElementById('totalNet').textContent = formatCurrency(currentInvoice.items.reduce((sum, item) => sum + item.net, 0));
    
    document.getElementById('totalBill').textContent = formatCurrency(totalBill);
    document.getElementById('totalDiscount').textContent = formatCurrency(totalDiscount + invoiceDiscountAmount);
    document.getElementById('totalGst').textContent = formatCurrency(totalGst);
    document.getElementById('netAmount').textContent = formatCurrency(netAmount);
    document.getElementById('summaryReceived').textContent = formatCurrency(amountReceived);
    document.getElementById('itemCount').textContent = currentInvoice.items.length;
}

function updateReturnedAndBalance() {
    const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
    const invoiceDiscountAmount = parseFloat(document.getElementById('invoiceDiscountAmount').value) || 0;
    const netAmount = currentInvoice.items.reduce((sum, item) => sum + item.net, 0) - invoiceDiscountAmount;
    
    const difference = amountReceived - netAmount;
    const amountReturned = difference > 0 ? difference : 0;
    const balance = difference < 0 ? Math.abs(difference) : 0;
    
    document.getElementById('amountReturned').value = amountReturned.toFixed(2);
    document.getElementById('summaryReturned').textContent = formatCurrency(amountReturned);
    document.getElementById('balanceAmount').textContent = formatCurrency(balance);
    
    const balanceEl = document.getElementById('balanceAmount');
    balanceEl.style.color = balance === 0 ? 'var(--success)' : 'var(--error)';
}

function updateBalanceFromReturned() {
    const amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
    const amountReturned = parseFloat(document.getElementById('amountReturned').value) || 0;
    const invoiceDiscountAmount = parseFloat(document.getElementById('invoiceDiscountAmount').value) || 0;
    const netAmount = currentInvoice.items.reduce((sum, item) => sum + item.net, 0) - invoiceDiscountAmount;
    
    const balance = (amountReceived - amountReturned) - netAmount;
    
    document.getElementById('balanceAmount').textContent = formatCurrency(balance);
    document.getElementById('summaryReturned').textContent = formatCurrency(amountReturned);
    
    const balanceEl = document.getElementById('balanceAmount');
    balanceEl.style.color = balance >= 0 ? 'var(--success)' : 'var(--error)';
}

function updateInvoiceDiscount() {
    const percentInput = document.getElementById('invoiceDiscountPercent');
    const amountInput = document.getElementById('invoiceDiscountAmount');
    const totalNet = currentInvoice.items.reduce((sum, item) => sum + item.net, 0);
    
    if (document.activeElement === percentInput) {
        const percent = parseFloat(percentInput.value) || 0;
        amountInput.value = (totalNet * percent / 100).toFixed(2);
    } else if (document.activeElement === amountInput) {
        const amount = parseFloat(amountInput.value) || 0;
        percentInput.value = totalNet > 0 ? ((amount / totalNet) * 100).toFixed(1) : 0;
    }
    
    updateSummary();
    updateReturnedAndBalance();
}

function autoCalculateAmountReturned() {
    updateReturnedAndBalance();
}

function quickPay() {
    const netAmount = currentInvoice.items.reduce((sum, item) => sum + item.net, 0);
    const invoiceDiscountAmount = parseFloat(document.getElementById('invoiceDiscountAmount').value) || 0;
    const finalAmount = netAmount - invoiceDiscountAmount;
    
    document.getElementById('amountReceived').value = finalAmount.toFixed(2);
    document.getElementById('amountReturned').value = 0;
    updateSummary();
    showNotification(`Quick Pay: ${formatCurrency(finalAmount)}`);
}

// Returns the id of the default walk-in customer ("Cash Customer"), creating it once if the tenant doesn't have one yet.
async function getWalkInCustomerId() {
    const existing = customersData.find(c => (c.customer_name || '').trim().toLowerCase() === 'cash customer');
    if (existing) return parseInt(existing.id);

    const companyId = currentInvoice.company || document.getElementById('company').value;
    if (!companyId) return null;

    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/quick-add-customer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customerName: 'Cash Customer', companyId: parseInt(companyId) })
        });
        const data = await response.json();
        if (data.success && data.customer) {
            customersData.push(data.customer);
            return parseInt(data.customer.id);
        }
    } catch (error) {
        console.error('Error creating walk-in customer:', error);
    }
    return null;
}

// After a save the next bill starts with NO customer, so the previous bill's customer
// never carries over to the next invoice and its print.
function resetCustomer() {
    currentInvoice.customer = null;
    currentInvoice.previousBalance = 'Dr 0.00';
    document.getElementById('customer').value = '';
    const search = document.getElementById('customerSearch');
    search.value = '';
    search._prevValue = '';
    const supplierManSelect = document.getElementById('supplierMan');
    if (supplierManSelect) supplierManSelect.value = '';
}

async function saveInvoice(status = 'Posted') {
    if (isSaving) return;

    // Pressing save again right after a save (or on an empty cart) prints the last saved invoice
    // (Manual Print mode only; in auto print mode the invoice is already printed on save)
    if (status !== 'Draft' && lastSavedInvoiceId && (justSaved || currentInvoice.items.length === 0) && isManualPrintMode()) {
        printLastInvoice();
        return;
    }
    // Never save the same cart twice
    if (justSaved) return;

    isSaving = true;
    
    if (currentInvoice.items.length === 0) {
        alert('Add at least one item to save invoice!');
        isSaving = false;
        return;
    }
    
    // Branch: auto-pick the first available branch if none is selected yet
    if (!currentInvoice.branch && branchesData.length > 0) {
        currentInvoice.branch = parseInt(branchesData[0].id);
        document.getElementById('branch').value = branchesData[0].id;
    }

    // Currency is not mandatory: fall back to the base currency, then the first available one
    if (!currentInvoice.currency && currenciesData.length > 0) {
        const fallbackCurrency = currenciesData.find(c => c.is_base_currency == 1) || currenciesData[0];
        currentInvoice.currency = fallbackCurrency.currency_id;
    }

    // No customer selected = walk-in sale: post it against the default "Cash Customer" account
    // (pos-add.php requires a customer for tax, receive voucher and ledger). The receipt hides
    // the Cash Customer name, so a walk-in bill prints without any customer.
    const invoiceCustomerId = currentInvoice.customer || await getWalkInCustomerId();
    if (!invoiceCustomerId) {
        alert('Walk-in (Cash Customer) could not be set up. Please select a customer!');
        isSaving = false;
        return;
    }

    if (!currentInvoice.branch) {
        alert('No branch available. Please add a branch first!');
        isSaving = false;
        return;
    }
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    if (paymentMethod === 'bank_transfer' && !document.getElementById('bankAccount').value) {
        alert('Please select a bank account!');
        isSaving = false;
        return;
    }
    
    // Check stock for all items - warn only, never block saving
    const stockWarnings = [];
    for (const item of currentInvoice.items) {
        if (item.productId) {
            try {
                const response = await fetch(`../../../../server/api/sale/pos_invoice/get-product-stock.php?product_id=${item.productId}&branch_id=${currentInvoice.branch}`);
                const data = await response.json();
                
                if (data.success) {
                    const availableStock = Math.floor(data.stock);
                    if (availableStock < item.qty) {
                        stockWarnings.push(`${item.product} (Available: ${availableStock}, Required: ${item.qty})`);
                    }
                }
            } catch (error) {
                console.error('Error validating stock:', error);
            }
        }
    }
    if (stockWarnings.length > 0) {
        showNotification('Stock warning - invoice will still be saved:\n' + stockWarnings.join('\n'), true);
    }
    
    const invoiceData = {
        saleDate: new Date().toISOString().split('T')[0],
        customerId: invoiceCustomerId,
        branchId: currentInvoice.branch,
        currencyId: currentInvoice.currency,
        projectId: currentInvoice.project || null,
        counterId: document.getElementById('counterSelect')?.value || null,
        salesOfficerId: userEmployeeId,
        previousBalance: currentInvoice.previousBalance || 'Dr 0.00',
        paymentMethod: document.getElementById('paymentMethod').value || 'cash',
        bankAccountId: document.getElementById('paymentMethod').value === 'bank_transfer' ? document.getElementById('bankAccount').value : null,
        totalBill: currentInvoice.items.reduce((sum, item) => sum + item.gross, 0),
        totalDiscountAmount: parseFloat(document.getElementById('invoiceDiscountAmount').value) || 0,
        netAmount: currentInvoice.items.reduce((sum, item) => sum + item.net, 0) - (parseFloat(document.getElementById('invoiceDiscountAmount').value) || 0),
        amountPaid: parseFloat(document.getElementById('amountReceived').value) || 0,
        amountReturned: parseFloat(document.getElementById('amountReturned').value) || 0,
        items: currentInvoice.items.flatMap((item, index) => {
            const rowNumber = index + 1;
            const parentItem = {
                productId: item.productId,
                uomId: item.uomId,
                quantity: item.qty,
                salePrice: item.price,
                grossAmount: item.gross,
                discountPercent: item.discountPercent,
                discountAmount: item.discountAmount,
                gstPercent: item.gstPercent,
                gstAmount: item.gstAmount,
                netAmount: item.net,
                stockAffects: 1,
                invoiceAffects: 1,
                parentRowId: null
            };
            
            if (item.children && item.children.length > 0) {
                const childItems = item.children.map(child => ({
                    productId: child.id,
                    uomId: child.uomId || 9,
                    quantity: child.qty,
                    salePrice: child.price,
                    grossAmount: child.qty * child.price,
                    discountPercent: 0,
                    discountAmount: 0,
                    gstPercent: 0,
                    gstAmount: 0,
                    netAmount: child.qty * child.price,
                    stockAffects: child.stock_affects,
                    invoiceAffects: child.invoice_affects,
                    parentRowId: rowNumber
                }));
                return [parentItem, ...childItems];
            }
            return [parentItem];
        }),
        status: status === 'Draft' ? 'Draft' : 'Posted'
    };
    
    try {
        const response = await fetch('../../../../server/api/sale/pos_invoice/pos-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(invoiceData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Invoice saved successfully!');
            lastSavedInvoiceId = data.invoice_id;
            justSaved = true;
            if (isManualPrintMode()) {
                // Manual print mode: no auto print, just show the PRINT button
                showPrintButton();
            } else {
                // Redirect to thermal print
                hidePrintButton();
                window.open(`thermal-print.php?id=${data.invoice_id}&autoprint=1`, '_blank');
            }
            setTimeout(() => {
                currentInvoice.items = [];
                justSaved = false;
                renderItems();
                updateSummary();
                loadNextInvoiceNo();
                resetCustomer();
                document.getElementById('invoiceDiscountPercent').value = 0;
                document.getElementById('invoiceDiscountAmount').value = 0;
                document.getElementById('amountReceived').value = 0;
                document.getElementById('amountReturned').value = 0;
                // Manual print mode: ready for the next invoice, focus back on product
                if (isManualPrintMode()) document.getElementById('quickProduct').focus();
            }, 500);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error saving invoice: ' + error.message);
    } finally {
        isSaving = false;
    }
}

function formatCurrency(amount) {
    const currencySelect = document.getElementById('currency');
    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
    const currencyCode = selectedOption ? selectedOption.value : 'USD';
    
    // Get symbol from currencies data
    const currency = currenciesData.find(c => c.code === currencyCode);
    const symbol = currency ? currency.symbol : '$';
    
    return `${symbol}${Math.abs(amount).toFixed(2)}`;
}

function showNotification(message, isWarning = false) {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: ${isWarning ? '70px' : '20px'};
        right: 20px;
        background: ${isWarning ? 'var(--warning)' : 'var(--success)'};
        color: ${isWarning ? '#1a1a1a' : 'white'};
        padding: 8px 16px;
        border-radius: 4px;
        z-index: 10000;
        font-weight: 500;
        white-space: pre-line;
        max-width: 360px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), isWarning ? 6000 : 3000);
}

async function loadSupplierMen() {
    const response = await fetch('../../../../server/api/sale/pos_invoice/get-supplier-men.php');
    const data = await response.json();
    if (data.success) {
        supplierMenData = data.supplierMen;
        const supplierManSelect = document.getElementById('supplierMan');
        supplierManSelect.innerHTML = '<option value="">Select Supplier Man</option>';
        data.supplierMen.forEach(sm => {
            const option = document.createElement('option');
            option.value = sm.id;
            option.textContent = `${sm.employee_id} - ${sm.full_name}`;
            supplierManSelect.appendChild(option);
        });
    }
}

async function loadProjects() {
    try {
        const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const data = await response.json();
        if (data.success) {
            projectsData = data.data;
            const projectSelect = document.getElementById('project');
            projectSelect.innerHTML = '<option value="">Select Project</option>';
            data.data.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                const label = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
                option.textContent = label;
                projectSelect.appendChild(option);
            });
            
            const savedProjectId = localStorage.getItem('lastSelectedProject');
            if (savedProjectId && data.data.find(p => p.id == savedProjectId)) {
                projectSelect.value = savedProjectId;
                currentInvoice.project = parseInt(savedProjectId);
            }
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}