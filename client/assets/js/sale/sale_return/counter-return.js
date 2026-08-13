let currentReturn = { items: [], customer: null, branch: null, currency: null, salesOfficer: null, supplierMan: null, subAccount: null, company: null, project: null, invoiceLevelTaxes: [], invoiceTotalBill: null, invoiceDiscountAmount: 0, extraDiscount1Amount: 0, extraDiscount2Amount: 0, shippingFees: 0 };
let productsData = [], customersData = [], branchesData = [], currenciesData = [], bankAccountsData = [], uomData = [], invoicesData = [], employeesData = [], subAccountsData = [], companiesData = [], projectsData = [];

const defaultShortcuts = { product: 'F1', customer: 'F2', branch: 'F3', qty: 'F4', save: 'F10', clear: 'F12' };
let shortcuts = { ...defaultShortcuts };

const urlParams = new URLSearchParams(window.location.search);
const editId = urlParams.get('edit');
const isEditMode = !!editId;

document.addEventListener('DOMContentLoaded', async function() {
    await Promise.all([loadProducts(), loadCustomers(), loadCurrencies(), loadBankAccounts(), loadUOM(), loadSaleInvoices(), loadEmployees(), loadCompanies(), loadProjects()]);
    loadShortcuts();
    updateDate();
    await loadNextReturnNo();
    
    
    initDropdown('branchSearch', 'branchOptions', 'branch', branchesData, (b) => `${b.branch_code} - ${b.branch_name} (${b.branch_type})`, (id) => { currentReturn.branch = parseInt(id); localStorage.setItem('lastSelectedBranch', id); });
    // Sync branchesData loaded by loadCompanies into the dropdown
    document.getElementById('branchSearch').updateData(branchesData);
    initDropdown('customerSearch', 'customerOptions', 'customer', customersData, (c) => `${c.customer_code} - ${c.customer_name}`, (id) => { 
        currentReturn.customer = parseInt(id); 
        
        // Auto-populate Supplier Man
        const customer = customersData.find(cust => cust.id == id);
        if (customer && customer.supplier_man_id) {
            const supplierMan = employeesData.find(e => e.id == customer.supplier_man_id);
            if (supplierMan) {
                document.getElementById('supplierManSearch').value = `${supplierMan.employee_id} - ${supplierMan.full_name}`;
                document.getElementById('supplierMan').value = customer.supplier_man_id;
                currentReturn.supplierMan = parseInt(customer.supplier_man_id);
            }
        }
        
        filterInvoicesByCustomer(id); 
        loadSubAccounts(id); 
    });
    initDropdown('subAccountSearch', 'subAccountOptions', 'subAccount', subAccountsData, (s) => s.sub_account_name, (id) => currentReturn.subAccount = parseInt(id));
    initDropdown('invoiceSearch', 'invoiceOptions', 'saleInvoice', invoicesData, (i) => `${i.bill_no} - ${i.customer_name} (${i.sale_date})`, (id) => loadInvoiceData(id));
    initDropdown('salesOfficerSearch', 'salesOfficerOptions', 'salesOfficer', employeesData, (e) => `${e.employee_id} - ${e.full_name}`, (id) => currentReturn.salesOfficer = parseInt(id));
    initDropdown('supplierManSearch', 'supplierManOptions', 'supplierMan', employeesData, (e) => `${e.employee_id} - ${e.full_name}`, (id) => currentReturn.supplierMan = parseInt(id));
    initDropdown('projectSearch', 'projectOptions', 'project', projectsData, (p) => `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`, (id) => { currentReturn.project = parseInt(id); localStorage.setItem('lastSelectedProject', id); });
    
    const savedProjectId = localStorage.getItem('lastSelectedProject');
    if (savedProjectId && projectsData.find(p => p.id == savedProjectId)) {
        const project = projectsData.find(p => p.id == savedProjectId);
        document.getElementById('projectSearch').value = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
        document.getElementById('project').value = savedProjectId;
        currentReturn.project = parseInt(savedProjectId);
    }
    
    const savedBranchId = localStorage.getItem('lastSelectedBranch');
    if (savedBranchId && branchesData.find(b => b.id == savedBranchId)) {
        const branch = branchesData.find(b => b.id == savedBranchId);
        document.getElementById('branchSearch').value = `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
        document.getElementById('branch').value = savedBranchId;
        currentReturn.branch = parseInt(savedBranchId);
    }
    
    const savedSalesOfficer = localStorage.getItem('lastSelectedSalesOfficer');
    if (savedSalesOfficer && employeesData.find(e => e.id == savedSalesOfficer)) {
        const officer = employeesData.find(e => e.id == savedSalesOfficer);
        document.getElementById('salesOfficerSearch').value = `${officer.employee_id} - ${officer.full_name}`;
        document.getElementById('salesOfficer').value = savedSalesOfficer;
        currentReturn.salesOfficer = parseInt(savedSalesOfficer);
    } else if (userEmployeeId) {
        const currentUser = employeesData.find(e => e.id == userEmployeeId);
        if (currentUser) {
            document.getElementById('salesOfficerSearch').value = `${currentUser.employee_id} - ${currentUser.full_name}`;
            document.getElementById('salesOfficer').value = currentUser.id;
            currentReturn.salesOfficer = currentUser.id;
        }
    }
    
    const baseCurrency = currenciesData.find(c => c.is_base_currency == 1);
    if (baseCurrency) currentReturn.currency = baseCurrency.currency_id;

    // Default to Cash Customer so the counter clerk doesn't have to pick a customer for a walk-in sale return.
    // Must run after all initDropdown(...) calls above — filterInvoicesByCustomer()/loadSubAccounts() call
    // .updateData() on the invoiceSearch/subAccountSearch inputs, which initDropdown() is what defines it.
    if (!isEditMode) {
        const cashCustomer = customersData.find(c => c.customer_name === 'Cash Customer');
        if (cashCustomer) {
            document.getElementById('customerSearch').value = `${cashCustomer.customer_code} - ${cashCustomer.customer_name}`;
            document.getElementById('customer').value = cashCustomer.id;
            currentReturn.customer = parseInt(cashCustomer.id);

            if (cashCustomer.supplier_man_id) {
                const supplierMan = employeesData.find(e => e.id == cashCustomer.supplier_man_id);
                if (supplierMan) {
                    document.getElementById('supplierManSearch').value = `${supplierMan.employee_id} - ${supplierMan.full_name}`;
                    document.getElementById('supplierMan').value = cashCustomer.supplier_man_id;
                    currentReturn.supplierMan = parseInt(cashCustomer.supplier_man_id);
                }
            }

            filterInvoicesByCustomer(cashCustomer.id);
            loadSubAccounts(cashCustomer.id);
        }
    }


    document.getElementById('paymentMethod').addEventListener('change', function() {
        document.getElementById('bankAccountContainer').classList.toggle('hidden', this.value !== 'bank_transfer');
    });
    
    document.getElementById('quickProduct').addEventListener('input', handleProductSearch);
    document.getElementById('quickProduct').addEventListener('keydown', handleProductKeydown);
    document.getElementById('quickQty').addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('quickAddBtn').click(); } });
    document.getElementById('quickAddBtn').addEventListener('click', addProductFromQuick);
    
    document.getElementById('amountRefunded').addEventListener('input', updateSummary);
    document.getElementById('returnDiscountPercent').addEventListener('input', updateReturnDiscount);
    document.getElementById('returnDiscountAmount').addEventListener('input', updateReturnDiscount);
    
    document.getElementById('saveReturnBtn').addEventListener('click', saveReturn);
    document.getElementById('clearBtn').addEventListener('click', clearReturn);
    
    if (isEditMode) {
        loadEditData(editId);
        document.getElementById('saveReturnBtn').innerHTML = '<i class="fas fa-save"></i> UPDATE (F10)';
    }
    
    document.addEventListener('keydown', (e) => {
        if (document.getElementById('shortcutsModal').style.display === 'flex') return;
        
        let key = e.key;
        if (key.length === 1 && key.match(/[a-z]/i)) key = key.toUpperCase();
        let combo = '';
        if (e.ctrlKey) combo += 'Ctrl+';
        if (e.altKey) combo += 'Alt+';
        if (e.shiftKey && key.length > 1) combo += 'Shift+';
        combo += key;
        
        if (combo === shortcuts.save) { e.preventDefault(); saveReturn(); }
        else if (combo === shortcuts.customer) { e.preventDefault(); document.getElementById('customerSearch').focus(); }
        else if (combo === shortcuts.product) { e.preventDefault(); document.getElementById('quickProduct').focus(); }
        else if (combo === shortcuts.branch) { e.preventDefault(); document.getElementById('branchSearch').focus(); }
        else if (combo === shortcuts.qty) { e.preventDefault(); document.getElementById('quickQty').focus(); }
        else if (combo === shortcuts.clear) { e.preventDefault(); clearReturn(); }
    });
    
    ['shortcutProduct', 'shortcutCustomer', 'shortcutBranch', 'shortcutQty', 'shortcutSave', 'shortcutClear'].forEach(id => {
        const input = document.getElementById(id);
        input.addEventListener('keydown', function(e) {
            e.preventDefault();
            let key = e.key;
            if (key.length === 1) key = key.toUpperCase();
            const parts = [];
            if (e.ctrlKey) parts.push('Ctrl');
            if (e.altKey) parts.push('Alt');
            if (e.shiftKey && key.length > 1) parts.push('Shift');
            if (key !== 'Control' && key !== 'Alt' && key !== 'Shift' && key !== 'Meta') parts.push(key);
            if (parts.length > 0) this.value = parts.join('+');
        });
    });
    
    document.getElementById('saveShortcutsBtn').addEventListener('click', saveShortcuts);
    document.getElementById('resetShortcutsBtn').addEventListener('click', resetShortcuts);
    
    document.getElementById('shortcutsBtn').addEventListener('click', () => {
        document.getElementById('shortcutsModal').style.display = 'flex';
    });
    
    document.getElementById('closeShortcutsBtn').addEventListener('click', () => {
        document.getElementById('shortcutsModal').style.display = 'none';
    });
    
    document.getElementById('shortcutsModal').addEventListener('click', (e) => {
        if (e.target.id === 'shortcutsModal') {
            document.getElementById('shortcutsModal').style.display = 'none';
        }
    });
    
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#branchSearch') && !e.target.closest('#branchOptions')) document.getElementById('branchOptions').style.display = 'none';
        if (!e.target.closest('#customerSearch') && !e.target.closest('#customerOptions')) document.getElementById('customerOptions').style.display = 'none';
        if (!e.target.closest('#subAccountSearch') && !e.target.closest('#subAccountOptions')) document.getElementById('subAccountOptions').style.display = 'none';
        if (!e.target.closest('#invoiceSearch') && !e.target.closest('#invoiceOptions')) document.getElementById('invoiceOptions').style.display = 'none';
        if (!e.target.closest('#salesOfficerSearch') && !e.target.closest('#salesOfficerOptions')) document.getElementById('salesOfficerOptions').style.display = 'none';
        if (!e.target.closest('#supplierManSearch') && !e.target.closest('#supplierManOptions')) document.getElementById('supplierManOptions').style.display = 'none';
        if (!e.target.closest('#projectSearch') && !e.target.closest('#projectOptions')) document.getElementById('projectOptions').style.display = 'none';
        if (!e.target.closest('.quick-entry')) document.getElementById('productSuggestions').style.display = 'none';
    });
});

function initDropdown(searchId, optionsId, hiddenId, data, textFn, onSelect) {
    const search = document.getElementById(searchId);
    const options = document.getElementById(optionsId);
    const hidden = document.getElementById(hiddenId);
    let selectedIndex = -1;
    let currentData = data;
    
    // Store reference for external filtering
    search.updateData = (newData) => {
        currentData = newData;
        selectedIndex = -1;
        renderOptions(currentData, options, textFn, (id, text) => { search.value = text; hidden.value = id; onSelect(id); options.style.display = 'none'; });
    };
    
    search.addEventListener('focus', () => { 
        selectedIndex = -1;
        renderOptions(currentData, options, textFn, (id, text) => { 
            search.value = text; 
            hidden.value = id; 
            onSelect(id); 
            options.style.display = 'none';
            if (searchId === 'salesOfficerSearch') localStorage.setItem('lastSelectedSalesOfficer', id);
        }); 
        options.style.display = 'block'; 
    });
    
    search.addEventListener('input', () => {
        const term = search.value.toLowerCase();
        const filtered = currentData.filter(d => textFn(d).toLowerCase().includes(term));
        selectedIndex = -1;
        renderOptions(filtered, options, textFn, (id, text) => { 
            search.value = text; 
            hidden.value = id; 
            onSelect(id); 
            options.style.display = 'none';
            if (searchId === 'salesOfficerSearch') localStorage.setItem('lastSelectedSalesOfficer', id);
        });
        options.style.display = filtered.length > 0 ? 'block' : 'none';
    });
    
    search.addEventListener('keydown', (e) => {
        const items = options.querySelectorAll('.dropdown-option');
        if (items.length === 0) return;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (selectedIndex >= 0) items[selectedIndex].style.background = '';
            selectedIndex = (selectedIndex + 1) % items.length;
            items[selectedIndex].style.background = 'var(--surface-1)';
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (selectedIndex >= 0) items[selectedIndex].style.background = '';
            selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
            items[selectedIndex].style.background = 'var(--surface-1)';
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            items[selectedIndex].click();
        }
    });
}

function renderOptions(data, container, textFn, onClick) {
    container.innerHTML = '';
    data.forEach(item => {
        const div = document.createElement('div');
        div.className = 'dropdown-option';
        div.textContent = textFn(item);
        div.onclick = () => onClick(item.id, textFn(item));
        container.appendChild(div);
    });
}

function handleProductSearch(e) {
    const term = e.target.value.trim().toLowerCase();
    const suggestions = document.getElementById('productSuggestions');
    
    if (term.length < 2) { suggestions.style.display = 'none'; return; }
    
    const matches = productsData.filter(p => p.name.toLowerCase().includes(term) || p.code.toLowerCase().includes(term) || (p.barcode && p.barcode.toLowerCase() === term) || (p.qr_code && p.qr_code.toLowerCase() === term));
    
    if (matches.length === 0) { suggestions.style.display = 'none'; }
    else if (matches.length === 1 && ((matches[0].barcode && matches[0].barcode.toLowerCase() === term) || (matches[0].qr_code && matches[0].qr_code.toLowerCase() === term))) {
        e.target.value = matches[0].name;
        suggestions.style.display = 'none';
        setTimeout(() => { document.getElementById('quickQty').focus(); document.getElementById('quickQty').select(); }, 100);
    } else {
        suggestions.innerHTML = '';
        matches.forEach(p => {
            const div = document.createElement('div');
            div.className = 'suggestion-item';
            div.innerHTML = `<strong>${p.name}</strong><br><small style="color: var(--subtext);">${p.code} - ${p.trade_price}</small>`;
            div.onclick = () => { e.target.value = p.name; suggestions.style.display = 'none'; document.getElementById('quickQty').focus(); };
            div.addEventListener('mouseenter', function() {
                suggestions.querySelectorAll('.suggestion-item').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
            });
            suggestions.appendChild(div);
        });
        suggestions.style.display = 'block';
    }
}

function handleProductKeydown(e) {
    const suggestions = document.getElementById('productSuggestions');
    if (e.key === 'Enter') {
        e.preventDefault();
        if (suggestions.style.display === 'block') {
            const selected = suggestions.querySelector('.suggestion-item.selected') || suggestions.querySelector('.suggestion-item');
            if (selected) selected.click();
        } else {
            const term = this.value.trim().toLowerCase();
            const product = productsData.find(p => (p.barcode && p.barcode.toLowerCase() === term) || (p.qr_code && p.qr_code.toLowerCase() === term));
            if (product) { this.value = product.name; document.getElementById('quickQty').focus(); }
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
        if (selected) { selected.classList.remove('selected'); selected.style.background = 'white'; }
        currentIndex = (currentIndex + 1) % items.length;
        items[currentIndex].classList.add('selected');
        items[currentIndex].style.background = 'var(--surface-1)';
        items[currentIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (selected) { selected.classList.remove('selected'); selected.style.background = 'white'; }
        currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
        items[currentIndex].classList.add('selected');
        items[currentIndex].style.background = 'var(--surface-1)';
        items[currentIndex].scrollIntoView({ block: 'nearest' });
    }
}

function addProductFromQuick() {
    const productInput = document.getElementById('quickProduct').value.trim();
    if (!productInput) return;
    
    const product = productsData.find(p => p.name.toLowerCase().includes(productInput.toLowerCase()) || p.code.toLowerCase().includes(productInput.toLowerCase()) || (p.barcode && p.barcode.toLowerCase() === productInput.toLowerCase()) || (p.qr_code && p.qr_code.toLowerCase() === productInput.toLowerCase()));
    
    if (product) {
        const priceType = document.querySelector('input[name="priceType"]:checked')?.value || 'tp';
        const uomDetails = getProductUOMDetails(product);
        const quickQty = parseFloat(document.getElementById('quickQty').value) || 1;
        
        // Find base unit (conversion factor = 1) or use first unit
        const baseUnitIndex = uomDetails.units.findIndex(u => u.conversionFactor === 1);
        const targetIndex = baseUnitIndex >= 0 ? baseUnitIndex : 0;
        
        const item = {
            id: Date.now(),
            product: product.name,
            productId: product.id,
            units: uomDetails.units.map((unit, index) => ({
                id: unit.id,
                name: unit.name,
                conversionFactor: unit.conversionFactor,
                qty: index === targetIndex ? quickQty : 0
            })),
            price: parseFloat(priceType === 'mrp' ? product.sale_price : product.trade_price),
            discountPercent: 0,
            taxPercent: 0,
            status: 'sellable',
            tradeOfferAmount: 0,
            focQuantity: 0
        };
        
        item.qty = calculateTotalQty(item);
        item.gross = item.qty * item.price;
        item.discountAmount = item.gross * (item.discountPercent / 100);
        item.taxAmount = (item.gross - item.discountAmount) * (item.taxPercent / 100);
        item.net = item.gross - item.discountAmount + item.taxAmount;
        
        currentReturn.items.push(item);
        recalculateMaxColumns();
        renderItems();
        updateSummary();
        
        document.getElementById('quickProduct').value = '';
        document.getElementById('quickQty').value = 1;
        document.getElementById('productSuggestions').style.display = 'none';
        document.getElementById('quickProduct').focus();
    }
}

function renderItems() {
    const tbody = document.getElementById('itemsBody');
    tbody.innerHTML = '';
    
    // Update headers and footer
    updateTableHeaders();
    
    currentReturn.items.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'item-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = getGridTemplate();
        row.style.gap = '4px';
        row.style.alignItems = 'center';
        
        let html = `
            <div class="text-center">${index + 1}</div>
            <div>${item.product}</div>
        `;
        
        // Add unit inputs
        for (let i = 0; i < maxUnitColumns; i++) {
            if (i < item.units.length) {
                const unit = item.units[i];
                html += `
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <div style="font-size: 9px; color: var(--subtext); text-align: center;">${unit.name}</div>
                        <input type="number" value="${unit.qty}" min="0" step="0.01" class="text-right" 
                            style="height: 24px; padding: 0 4px; font-size: 11px;" 
                            oninput="updateUnitQty(${item.id}, ${i}, this.value)">
                    </div>
                `;
            } else {
                html += `<div class="text-center" style="color: var(--subtext); font-size: 11px;">-</div>`;
            }
        }
        
        html += `
            <input type="number" value="${item.price.toFixed(2)}" step="0.01" class="text-right" oninput="updateItemPrice(${item.id}, this.value)">
            <div class="readonly">${formatCurrency(item.gross)}</div>
            <input type="number" value="${item.discountPercent}" step="0.1" class="text-right" oninput="updateItemDiscount(${item.id}, this.value)">
            <input type="number" value="${item.taxPercent}" step="0.1" class="text-right" oninput="updateItemTaxPercent(${item.id}, this.value)">
            <div class="readonly">${formatCurrency(item.taxAmount)}</div>
            <select onchange="updateItemStatus(${item.id}, this.value)">
                <option value="sellable" ${item.status === 'sellable' ? 'selected' : ''}>Sellable</option>
                <option value="damaged" ${item.status === 'damaged' ? 'selected' : ''}>Damaged</option>
            </select>
            <input type="number" value="${item.tradeOfferAmount.toFixed(2)}" step="0.01" class="text-right" oninput="updateItemTradeOfferAmount(${item.id}, this.value)">
            <input type="number" value="${item.focQuantity}" step="0.01" class="text-right" oninput="updateItemFocQuantity(${item.id}, this.value)">
            <div class="readonly">${formatCurrency(item.net)}</div>
            <div class="text-center">
                <button class="btn btn-danger btn-micro" onclick="removeItem(${item.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        row.innerHTML = html;
        tbody.appendChild(row);
    });
    
    updateFooterTotals();
}

window.updateUnitQty = function(itemId, unitIndex, newQty) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item && item.units[unitIndex]) {
        item.units[unitIndex].qty = parseFloat(newQty) || 0;
        item.qty = calculateTotalQty(item);
        currentReturn.invoiceTotalBill = null;
        recalculateItem(item);
        updateFooterTotals();
        updateSummary();
    }
};

window.updateItemQty = function(itemId, newQty) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) { item.qty = parseFloat(newQty) || 1; currentReturn.invoiceTotalBill = null; recalculateItem(item); updateSummary(); }
};

window.updateItemPrice = function(itemId, newPrice) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) { item.price = parseFloat(newPrice) || 0; currentReturn.invoiceTotalBill = null; recalculateItem(item); updateFooterTotals(); updateSummary(); }
};

window.updateItemDiscount = function(itemId, newPercent) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) { item.discountPercent = parseFloat(newPercent) || 0; currentReturn.invoiceTotalBill = null; recalculateItem(item); updateFooterTotals(); updateSummary(); }
};

window.updateItemTaxPercent = function(itemId, newPercent) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) { item.taxPercent = parseFloat(newPercent) || 0; currentReturn.invoiceTotalBill = null; recalculateItem(item); updateFooterTotals(); updateSummary(); }
};

window.updateItemStatus = function(itemId, newStatus) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) item.status = newStatus;
};

window.updateItemTradeOfferAmount = function(itemId, newAmount) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) {
        currentReturn.invoiceTotalBill = null;
        item.tradeOfferAmount = parseFloat(newAmount) || 0;
        recalculateItem(item);
        updateFooterTotals();
        updateSummary();
    }
};

window.updateItemFocQuantity = function(itemId, newQty) {
    const item = currentReturn.items.find(i => i.id === itemId);
    if (item) item.focQuantity = parseFloat(newQty) || 0;
};

window.removeItem = function(itemId) {
    currentReturn.items = currentReturn.items.filter(i => i.id !== itemId);
    currentReturn.invoiceTotalBill = null;
    renderItems();
    updateSummary();
};

function recalculateItem(item) {
    item.gross = item.qty * item.price;
    item.discountAmount = item.gross * (item.discountPercent / 100);
    item.taxAmount = (item.gross - item.discountAmount) * (item.taxPercent / 100);
    item.net = item.gross - item.discountAmount + item.taxAmount - (item.tradeOfferAmount || 0);

    // Update readonly display cells in-place
    const tbody = document.getElementById('itemsBody');
    if (!tbody) return;
    tbody.querySelectorAll('.item-row').forEach(row => {
        const inputs = row.querySelectorAll('input');
        // Find row by checking if any input has oninput referencing this item.id
        const belongs = Array.from(inputs).some(inp => (inp.getAttribute('oninput') || '').includes('(' + item.id + ','));
        if (!belongs) return;
        const readonlyCells = row.querySelectorAll('.readonly');
        if (readonlyCells[0]) readonlyCells[0].textContent = formatCurrency(item.gross);
        if (readonlyCells[1]) readonlyCells[1].textContent = formatCurrency(item.taxAmount);
        if (readonlyCells[2]) readonlyCells[2].textContent = formatCurrency(item.net);
    });
}

function updateSummary() {
    const totalBill = currentReturn.invoiceTotalBill != null ? currentReturn.invoiceTotalBill : currentReturn.items.reduce((sum, item) => sum + item.net, 0);
    const returnDiscountAmount = parseFloat(document.getElementById('returnDiscountAmount').value) || 0;
    const ed1 = currentReturn.extraDiscount1Amount || 0;
    const ed2 = currentReturn.extraDiscount2Amount || 0;
    const shipping = currentReturn.shippingFees || 0;
    const itemsNetTotal = currentReturn.items.reduce((sum, item) => sum + item.net, 0);
    const netAmount = totalBill - returnDiscountAmount - ed1 - ed2 + shipping;
    const amountRefunded = parseFloat(document.getElementById('amountRefunded').value) || 0;
    const balance = netAmount - amountRefunded;

    document.getElementById('totalGross').textContent = formatCurrency(currentReturn.items.reduce((sum, item) => sum + item.gross, 0));
    document.getElementById('totalNet').textContent = formatCurrency(currentReturn.items.reduce((sum, item) => sum + item.net, 0));

    document.getElementById('totalBill').textContent = formatCurrency(totalBill);
    const itemsDiscountTotal = currentReturn.items.reduce((sum, item) => sum + (item.discountAmount || 0), 0);
    document.getElementById('totalDiscount').textContent = formatCurrency(itemsDiscountTotal + returnDiscountAmount);

    // Extra Discount 1
    const ed1Item = document.getElementById('extraDiscount1Item');
    if (ed1 > 0) {
        ed1Item.style.display = '';
        document.getElementById('extraDiscount1Amt').textContent = formatCurrency(ed1);
    } else {
        ed1Item.style.display = 'none';
    }

    // Extra Discount 2
    const ed2Item = document.getElementById('extraDiscount2Item');
    if (ed2 > 0) {
        ed2Item.style.display = '';
        document.getElementById('extraDiscount2Amt').textContent = formatCurrency(ed2);
    } else {
        ed2Item.style.display = 'none';
    }

    // Shipping Fees
    const shippingItem = document.getElementById('shippingFeesItem');
    if (shipping > 0) {
        shippingItem.style.display = '';
        document.getElementById('shippingFeesAmt').textContent = formatCurrency(shipping);
    } else {
        shippingItem.style.display = 'none';
    }

    document.getElementById('netAmount').textContent = formatCurrency(netAmount);
    document.getElementById('summaryRefunded').textContent = formatCurrency(amountRefunded);
    document.getElementById('balanceAmount').textContent = formatCurrency(balance);
    document.getElementById('itemCount').textContent = currentReturn.items.length;

    document.getElementById('balanceAmount').style.color = balance >= 0 ? 'var(--error)' : 'var(--success)';
    updateNetReceivable();
}

function updateTableHeaders() {
    const header = document.getElementById('itemsHeader');
    header.innerHTML = '';
    header.style.display = 'grid';
    header.style.gridTemplateColumns = getGridTemplate();
    header.style.gap = '4px';
    
    let html = `
        <div class="col-header text-center">#</div>
        <div class="col-header">PRODUCT</div>
    `;
    
    for (let i = 0; i < maxUnitColumns; i++) {
        html += `<div class="col-header text-center">UNIT ${i + 1}</div>`;
    }
    
    html += `
        <div class="col-header text-right">PRICE</div>
        <div class="col-header text-right">GROSS</div>
        <div class="col-header text-right">DISC%</div>
        <div class="col-header text-right">TAX%</div>
        <div class="col-header text-right">TAX AMT</div>
        <div class="col-header text-center">STATUS</div>
        <div class="col-header text-right">T.O AMT</div>
        <div class="col-header text-right">FOC QTY</div>
        <div class="col-header text-right">NET</div>
        <div class="col-header text-center">ACT</div>
    `;
    
    header.innerHTML = html;
}

function updateFooterTotals() {
    const footer = document.getElementById('itemsFooter');
    footer.style.display = 'grid';
    footer.style.gridTemplateColumns = getGridTemplate();
    
    let html = `
        <div></div>
        <div>TOTALS</div>
    `;
    
    // Calculate unit totals
    for (let i = 0; i < maxUnitColumns; i++) {
        let total = 0;
        currentReturn.items.forEach(item => {
            if (item.units && item.units[i]) {
                total += item.units[i].qty || 0;
            }
        });
        html += `<div class="text-right">${total.toFixed(2)}</div>`;
    }
    
    html += `
        <div></div>
        <div class="text-right" id="totalGross">0.00</div>
        <div></div>
        <div></div>
        <div class="text-right" id="totalTaxPercent">0.00</div>
        <div class="text-right" id="totalTaxAmount">0.00</div>
        <div></div>
        <div class="text-right" id="totalToAmt">0.00</div>
        <div class="text-right" id="totalFocQty">0.00</div>
        <div class="text-right" id="totalNet">0.00</div>
        <div></div>
    `;
    
    footer.innerHTML = html;
    
    // Update totals
    const totalBill = currentReturn.items.reduce((sum, item) => sum + item.gross, 0);
    const totalNet = currentReturn.items.reduce((sum, item) => sum + item.net, 0);
    const totalToAmt = currentReturn.items.reduce((sum, item) => sum + (item.tradeOfferAmount || 0), 0);
    const totalFocQty = currentReturn.items.reduce((sum, item) => sum + (item.focQuantity || 0), 0);
    document.getElementById('totalGross').textContent = formatCurrency(totalBill);
    document.getElementById('totalToAmt').textContent = formatCurrency(totalToAmt);
    document.getElementById('totalFocQty').textContent = totalFocQty.toFixed(2);
    document.getElementById('totalNet').textContent = formatCurrency(totalNet);
}

function getGridTemplate() {
    const baseColumns = '30px 120px';
    const unitColumns = ' 70px'.repeat(maxUnitColumns);
    const endColumns = ' 70px 70px 70px 70px 70px 60px 70px 70px 80px 40px';
    return baseColumns + unitColumns + endColumns;
}

function updateReturnDiscount() {
    const percentInput = document.getElementById('returnDiscountPercent');
    const amountInput = document.getElementById('returnDiscountAmount');
    const totalNet = currentReturn.items.reduce((sum, item) => sum + item.net, 0);
    
    if (document.activeElement === percentInput) {
        const percent = parseFloat(percentInput.value) || 0;
        amountInput.value = (totalNet * percent / 100).toFixed(2);
    } else if (document.activeElement === amountInput) {
        const amount = parseFloat(amountInput.value) || 0;
        percentInput.value = totalNet > 0 ? ((amount / totalNet) * 100).toFixed(1) : 0;
    }
    updateSummary();
}

async function saveReturn() {
    if (currentReturn.items.length === 0) { alert('Add at least one item!'); return; }
    if (!currentReturn.company) { alert('Please select company!'); return; }
    if (!currentReturn.customer || !currentReturn.branch || !currentReturn.currency) { alert('Please select customer and branch!'); return; }
    if (!currentReturn.salesOfficer) { alert('Please select sales officer!'); return; }
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    if (paymentMethod === 'bank_transfer' && !document.getElementById('bankAccount').value) { alert('Please select a bank account!'); return; }
    
    const supplierManId = parseInt(document.getElementById('supplierMan').value) || currentReturn.supplierMan || null;
    
    const returnData = {
        invoice_id: isEditMode ? editId : undefined,
        saleDate: document.getElementById('currentDate').value,
        companyId: currentReturn.company,
        projectId: currentReturn.project,
        customerId: currentReturn.customer,
        branchId: currentReturn.branch,
        currencyId: currentReturn.currency,
        salesOfficerId: currentReturn.salesOfficer,
        supplierManId: supplierManId,
        subAccountId: currentReturn.subAccount,
        saleInvoiceId: document.getElementById('saleInvoice').value || null,
        invoiceLevelTaxes: currentReturn.invoiceLevelTaxes.map(tax => ({
            taxRegimeId: tax.tax_regime_id ?? null,
            taxRateId: tax.tax_rate_id ?? null,
            taxName: tax.tax_name,
            ratePercentage: parseFloat(tax.rate_percentage || 0),
            baseAmount: parseFloat(tax.base_amount || 0),
            taxAmount: parseFloat(tax.tax_amount || 0)
        })),
        paymentMethod: paymentMethod,
        bankAccountId: paymentMethod === 'bank_transfer' ? document.getElementById('bankAccount').value : null,
        totalBill: currentReturn.invoiceTotalBill != null ? currentReturn.invoiceTotalBill : currentReturn.items.reduce((sum, item) => sum + item.net, 0),
        totalDiscountPercent: parseFloat(document.getElementById('returnDiscountPercent').value) || 0,
        totalDiscountAmount: currentReturn.invoiceDiscountAmount || parseFloat(document.getElementById('returnDiscountAmount').value) || 0,
        extraDiscount1Amount: currentReturn.extraDiscount1Amount || 0,
        extraDiscount2Amount: currentReturn.extraDiscount2Amount || 0,
        shippingFees: currentReturn.shippingFees || 0,
        netAmount: (currentReturn.invoiceTotalBill != null ? currentReturn.invoiceTotalBill : currentReturn.items.reduce((sum, item) => sum + item.net, 0)) - (parseFloat(document.getElementById('returnDiscountAmount').value) || 0) - (currentReturn.extraDiscount1Amount || 0) - (currentReturn.extraDiscount2Amount || 0) + (currentReturn.shippingFees || 0),
        amountPaid: parseFloat(document.getElementById('amountRefunded').value) || 0,
        items: currentReturn.items.map(item => ({
            productId: item.productId,
            unitEntries: item.units.map(unit => ({
                uomId: unit.id,
                quantity: unit.qty || 0
            })).filter(entry => entry.quantity > 0),
            salePrice: item.price,
            grossAmount: item.gross,
            discountPercent: item.discountPercent,
            discountAmount: item.discountAmount,
            taxPercent: item.taxPercent,
            taxAmount: item.taxAmount,
            netAmount: item.net,
            stockStatus: item.status,
            tradeOfferAmount: item.tradeOfferAmount || 0,
            focQuantity: item.focQuantity || 0
        })),
        status: 'Posted'
    };
    
    try {
        const apiUrl = isEditMode
            ? '../../../../server/api/sale/sale_return/return-edit.php'
            : '../../../../server/api/sale/sale_return/return-add.php';
        const method = isEditMode ? 'PUT' : 'POST';
        const response = await fetch(apiUrl, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(returnData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            const returnId = isEditMode ? editId : data.invoice_id;
            showNotification(isEditMode ? 'Return updated successfully!' : 'Return saved successfully!');
            window.open(`thermal-print.php?id=${returnId}`, '_blank');
            if (isEditMode) {
                setTimeout(() => window.location.href = 'return-list.php', 500);
            } else {
                setTimeout(() => {
                    currentReturn.items = [];
                    renderItems();
                    updateSummary();
                    loadNextReturnNo();
                    document.getElementById('returnDiscountPercent').value = 0;
                    document.getElementById('returnDiscountAmount').value = 0;
                    document.getElementById('amountRefunded').value = 0;
                    document.getElementById('invoiceSearch').value = '';
                    document.getElementById('saleInvoice').value = '';
                }, 500);
            }
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error saving return: ' + error.message);
    }
}

function clearReturn() {
    if (confirm('Clear current return?')) {
        currentReturn.items = [];
        currentReturn.customer = null;
        currentReturn.company = null;
        currentReturn.invoiceLevelTaxes = [];
        currentReturn.invoiceTotalBill = null;
        currentReturn.invoiceDiscountAmount = 0;
        currentReturn.extraDiscount1Amount = 0;
        currentReturn.extraDiscount2Amount = 0;
        currentReturn.shippingFees = 0;
        renderReturnLevelTaxes();
        renderItems();
        updateSummary();
        document.getElementById('customerSearch').value = '';
        document.getElementById('customer').value = '';
        document.getElementById('company').value = '';
        document.getElementById('returnDiscountPercent').value = 0;
        document.getElementById('returnDiscountAmount').value = 0;
        document.getElementById('amountRefunded').value = 0;
        document.getElementById('invoiceSearch').value = '';
        document.getElementById('saleInvoice').value = '';
    }
}

async function loadInvoiceData(invoiceId) {
    try {
        const response = await fetch(`../../../../server/api/sale/pos_invoice/pos-edit.php?id=${invoiceId}`);
        const data = await response.json();
        
        if (data.success) {
            const invoice = data.invoice;
            const priceType = document.querySelector('input[name="priceType"]:checked')?.value || 'tp';
            
            document.getElementById('customerSearch').value = `${invoice.customer_code} - ${invoice.customer_name}`;
            document.getElementById('customer').value = invoice.customer_id;
            currentReturn.customer = invoice.customer_id;
            
            // Auto-populate company
            if (invoice.company_id) {
                document.getElementById('company').value = invoice.company_id;
                currentReturn.company = invoice.company_id;
            }
            
            // Auto-populate branch
            const branch = branchesData.find(b => b.id == invoice.branch_id);
            if (branch) {
                document.getElementById('branchSearch').value = `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                document.getElementById('branch').value = invoice.branch_id;
                currentReturn.branch = invoice.branch_id;
            }
            
            // Auto-populate sales officer
            if (invoice.sale_officer_id) {
                const officer = employeesData.find(e => e.id == invoice.sale_officer_id);
                if (officer) {
                    document.getElementById('salesOfficerSearch').value = `${officer.employee_id} - ${officer.full_name}`;
                    document.getElementById('salesOfficer').value = invoice.sale_officer_id;
                    currentReturn.salesOfficer = invoice.sale_officer_id;
                }
            }
            
            // Auto-populate supplier man
            if (invoice.supplier_man_id) {
                const supplierMan = employeesData.find(e => e.id == invoice.supplier_man_id);
                if (supplierMan) {
                    document.getElementById('supplierManSearch').value = `${supplierMan.employee_id} - ${supplierMan.full_name}`;
                    document.getElementById('supplierMan').value = invoice.supplier_man_id;
                    currentReturn.supplierMan = invoice.supplier_man_id;
                }
            }
            
            currentReturn.items = [];
            
            // Group items by product_id (excluding child products)
            const productGroups = {};
            data.items.forEach(item => {
                if (!item.parent_row_id) {
                    if (!productGroups[item.product_id]) {
                        productGroups[item.product_id] = [];
                    }
                    productGroups[item.product_id].push(item);
                }
            });
            
            // Create one return item per product with all unit quantities
            Object.keys(productGroups).forEach(productId => {
                const items = productGroups[productId];
                const firstItem = items[0];
                const product = productsData.find(p => p.id == productId);
                
                if (product) {
                    const uomDetails = getProductUOMDetails(product);
                    
                    // Create unit quantity map from invoice items
                    const unitQtyMap = {};
                    items.forEach(item => {
                        unitQtyMap[parseInt(item.uom_id)] = parseFloat(item.quantity);
                    });
                    
                    const returnItem = {
                        id: Date.now() + Math.random(),
                        product: firstItem.product_name,
                        productId: productId,
                        units: uomDetails.units.map(unit => ({
                            id: unit.id,
                            name: unit.name,
                            conversionFactor: unit.conversionFactor,
                            qty: unitQtyMap[unit.id] || 0
                        })),
                        price: parseFloat(firstItem.sale_price),
                        discountPercent: parseFloat(firstItem.discount_percent || 0),
                        taxPercent: parseFloat(firstItem.tax_percent || 0),
                        status: 'sellable',
                        tradeOfferAmount: parseFloat(firstItem.trade_offer_amount || 0),
                        focQuantity: parseFloat(firstItem.foc_quantity || 0)
                    };
                    
                    returnItem.qty = calculateTotalQty(returnItem);
                    recalculateItem(returnItem);
                    currentReturn.items.push(returnItem);
                }
            });
            
            recalculateMaxColumns();
            renderItems();

            // Set total bill and extra discounts from original invoice
            currentReturn.invoiceTotalBill = parseFloat(invoice.total_bill || 0);
            currentReturn.invoiceDiscountAmount = parseFloat(invoice.total_discount_amount || 0);
            currentReturn.extraDiscount1Amount = parseFloat(invoice.extra_discount_1_amount || 0);
            currentReturn.extraDiscount2Amount = parseFloat(invoice.extra_discount_2_amount || 0);
            currentReturn.shippingFees = parseFloat(invoice.shipping_fees || 0);

            // Populate return discount inputs from invoice
            document.getElementById('returnDiscountPercent').value = parseFloat(invoice.total_discount_percent || 0);
            document.getElementById('returnDiscountAmount').value = parseFloat(invoice.total_discount_amount || 0);

            updateSummary();

            // Fetch and populate invoice-level taxes from the original sale invoice
            try {
                const taxResponse = await fetch(`../../../../server/api/sale/pos_invoice/get-invoice-taxes.php?invoice_id=${invoiceId}`);
                const taxData = await taxResponse.json();
                currentReturn.invoiceLevelTaxes = (taxData.success && taxData.data && taxData.data.length > 0) ? taxData.data : [];
            } catch (e) {
                currentReturn.invoiceLevelTaxes = [];
            }
            renderReturnLevelTaxes();

            showNotification('Invoice loaded successfully!');
        }
    } catch (error) {
        alert('Error loading invoice: ' + error.message);
    }
}

function renderReturnLevelTaxes() {
    const container = document.getElementById('invoiceLevelTaxesContainer');
    if (!container) return;
    container.innerHTML = '';

    currentReturn.invoiceLevelTaxes.forEach(tax => {
        const percentItem = document.createElement('div');
        percentItem.className = 'summary-item';
        percentItem.innerHTML = `
            <div class="summary-label">${tax.tax_name} %</div>
            <div class="summary-value">${parseFloat(tax.rate_percentage).toFixed(2)}%</div>
        `;
        container.appendChild(percentItem);

        const amountItem = document.createElement('div');
        amountItem.className = 'summary-item';
        amountItem.innerHTML = `
            <div class="summary-label">${tax.tax_name} Amt</div>
            <div class="summary-value" style="color: var(--error);">${formatCurrency(parseFloat(tax.tax_amount))}</div>
        `;
        container.appendChild(amountItem);
    });

    const netReceivableItem = document.getElementById('netReceivableItem');
    if (netReceivableItem) {
        netReceivableItem.style.display = currentReturn.invoiceLevelTaxes.length > 0 ? '' : 'none';
    }
    updateNetReceivable();
}

function updateNetReceivable() {
    const netReceivableEl = document.getElementById('netReceivable');
    if (!netReceivableEl) return;
    const returnDiscountAmount = parseFloat(document.getElementById('returnDiscountAmount').value) || 0;
    const totalBill = currentReturn.invoiceTotalBill != null ? currentReturn.invoiceTotalBill : currentReturn.items.reduce((sum, item) => sum + item.net, 0);
    const ed1 = currentReturn.extraDiscount1Amount || 0;
    const ed2 = currentReturn.extraDiscount2Amount || 0;
    const shipping = currentReturn.shippingFees || 0;
    const netAmount = totalBill - returnDiscountAmount - ed1 - ed2 + shipping;
    const totalInvoiceTax = currentReturn.invoiceLevelTaxes.reduce((sum, t) => sum + parseFloat(t.tax_amount || 0), 0);
    netReceivableEl.textContent = formatCurrency(netAmount - totalInvoiceTax);
}

async function loadEditData(returnId) {
    try {
        const response = await fetch(`../../../../server/api/sale/sale_return/return-edit.php?id=${returnId}`);
        const data = await response.json();
        if (!data.success) { alert('Error loading return: ' + data.message); return; }

        const inv = data.invoice;

        // Header fields
        document.getElementById('currentDate').value = inv.sale_date || '';
        document.getElementById('returnNo').textContent = inv.bill_no || '';

        // Company
        document.getElementById('company').value = inv.company_id || '';
        currentReturn.company = parseInt(inv.company_id) || null;

        // Branch
        const branch = branchesData.find(b => b.id == inv.branch_id);
        if (branch) {
            document.getElementById('branchSearch').value = `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
            document.getElementById('branch').value = inv.branch_id;
            currentReturn.branch = parseInt(inv.branch_id);
        }

        // Customer
        const customer = customersData.find(c => c.id == inv.customer_id);
        if (customer) {
            document.getElementById('customerSearch').value = `${customer.customer_code} - ${customer.customer_name}`;
            document.getElementById('customer').value = inv.customer_id;
            currentReturn.customer = parseInt(inv.customer_id);
        }

        // Sales Officer
        if (inv.sale_officer_id) {
            const officer = employeesData.find(e => e.id == inv.sale_officer_id);
            if (officer) {
                document.getElementById('salesOfficerSearch').value = `${officer.employee_id} - ${officer.full_name}`;
                document.getElementById('salesOfficer').value = inv.sale_officer_id;
                currentReturn.salesOfficer = parseInt(inv.sale_officer_id);
            }
        }

        // Supplier Man
        if (inv.supplier_man_id) {
            const sm = employeesData.find(e => e.id == inv.supplier_man_id);
            if (sm) {
                document.getElementById('supplierManSearch').value = `${sm.employee_id} - ${sm.full_name}`;
                document.getElementById('supplierMan').value = inv.supplier_man_id;
                currentReturn.supplierMan = parseInt(inv.supplier_man_id);
            }
        }

        // Project
        if (inv.project_id) {
            const project = projectsData.find(p => p.id == inv.project_id);
            if (project) {
                document.getElementById('projectSearch').value = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
                document.getElementById('project').value = inv.project_id;
                currentReturn.project = parseInt(inv.project_id);
            }
        }

        // Sub Account
        if (inv.sub_account_id) {
            await loadSubAccounts(inv.customer_id);
            document.getElementById('subAccount').value = inv.sub_account_id;
            currentReturn.subAccount = parseInt(inv.sub_account_id);
        }

        // Sale Invoice
        if (inv.sale_invoice_no) {
            document.getElementById('invoiceSearch').value = inv.sale_invoice_no;
            document.getElementById('saleInvoice').value = inv.sale_invoice_no;
        }

        // Payment
        document.getElementById('paymentMethod').value = inv.payment_method === 'Bank Transfer' ? 'bank_transfer' : 'cash';
        if (inv.payment_method === 'Bank Transfer' && inv.bank_account_id) {
            document.getElementById('bankAccountContainer').classList.remove('hidden');
            document.getElementById('bankAccount').value = inv.bank_account_id;
        }
        document.getElementById('amountRefunded').value = parseFloat(inv.amount_refunded || 0);

        // Discounts
        document.getElementById('returnDiscountPercent').value = parseFloat(inv.total_discount_percent || 0);
        document.getElementById('returnDiscountAmount').value = parseFloat(inv.total_discount_amount || 0);

        // Set invoice-level values
        currentReturn.invoiceTotalBill = parseFloat(inv.total_bill || 0);
        currentReturn.invoiceDiscountAmount = parseFloat(inv.total_discount_amount || 0);
        currentReturn.extraDiscount1Amount = parseFloat(inv.extra_discount_1_amount || 0);
        currentReturn.extraDiscount2Amount = parseFloat(inv.extra_discount_2_amount || 0);
        currentReturn.shippingFees = parseFloat(inv.shipping_fees || 0);

        // Load items
        currentReturn.items = [];
        data.items.forEach(item => {
            const product = productsData.find(p => p.id == item.product_id);
            const uomDetails = product ? getProductUOMDetails(product) : {
                units: (item.unit_entries || []).map(e => ({ id: e.uom_id, name: e.uom_name, conversionFactor: 1 }))
            };

            const unitQtyMap = {};
            (item.unit_entries || []).forEach(e => { unitQtyMap[parseInt(e.uom_id)] = parseFloat(e.quantity); });

            const returnItem = {
                id: Date.now() + Math.random(),
                product: item.product_name,
                productId: item.product_id,
                units: uomDetails.units.map(u => ({
                    id: u.id,
                    name: u.name,
                    conversionFactor: u.conversionFactor,
                    qty: unitQtyMap[u.id] || 0
                })),
                price: parseFloat(item.sale_price),
                discountPercent: parseFloat(item.discount_percent || 0),
                taxPercent: parseFloat(item.tax_percent || 0),
                status: 'sellable',
                tradeOfferAmount: parseFloat(item.trade_offer_amount || 0),
                focQuantity: parseFloat(item.foc_quantity || 0)
            };
            returnItem.qty = calculateTotalQty(returnItem);
            recalculateItem(returnItem);
            currentReturn.items.push(returnItem);
        });

        recalculateMaxColumns();
        renderItems();
        updateSummary();

        document.getElementById('returnNo').textContent = inv.bill_no;
    } catch (error) {
        alert('Error loading return data: ' + error.message);
    }
}

async function loadProducts(companyId = null) {
    const url = companyId
        ? `../../../../server/api/sale/sale_return/get-products.php?company_id=${companyId}`
        : '../../../../server/api/sale/sale_return/get-products.php';
    const response = await fetch(url);
    const data = await response.json();
    if (data.success) productsData = data.products;
}

async function loadCustomers(companyId = null) {
    const url = companyId
        ? `../../../../server/api/sale/sale_return/get-customers.php?company_id=${companyId}`
        : '../../../../server/api/sale/sale_return/get-customers.php';
    const response = await fetch(url);
    const data = await response.json();
    if (data.success) {
        customersData = data.customers;
        const customerSearch = document.getElementById('customerSearch');
        if (customerSearch && customerSearch.updateData) customerSearch.updateData(customersData);
    }
}

async function loadSaleInvoices(companyId = null) {
    const url = companyId
        ? `../../../../server/api/sale/sale_return/get-sale-invoices.php?company_id=${companyId}`
        : '../../../../server/api/sale/sale_return/get-sale-invoices.php';
    const response = await fetch(url);
    const data = await response.json();
    if (data.success) {
        invoicesData = data.invoices;
        const invoiceSearch = document.getElementById('invoiceSearch');
        if (invoiceSearch && invoiceSearch.updateData) invoiceSearch.updateData(invoicesData);
    }
}

async function loadBranches(companyId = null) {
    const url = companyId
        ? `../../../../server/api/sale/sale_return/get-branches.php?company_id=${companyId}`
        : '../../../../server/api/sale/sale_return/get-branches.php';
    const response = await fetch(url);
    const data = await response.json();
    if (data.success) {
        branchesData = data.branches;
        const branchSearch = document.getElementById('branchSearch');
        if (branchSearch && branchSearch.updateData) branchSearch.updateData(branchesData);
    }
}

async function loadCurrencies() {
    const response = await fetch('../../../../server/api/sale/sale_return/get-currencies.php');
    const data = await response.json();
    if (data.success) currenciesData = data.currencies;
}

async function loadBankAccounts() {
    const response = await fetch('../../../../server/api/sale/sale_return/get-bank-accounts.php');
    const data = await response.json();
    if (data.success) {
        bankAccountsData = data.accounts;
        const select = document.getElementById('bankAccount');
        select.innerHTML = '<option value="">Select Bank Account</option>';
        data.accounts.forEach(acc => {
            const option = document.createElement('option');
            option.value = acc.id;
            option.textContent = `${acc.account_title} - ${acc.account_number}`;
            select.appendChild(option);
        });
    }
}

async function loadUOM() {
    const response = await fetch('../../../../server/api/sale/sale_return/get-uom.php');
    const data = await response.json();
    if (data.success) uomData = data.uoms;
}

async function loadEmployees() {
    const response = await fetch('../../../../server/api/sale/sale_return/get-employees.php');
    const data = await response.json();
    if (data.success) employeesData = data.employees;
}

async function loadCompanies() {
    const response = await fetch('../../../../server/api/sale/sale_return/get-companies.php');
    const data = await response.json();
    if (data.success) {
        companiesData = data.companies;
        const select = document.getElementById('company');
        select.innerHTML = '<option value="">Select Company</option>';
        data.companies.forEach(c => {
            const option = document.createElement('option');
            option.value = c.id;
            option.textContent = `${c.company_code} - ${c.company_name}`;
            select.appendChild(option);
        });

        // Auto-select if only one company
        if (data.companies.length === 1) {
            const autoId = data.companies[0].id;
            select.value = autoId;
            currentReturn.company = autoId;
            await Promise.all([loadProducts(autoId), loadCustomers(autoId), loadSaleInvoices(autoId), loadBranches(autoId)]);
            loadNextReturnNo(autoId);
        }

        // Company change handler
        select.addEventListener('change', async function() {
            const companyId = parseInt(this.value) || null;
            currentReturn.company = companyId;
            // Clear customer, invoice, branch selections
            document.getElementById('customerSearch').value = '';
            document.getElementById('customer').value = '';
            currentReturn.customer = null;
            document.getElementById('invoiceSearch').value = '';
            document.getElementById('saleInvoice').value = '';
            document.getElementById('branchSearch').value = '';
            document.getElementById('branch').value = '';
            currentReturn.branch = null;
            await Promise.all([loadProducts(companyId), loadCustomers(companyId), loadSaleInvoices(companyId), loadBranches(companyId)]);
            loadNextReturnNo(companyId);
        });
    }
}

async function loadSubAccounts(customerId) {
    const response = await fetch(`../../../../server/api/sale/sale_return/get-sub-accounts.php?customer_id=${customerId}`);
    const data = await response.json();
    if (data.success) {
        subAccountsData = data.sub_accounts;
        const subAccountSearch = document.getElementById('subAccountSearch');
        subAccountSearch.value = '';
        document.getElementById('subAccount').value = '';
        currentReturn.subAccount = null;
        subAccountSearch.updateData(subAccountsData);
    }
}

function updateDate() {
    document.getElementById('currentDate').value = new Date().toISOString().split('T')[0];
}

async function loadNextReturnNo(companyId = null) {
    try {
        const url = companyId
            ? `../../../../server/api/sale/sale_return/get-next-invoice-number.php?company_id=${companyId}`
            : '../../../../server/api/sale/sale_return/get-next-invoice-number.php';
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) document.getElementById('returnNo').textContent = data.nextNumber;
    } catch (error) {
        console.error('Error loading return number:', error);
    }
}

function formatCurrency(amount) {
    const currency = currenciesData.find(c => c.is_base_currency == 1);
    const symbol = currency ? currency.symbol : '$';
    return `${symbol}${Math.abs(amount).toFixed(2)}`;
}

function showNotification(message) {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: var(--success); color: white; padding: 8px 16px; border-radius: 4px; z-index: 10000; font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

function loadShortcuts() {
    const saved = localStorage.getItem('return_shortcuts');
    if (saved) shortcuts = JSON.parse(saved);
    document.getElementById('shortcutProduct').value = shortcuts.product;
    document.getElementById('shortcutCustomer').value = shortcuts.customer;
    document.getElementById('shortcutBranch').value = shortcuts.branch;
    document.getElementById('shortcutQty').value = shortcuts.qty;
    document.getElementById('shortcutSave').value = shortcuts.save;
    document.getElementById('shortcutClear').value = shortcuts.clear;
}

function saveShortcuts() {
    shortcuts = {
        product: document.getElementById('shortcutProduct').value || defaultShortcuts.product,
        customer: document.getElementById('shortcutCustomer').value || defaultShortcuts.customer,
        branch: document.getElementById('shortcutBranch').value || defaultShortcuts.branch,
        qty: document.getElementById('shortcutQty').value || defaultShortcuts.qty,
        save: document.getElementById('shortcutSave').value || defaultShortcuts.save,
        clear: document.getElementById('shortcutClear').value || defaultShortcuts.clear
    };
    localStorage.setItem('return_shortcuts', JSON.stringify(shortcuts));
    document.getElementById('shortcutsModal').style.display = 'none';
    showNotification('Shortcuts saved successfully!');
}

function resetShortcuts() {
    shortcuts = { ...defaultShortcuts };
    document.getElementById('shortcutProduct').value = shortcuts.product;
    document.getElementById('shortcutCustomer').value = shortcuts.customer;
    document.getElementById('shortcutBranch').value = shortcuts.branch;
    document.getElementById('shortcutQty').value = shortcuts.qty;
    document.getElementById('shortcutSave').value = shortcuts.save;
    document.getElementById('shortcutClear').value = shortcuts.clear;
}

function filterInvoicesByCustomer(customerId) {
    const invoiceSearch = document.getElementById('invoiceSearch');
    const filteredInvoices = invoicesData.filter(inv => inv.customer_id == customerId);
    
    invoiceSearch.value = '';
    document.getElementById('saleInvoice').value = '';
    
    // Update dropdown data
    invoiceSearch.updateData(filteredInvoices);
}

async function loadProjects() {
    const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
    const data = await response.json();
    if (data.success) projectsData = data.projects;
}
