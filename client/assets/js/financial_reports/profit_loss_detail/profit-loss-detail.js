// DOM Elements
const startDateEl = document.getElementById('startDate');
const endDateEl = document.getElementById('endDate');
const branchFilterEl = document.getElementById('branchFilter');
const companyFilterEl = document.getElementById('companyFilter');
const categoryFilterEl = document.getElementById('categoryFilter');
const customerFilterEl = document.getElementById('customerFilter');
const productFilterEl = document.getElementById('productFilter');
const invoiceFilterEl = document.getElementById('invoiceFilter');
const supplierFilterEl = document.getElementById('supplierFilter');
const currencyFilterEl = document.getElementById('currencyFilter');
const categoryFilterSearchEl = document.getElementById('categoryFilterSearch');
const customerFilterSearchEl = document.getElementById('customerFilterSearch');
const productFilterSearchEl = document.getElementById('productFilterSearch');
const invoiceFilterSearchEl = document.getElementById('invoiceFilterSearch');
const supplierFilterSearchEl = document.getElementById('supplierFilterSearch');
const generateReportBtn = document.getElementById('generateReportBtn');
const resetBtn = document.getElementById('resetBtn');
const helpBtn = document.getElementById('helpBtn');
const helpSection = document.getElementById('helpSection');
const exportPDFBtn = document.getElementById('exportPDFBtn');
const exportCSVBtn = document.getElementById('exportCSVBtn');
const printBtn = document.getElementById('printBtn');
const refreshBtn = document.getElementById('refreshBtn');
const reportResults = document.getElementById('reportResults');
const summaryCards = document.getElementById('summaryCards');
const validationMessage = document.getElementById('validationMessage');
const validationText = document.getElementById('validationText');

// Summary card elements
const totalRevenueEl = document.getElementById('totalRevenue');
const totalCOGSEl = document.getElementById('totalCOGS');
const grossProfitEl = document.getElementById('grossProfit');
const profitMarginEl = document.getElementById('profitMargin');

// Table elements
const itemTableBody = document.getElementById('itemTableBody');
const categoryTableBody = document.getElementById('categoryTableBody');
const customerTableBody = document.getElementById('customerTableBody');
const invoiceTableBody = document.getElementById('invoiceTableBody');
const supplierTableBody = document.getElementById('supplierTableBody');
const partnerTableBody = document.getElementById('partnerTableBody');
const reportPeriodItem = document.getElementById('reportPeriodItem');
const reportPeriodCategory = document.getElementById('reportPeriodCategory');
const reportPeriodCustomer = document.getElementById('reportPeriodCustomer');
const reportPeriodInvoice = document.getElementById('reportPeriodInvoice');
const reportPeriodSupplier = document.getElementById('reportPeriodSupplier');
const reportPeriodPartner = document.getElementById('reportPeriodPartner');

// Set default dates
const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

startDateEl.value = formatDate(firstDay);
endDateEl.value = formatDate(today);

// Searchable dropdown functionality
function initSearchableDropdown(searchInput, optionsContainer, hiddenInput, data, valueKey, labelKey) {
    let filteredData = data;
    
    searchInput.addEventListener('focus', () => {
        optionsContainer.classList.add('active');
        renderOptions();
    });
    
    searchInput.addEventListener('input', () => {
        const searchTerm = searchInput.value.toLowerCase();
        filteredData = data.filter(item => 
            item[labelKey].toLowerCase().includes(searchTerm)
        );
        renderOptions();
    });
    
    function renderOptions() {
        optionsContainer.innerHTML = '';
        
        if (filteredData.length === 0) {
            optionsContainer.innerHTML = '<div class="dropdown-option" style="color: var(--text-subtext);">No results found</div>';
            return;
        }
        
        filteredData.forEach(item => {
            const option = document.createElement('div');
            option.className = 'dropdown-option';
            option.textContent = item[labelKey];
            option.dataset.value = item[valueKey];
            
            option.addEventListener('click', () => {
                hiddenInput.value = item[valueKey];
                searchInput.value = item[labelKey];
                optionsContainer.classList.remove('active');
            });
            
            optionsContainer.appendChild(option);
        });
    }
    
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !optionsContainer.contains(e.target)) {
            optionsContainer.classList.remove('active');
        }
    });
}

// Load dropdowns
loadCurrencies();
loadCompanies();
loadCategories();
loadCustomers();
loadProducts();
loadInvoices();
loadSuppliers();

async function loadCurrencies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-currencies.php');
        const result = await response.json();
        
        if (result.success) {
            currencyFilterEl.innerHTML = '';
            result.data.forEach(currency => {
                const option = document.createElement('option');
                option.value = currency.id;
                option.textContent = `${currency.name} (${currency.symbol})`;
                option.dataset.symbol = currency.symbol;
                if (currency.is_base) option.selected = true;
                currencyFilterEl.appendChild(option);
            });
            
            // Set initial currency symbol
            const selectedOption = currencyFilterEl.options[currencyFilterEl.selectedIndex];
            if (selectedOption && selectedOption.dataset.symbol) {
                window.CURRENCY_SYMBOL = selectedOption.dataset.symbol;
            }
        }
    } catch (error) {
        console.error('Error loading currencies:', error);
    }
}

async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-companies.php');
        const result = await response.json();
        
        if (result.success) {
            companyFilterEl.innerHTML = '<option value="">All Companies</option>';
            result.data.forEach(company => {
                companyFilterEl.innerHTML += `<option value="${company.id}">${company.company_name}</option>`;
            });
            
            if (result.data.length === 1) {
                companyFilterEl.value = result.data[0].id;
            }
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

async function loadCategories() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-categories.php');
        const result = await response.json();
        
        if (result.success) {
            const categoriesData = [{id: '', name: 'All Categories'}, ...result.data];
            initSearchableDropdown(
                categoryFilterSearchEl,
                document.getElementById('categoryFilterOptions'),
                categoryFilterEl,
                categoriesData,
                'id',
                'name'
            );
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

async function loadCustomers() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-customers.php');
        const result = await response.json();
        
        if (result.success) {
            const customersData = [{id: '', name: 'All Customers'}, ...result.data];
            initSearchableDropdown(
                customerFilterSearchEl,
                document.getElementById('customerFilterOptions'),
                customerFilterEl,
                customersData,
                'id',
                'name'
            );
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}

async function loadProducts() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-products.php');
        const result = await response.json();
        
        if (result.success) {
            const productsData = [{id: '', name: 'All Products'}, ...result.data];
            initSearchableDropdown(
                productFilterSearchEl,
                document.getElementById('productFilterOptions'),
                productFilterEl,
                productsData,
                'id',
                'name'
            );
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

async function loadSuppliers() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-suppliers.php');
        const result = await response.json();

        if (result.success) {
            const suppliersData = [{id: '', name: 'All Suppliers'}, ...result.data];
            initSearchableDropdown(
                supplierFilterSearchEl,
                document.getElementById('supplierFilterOptions'),
                supplierFilterEl,
                suppliersData,
                'id',
                'name'
            );
        }
    } catch (error) {
        console.error('Error loading suppliers:', error);
    }
}

async function loadInvoices() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/profit_loss_detail/get-invoices.php');
        const result = await response.json();
        
        if (result.success) {
            const invoicesData = [{id: '', bill_no: 'All Invoices'}, ...result.data];
            initSearchableDropdown(
                invoiceFilterSearchEl,
                document.getElementById('invoiceFilterOptions'),
                invoiceFilterEl,
                invoicesData,
                'id',
                'bill_no'
            );
        }
    } catch (error) {
        console.error('Error loading invoices:', error);
    }
}

// Format currency
function formatCurrency(amount) {
    const selectedOption = currencyFilterEl.options[currencyFilterEl.selectedIndex];
    const symbol = selectedOption && selectedOption.dataset.symbol ? selectedOption.dataset.symbol : CURRENCY_SYMBOL;
    return symbol + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Validate form
function validateForm() {
    const startDate = new Date(startDateEl.value);
    const endDate = new Date(endDateEl.value);

    startDateEl.classList.remove('error');
    endDateEl.classList.remove('error');

    let isValid = true;
    let errorMessage = '';

    if (startDate > endDate) {
        startDateEl.classList.add('error');
        endDateEl.classList.add('error');
        errorMessage = 'Start date cannot be after end date.';
        isValid = false;
    }

    if (startDate > today || endDate > today) {
        startDateEl.classList.add('error');
        endDateEl.classList.add('error');
        errorMessage = 'Report dates cannot be in the future.';
        isValid = false;
    }

    if (!isValid) {
        validationMessage.className = 'validation-message validation-error';
        validationText.textContent = errorMessage;
        validationMessage.style.display = 'flex';
    } else {
        validationMessage.style.display = 'none';
    }

    return isValid;
}

// Fetch data from API
async function fetchReportData(startDate, endDate, branchId, companyId, categoryId, customerId, productId, invoiceId, currencyId, supplierId) {
    let url = `../../../../server/api/financial_reports/profit_loss_detail/get-report.php?start_date=${startDate}&end_date=${endDate}`;
    if (branchId && branchId !== 'all') url += `&branch_id=${branchId}`;
    if (companyId) url += `&company_id=${companyId}`;
    if (categoryId) url += `&category_id=${categoryId}`;
    if (customerId) url += `&customer_id=${customerId}`;
    if (productId) url += `&product_id=${productId}`;
    if (invoiceId) url += `&invoice_id=${invoiceId}`;
    if (currencyId) url += `&currency_id=${currencyId}`;
    if (supplierId) url += `&supplier_id=${supplierId}`;
    
    const response = await fetch(url);
    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.message || 'Failed to fetch data');
    }
    
    return result.data;
}

// Fetch partner accounts and render the Partner-wise distribution table
// based on the given total profit figure for the period.
async function renderPartnerTable(totalProfit) {
    if (!partnerTableBody) return;

    try {
        const response = await fetch('../../../../server/api/chart_of_accounts/partner-accounts.php');
        const result = await response.json();

        if (!result.success) {
            partnerTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; color: var(--text-subtext); padding: 30px;">${result.message}</td></tr>`;
            // Nothing to print in this case; clear any stale partner data.
            if (window.currentData) window.currentData.partnerwise = { partners: [], totalProfit: totalProfit };
            return;
        }

        const partners = result.data;

        if (!partners || partners.length === 0) {
            partnerTableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; color: var(--text-subtext); padding: 30px;">No partners have been added yet. Use "Add Partner" in Chart of Accounts.</td></tr>';
            if (window.currentData) window.currentData.partnerwise = { partners: [], totalProfit: totalProfit };
            return;
        }

        let partnerHTML = '';
        let totalPercentage = 0;
        let totalDistributed = 0;

        partners.forEach(partner => {
            const percentage = parseFloat(partner.percentage) || 0;
            const share = totalProfit * (percentage / 100);

            totalPercentage += percentage;
            totalDistributed += share;

            partnerHTML += `
                <tr>
                    <td>${partner.account_name}</td>
                    <td class="amount-cell">${percentage.toFixed(2)}%</td>
                    <td class="amount-cell ${share >= 0 ? 'positive' : 'negative'}">${formatCurrency(share)}</td>
                </tr>
            `;
        });

        // Whatever percentage is left over (not assigned to any partner)
        // is treated as the owner's / remaining share.
        const ownerPercentage = 100 - totalPercentage;
        const ownerShare = totalProfit - totalDistributed;

        partnerHTML += `
            <tr class="total-row">
                <td><strong>Remaining / Owner's Share</strong></td>
                <td class="amount-cell">${ownerPercentage.toFixed(2)}%</td>
                <td class="amount-cell ${ownerShare >= 0 ? 'positive' : 'negative'}">${formatCurrency(ownerShare)}</td>
            </tr>
            <tr class="total-row">
                <td><strong>TOTAL PROFIT</strong></td>
                <td class="amount-cell">100.00%</td>
                <td class="amount-cell ${totalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(totalProfit)}</td>
            </tr>
        `;

        partnerTableBody.innerHTML = partnerHTML;

        // Store partner data so the Print button can send it to print.php
        // (partner data comes from a separate endpoint, unlike the other tabs).
        if (window.currentData) {
            window.currentData.partnerwise = { partners: partners, totalProfit: totalProfit };
        }
    } catch (error) {
        console.error('Error loading partner accounts:', error);
        partnerTableBody.innerHTML = '<tr><td colspan="3" style="text-align:center; color: var(--text-subtext); padding: 30px;">Error loading partner data.</td></tr>';
        if (window.currentData) window.currentData.partnerwise = { partners: [], totalProfit: totalProfit };
    }
}

// Generate report
async function generateReport() {
    if (!validateForm()) return;

    try {
        validationMessage.className = 'validation-message validation-success';
        validationText.textContent = 'Loading report data...';
        validationMessage.style.display = 'flex';

     const data = await fetchReportData(
        startDateEl.value,
        endDateEl.value,
        branchFilterEl.value,
        companyFilterEl.value,
        categoryFilterEl.value,
        customerFilterEl.value,
        productFilterEl.value,
        invoiceFilterEl.value,
        currencyFilterEl.value,
        supplierFilterEl.value
    );

    // Calculate totals
    let totalRevenue = 0;
    let totalCOGS = 0;
    let totalExpenses = data.total_expenses || 0;  // ✅ Get from API

    data.itemwise.forEach(item => {
        totalRevenue += parseFloat(item.revenue);
        totalCOGS += parseFloat(item.cogs);
    });

    let grossProfit = totalRevenue - totalCOGS;
    let netProfit = grossProfit - totalExpenses;  // ✅ Calculate Net Profit
    let profitMargin = totalRevenue > 0 ? ((grossProfit / totalRevenue) * 100).toFixed(2) : 0;

    // ✅ Pass Net Profit to partner distribution instead of Gross Profit
    await renderPartnerTable(netProfit);


        // Update summary cards
        const selectedOption = currencyFilterEl.options[currencyFilterEl.selectedIndex];
        const currentSymbol = selectedOption && selectedOption.dataset.symbol ? selectedOption.dataset.symbol : CURRENCY_SYMBOL;
        
        totalRevenueEl.textContent = currentSymbol + totalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        totalCOGSEl.textContent = currentSymbol + totalCOGS.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        grossProfitEl.textContent = currentSymbol + grossProfit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        profitMarginEl.textContent = profitMargin + '%';

        // Update report period
        const startDate = new Date(startDateEl.value);
        const endDate = new Date(endDateEl.value);
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        const periodText = `Period: ${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}`;
        reportPeriodItem.textContent = periodText;
        reportPeriodCategory.textContent = periodText;
        reportPeriodCustomer.textContent = periodText;
        reportPeriodInvoice.textContent = periodText;
        reportPeriodSupplier.textContent = periodText;
        if (reportPeriodPartner) reportPeriodPartner.textContent = periodText;

        // Build item-wise table
        let itemHTML = '';
        let itemTotalRevenue = 0;
        let itemTotalCOGS = 0;
        let itemTotalProfit = 0;

        data.itemwise.forEach(item => {
            const revenue = parseFloat(item.revenue);
            const cogs = parseFloat(item.cogs);
            const profit = revenue - cogs;
            const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

            itemTotalRevenue += revenue;
            itemTotalCOGS += cogs;
            itemTotalProfit += profit;

            itemHTML += `
                <tr>
                    <td>${item.product_name}</td>
                    <td class="amount-cell">${parseFloat(item.qty_sold).toFixed(2)}</td>
                    <td class="amount-cell positive">${formatCurrency(revenue)}</td>
                    <td class="amount-cell negative">${formatCurrency(cogs)}</td>
                    <td class="amount-cell ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                    <td class="amount-cell">${margin}%</td>
                </tr>
            `;
        });

        const itemTotalMargin = itemTotalRevenue > 0 ? ((itemTotalProfit / itemTotalRevenue) * 100).toFixed(2) : 0;
        itemHTML += `
            <tr class="total-row">
                <td><strong>TOTAL</strong></td>
                <td class="amount-cell"></td>
                <td class="amount-cell positive">${formatCurrency(itemTotalRevenue)}</td>
                <td class="amount-cell negative">${formatCurrency(itemTotalCOGS)}</td>
                <td class="amount-cell ${itemTotalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(itemTotalProfit)}</td>
                <td class="amount-cell">${itemTotalMargin}%</td>
            </tr>
        `;

        itemTableBody.innerHTML = itemHTML;

        // Build category-wise table
        let categoryHTML = '';
        let catTotalRevenue = 0;
        let catTotalCOGS = 0;
        let catTotalProfit = 0;

        data.categorywise.forEach(category => {
            const revenue = parseFloat(category.revenue);
            const cogs = parseFloat(category.cogs);
            const profit = revenue - cogs;
            const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

            catTotalRevenue += revenue;
            catTotalCOGS += cogs;
            catTotalProfit += profit;

            categoryHTML += `
                <tr>
                    <td>${category.category_name || 'Uncategorized'}</td>
                    <td class="amount-cell">${category.product_count}</td>
                    <td class="amount-cell positive">${formatCurrency(revenue)}</td>
                    <td class="amount-cell negative">${formatCurrency(cogs)}</td>
                    <td class="amount-cell ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                    <td class="amount-cell">${margin}%</td>
                </tr>
            `;
        });

        const catTotalMargin = catTotalRevenue > 0 ? ((catTotalProfit / catTotalRevenue) * 100).toFixed(2) : 0;
        categoryHTML += `
            <tr class="total-row">
                <td><strong>TOTAL</strong></td>
                <td class="amount-cell"></td>
                <td class="amount-cell positive">${formatCurrency(catTotalRevenue)}</td>
                <td class="amount-cell negative">${formatCurrency(catTotalCOGS)}</td>
                <td class="amount-cell ${catTotalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(catTotalProfit)}</td>
                <td class="amount-cell">${catTotalMargin}%</td>
            </tr>
        `;

        categoryTableBody.innerHTML = categoryHTML;

        // Build customer-wise table
        let customerHTML = '';
        let custTotalRevenue = 0;
        let custTotalCOGS = 0;
        let custTotalProfit = 0;

        data.customerwise.forEach(customer => {
            const revenue = parseFloat(customer.revenue);
            const cogs = parseFloat(customer.cogs);
            const profit = revenue - cogs;
            const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

            custTotalRevenue += revenue;
            custTotalCOGS += cogs;
            custTotalProfit += profit;

            customerHTML += `
                <tr>
                    <td>${customer.customer_name}</td>
                    <td class="amount-cell">${customer.invoice_count}</td>
                    <td class="amount-cell positive">${formatCurrency(revenue)}</td>
                    <td class="amount-cell negative">${formatCurrency(cogs)}</td>
                    <td class="amount-cell ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                    <td class="amount-cell">${margin}%</td>
                </tr>
            `;
        });

        const custTotalMargin = custTotalRevenue > 0 ? ((custTotalProfit / custTotalRevenue) * 100).toFixed(2) : 0;
        customerHTML += `
            <tr class="total-row">
                <td><strong>TOTAL</strong></td>
                <td class="amount-cell"></td>
                <td class="amount-cell positive">${formatCurrency(custTotalRevenue)}</td>
                <td class="amount-cell negative">${formatCurrency(custTotalCOGS)}</td>
                <td class="amount-cell ${custTotalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(custTotalProfit)}</td>
                <td class="amount-cell">${custTotalMargin}%</td>
            </tr>
        `;

        customerTableBody.innerHTML = customerHTML;

        // Build invoice-wise table
        let invoiceHTML = '';
        let invTotalRevenue = 0;
        let invTotalCOGS = 0;
        let invTotalProfit = 0;

        data.invoicewise.forEach(invoice => {
            const revenue = parseFloat(invoice.revenue);
            const cogs = parseFloat(invoice.cogs);
            const profit = revenue - cogs;
            const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : 0;

            invTotalRevenue += revenue;
            invTotalCOGS += cogs;
            invTotalProfit += profit;

            const invoiceDate = new Date(invoice.sale_date);
            const dateStr = invoiceDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            invoiceHTML += `
                <tr>
                    <td>${invoice.bill_no}</td>
                    <td>${dateStr}</td>
                    <td>${invoice.customer_name}</td>
                    <td class="amount-cell positive">${formatCurrency(revenue)}</td>
                    <td class="amount-cell negative">${formatCurrency(cogs)}</td>
                    <td class="amount-cell ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                    <td class="amount-cell">${margin}%</td>
                </tr>
            `;
        });

        const invTotalMargin = invTotalRevenue > 0 ? ((invTotalProfit / invTotalRevenue) * 100).toFixed(2) : 0;
        invoiceHTML += `
            <tr class="total-row">
                <td colspan="3"><strong>TOTAL</strong></td>
                <td class="amount-cell positive">${formatCurrency(invTotalRevenue)}</td>
                <td class="amount-cell negative">${formatCurrency(invTotalCOGS)}</td>
                <td class="amount-cell ${invTotalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(invTotalProfit)}</td>
                <td class="amount-cell">${invTotalMargin}%</td>
            </tr>
        `;

        invoiceTableBody.innerHTML = invoiceHTML;

        // Build supplier-wise table
        let supplierHTML = '';
        let supTotalRevenue = 0;
        let supTotalCOGS = 0;
        let supTotalProfit = 0;

        data.supplierwise.forEach(supplier => {
            const revenue = parseFloat(supplier.revenue);
            const cogs = parseFloat(supplier.cogs);
            const profit = revenue - cogs;
            const margin = revenue > 0 ? ((profit / revenue) * 100).toFixed(2) : '—';

            supTotalRevenue += revenue;
            supTotalCOGS += cogs;
            supTotalProfit += profit;

            supplierHTML += `
                <tr>
                    <td>${supplier.supplier_code || '—'}</td>
                    <td>${supplier.supplier_name}</td>
                    <td class="amount-cell">${supplier.purchase_count}</td>
                    <td class="amount-cell positive">${formatCurrency(revenue)}</td>
                    <td class="amount-cell negative">${formatCurrency(cogs)}</td>
                    <td class="amount-cell ${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                    <td class="amount-cell">${margin}${margin !== '—' ? '%' : ''}</td>
                </tr>
            `;
        });

        if (data.supplierwise.length === 0) {
            supplierHTML = '<tr><td colspan="7" style="text-align:center; color: var(--text-subtext); padding: 30px;">No supplier data found for selected period.</td></tr>';
        } else {
            const supTotalMargin = supTotalRevenue > 0 ? ((supTotalProfit / supTotalRevenue) * 100).toFixed(2) : '0.00';
            supplierHTML += `
                <tr class="total-row">
                    <td colspan="2"><strong>TOTAL</strong></td>
                    <td class="amount-cell"></td>
                    <td class="amount-cell positive">${formatCurrency(supTotalRevenue)}</td>
                    <td class="amount-cell negative">${formatCurrency(supTotalCOGS)}</td>
                    <td class="amount-cell ${supTotalProfit >= 0 ? 'positive' : 'negative'}">${formatCurrency(supTotalProfit)}</td>
                    <td class="amount-cell">${supTotalMargin}%</td>
                </tr>
            `;
        }

        supplierTableBody.innerHTML = supplierHTML;

                // Build partner-wise distribution table (based on Net Profit)
        await renderPartnerTable(netProfit);

        // Show results
        summaryCards.style.display = 'grid';
        reportResults.style.display = 'block';

        validationMessage.className = 'validation-message validation-success';
        validationText.textContent = 'Report generated successfully.';
        validationMessage.style.display = 'flex';

        reportResults.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
        validationMessage.className = 'validation-message validation-error';
        validationText.textContent = 'Error: ' + error.message;
        validationMessage.style.display = 'flex';
    }
}

// Reset form
function resetForm() {
    startDateEl.value = formatDate(firstDay);
    endDateEl.value = formatDate(today);
    branchFilterEl.value = 'all';
    companyFilterEl.value = '';
    categoryFilterEl.value = '';
    customerFilterEl.value = '';
    productFilterEl.value = '';
    invoiceFilterEl.value = '';
    supplierFilterEl.value = '';
    categoryFilterSearchEl.value = '';
    customerFilterSearchEl.value = '';
    productFilterSearchEl.value = '';
    invoiceFilterSearchEl.value = '';
    supplierFilterSearchEl.value = '';

    summaryCards.style.display = 'none';
    reportResults.style.display = 'none';
    validationMessage.style.display = 'none';

    startDateEl.classList.remove('error');
    endDateEl.classList.remove('error');

    validationMessage.className = 'validation-message validation-success';
    validationText.textContent = 'Form has been reset to default values.';
    validationMessage.style.display = 'flex';
}

// Export functions
function exportToPDF() {
    alert('PDF export functionality will be implemented.');
}

function exportToCSV() {
    alert('CSV export functionality will be implemented.');
}

function printReport() {
    const activeTab = document.querySelector('.tab-btn.active').getAttribute('data-tab');
    let reportData = [];
    
    if (activeTab === 'itemwise') {
        reportData = window.currentData.itemwise;
    } else if (activeTab === 'categorywise') {
        reportData = window.currentData.categorywise;
    } else if (activeTab === 'customerwise') {
        reportData = window.currentData.customerwise;
    } else if (activeTab === 'invoicewise') {
        reportData = window.currentData.invoicewise;
    } else if (activeTab === 'supplierwise') {
        reportData = window.currentData.supplierwise;
    } else if (activeTab === 'partnerwise') {
        // Partner data comes from a separate endpoint; renderPartnerTable()
        // stashes it on window.currentData.partnerwise for printing.
        if (!window.currentData.partnerwise || !window.currentData.partnerwise.partners || window.currentData.partnerwise.partners.length === 0) {
            alert('No partner data available to print. Add partners from Chart of Accounts first.');
            return;
        }
        reportData = window.currentData.partnerwise;
    }
    
    const dataJson = encodeURIComponent(JSON.stringify(reportData));
    const url = `print.php?start_date=${startDateEl.value}&end_date=${endDateEl.value}&report_type=${activeTab}&data=${dataJson}&currency_id=${currencyFilterEl.value}`;
    window.open(url, '_blank');
}

function toggleHelp() {
    helpSection.style.display = helpSection.style.display === 'none' ? 'block' : 'none';
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabName = btn.getAttribute('data-tab');
        
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        btn.classList.add('active');
        document.getElementById(tabName).classList.add('active');
    });
});

// Event Listeners
generateReportBtn.addEventListener('click', generateReport);
resetBtn.addEventListener('click', resetForm);
helpBtn.addEventListener('click', toggleHelp);
exportPDFBtn.addEventListener('click', exportToPDF);
exportCSVBtn.addEventListener('click', exportToCSV);
printBtn.addEventListener('click', printReport);
refreshBtn.addEventListener('click', generateReport);
startDateEl.addEventListener('change', validateForm);
endDateEl.addEventListener('change', validateForm);
currencyFilterEl.addEventListener('change', function() {
    // Update currency symbol when currency changes
    const selectedOption = currencyFilterEl.options[currencyFilterEl.selectedIndex];
    if (selectedOption && selectedOption.dataset.symbol) {
        window.CURRENCY_SYMBOL = selectedOption.dataset.symbol;
    }
    // Regenerate report if data exists
    if (window.currentData) {
        generateReport();
    }
});