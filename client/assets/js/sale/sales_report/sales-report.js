let reportData = null;

// ---- Round Off helpers ----
function isRoundOffEnabled() {
    return document.getElementById('round-off-toggle')?.checked || false;
}

function formatMoney(value) {
    const amount = parseFloat(value) || 0;
    return CURRENCY_SYMBOL + (isRoundOffEnabled()
        ? Math.round(amount).toLocaleString()
        : amount.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 }));
}

function formatQty(value) {
    const amount = parseFloat(value) || 0;
    return isRoundOffEnabled()
        ? Math.round(amount).toLocaleString()
        : amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

document.getElementById('current-date').textContent = new Date().toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
});

loadCompanies();
loadProjects();

async function fetchReportData() {
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const reportType = document.getElementById('report-type').value;
    const company = document.getElementById('company').value;
    const project = document.getElementById('project').value;

    try {
        let url = `../../../../server/api/sale/sales_report/sales-report.php?date_from=${dateFrom}&date_to=${dateTo}&report_type=${reportType}`;
        if (company) url += `&company_id=${company}`;
        if (project) url += `&project_id=${project}`;

        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            reportData = result.data;
            updateDashboard();
        } else {
            console.error('Error:', result.error);
        }
    } catch (error) {
        console.error('Fetch error:', error);
    }
}

function updateDashboard() {
    if (!reportData) return;

    updateSummaryCards();
    populateOfficerTable();
    populateProductTable();
    populateBranchTable();
    populateCategoryTable();
    populateTerritoryTable();
    populateInvoiceTable();
    populateItemCompanyTable();
    populateVendorTable();
    populateCustomerTable();
    populateSupplierManTable();
    initializeCharts();
    filterReportType();
}

// Helper to render batch/expiry cell text (flags near-expiry batches in red).
// Item Company is NOT shown here anymore - it has its own dedicated column.
function formatExpiryCell(expiryValue) {
    if (!expiryValue) {
        return '-';
    }
    const firstDate = expiryValue.split(',')[0].trim();
    const isExpiringSoon = firstDate && !isNaN(new Date(firstDate)) && (new Date(firstDate) - new Date()) / (1000*60*60*24) <= 30;

    return isExpiringSoon
        ? `<span style="color:#d64545; font-weight:600;">${expiryValue}</span>`
        : expiryValue;
}

function formatBatchCell(batchValue) {
    return batchValue && batchValue.length ? batchValue : '-';
}

// Helper for the dedicated Item Company column
function formatItemCompanyCell(itemCompanyValue) {
    return itemCompanyValue && itemCompanyValue.length ? itemCompanyValue : '-';
}

// Load Projects
async function loadProjects() {
    try {
        const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('project');
            data.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Load Companies
async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/sale/sales_report/get-companies.php');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('company');
            data.companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

function populateBranchTable() {
    const tableBody = document.getElementById('branchTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.branch_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.branch_sales.forEach((item, index) => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'branch-row';
        row.setAttribute('data-search', `${item.branch_id_display} ${item.branch_name}`.toLowerCase());
        row.innerHTML = `
            <td data-col="branch_id"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.branch_id_display}</td>
            <td data-col="branch_name">${item.branch_name}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="transactions">${formatQty(item.transactions)}</td>
            <td data-col="avg_sale">${formatMoney(item.avg_sale)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="8" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #E8B23F;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Daily Breakdown</div>
                    <div id="branch-breakdown-${index}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleBreakdown(row, detailRow, 'branch', item.branch_id, index));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-branch', '.branch-row', ['data-search']);
    setupPagination('branchTableBody', 50);
    setupColumnToggle('branch');
}

function populateCategoryTable() {
    const tableBody = document.getElementById('categoryTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.categories.length) {
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center">No data available</td></tr>';
        return;
    }

    const total = reportData.categories.reduce((sum, item) => sum + parseFloat(item.total || 0), 0);

    reportData.categories.forEach(item => {
        const percentage = total > 0 ? ((parseFloat(item.total || 0) / total) * 100).toFixed(1) : 0;
        const row = document.createElement('tr');
        row.className = 'category-row';
        row.setAttribute('data-search', item.category.toLowerCase());
        row.innerHTML = `
            <td data-col="category">${item.category}</td>
            <td data-col="total_revenue">${formatMoney(item.total)}</td>
            <td data-col="percentage">${percentage}%</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
        `;
        tableBody.appendChild(row);
    });
    setupSearch('search-category', '.category-row', ['data-search']);
    setupPagination('categoryTableBody', 50);
    setupColumnToggle('category');
}

function filterReportType() {
    const reportType = document.getElementById('report-type').value;
    const tables = document.querySelectorAll('.table-card');
    const charts = document.querySelectorAll('.chart-card');
    const officerTable = tables[0];
    const supplierManTable = tables[1];
    const productTable = tables[2];
    const branchTable = tables[3];
    const categoryTable = tables[4];
    const territoryTable = tables[5];
    const invoiceTable = tables[6];
    const itemCompanyTable = tables[7];
    const vendorTable = tables[8];
    const customerTable = tables[9];

    if (!officerTable || !supplierManTable || !productTable || !branchTable || !categoryTable || !territoryTable || !invoiceTable || !itemCompanyTable || !vendorTable || !customerTable) return;

    tables.forEach(table => table.style.display = 'none');
    charts.forEach(chart => chart.style.display = 'none');

    if (reportType === 'all') {
        officerTable.style.display = 'block';
        supplierManTable.style.display = 'block';
        productTable.style.display = 'block';
        branchTable.style.display = 'block';
        categoryTable.style.display = 'block';
        territoryTable.style.display = 'block';
        invoiceTable.style.display = 'block';
        itemCompanyTable.style.display = 'block';
        vendorTable.style.display = 'block';
        customerTable.style.display = 'block';
        charts.forEach(chart => chart.style.display = 'block');
    } else if (reportType === 'officer') {
        officerTable.style.display = 'block';
        charts[2].style.display = 'block';
        charts[4].style.display = 'block';
    } else if (reportType === 'product') {
        productTable.style.display = 'block';
        charts[3].style.display = 'block';
        charts[1].style.display = 'block';
    } else if (reportType === 'branch') {
        branchTable.style.display = 'block';
        charts[5].style.display = 'block';
        charts[6].style.display = 'block';
    } else if (reportType === 'territory') {
        territoryTable.style.display = 'block';
        charts[0].style.display = 'block';
    } else if (reportType === 'invoice') {
        invoiceTable.style.display = 'block';
        charts[0].style.display = 'block';
        charts[4].style.display = 'block';
    } else if (reportType === 'vendor') {
        vendorTable.style.display = 'block';
        charts[4].style.display = 'block';
    } else if (reportType === 'customer') {
        customerTable.style.display = 'block';
        charts[0].style.display = 'block';
        charts[4].style.display = 'block';
    } else if (reportType === 'supplier_man') {
        supplierManTable.style.display = 'block';
        charts[0].style.display = 'block';
        charts[4].style.display = 'block';
    } else if (reportType === 'item_company') {
        itemCompanyTable.style.display = 'block';
    } else if (reportType === 'category') {
        categoryTable.style.display = 'block';
        charts[1].style.display = 'block';
        charts[3].style.display = 'block';
    }
}

function updateSummaryCards() {
    const cards = document.querySelectorAll('.card-value');

    const totalSales = (reportData.officer_sales || []).reduce((sum, item) => sum + parseFloat(item.total_sales || 0), 0);
    const totalCustomers = (reportData.customer_sales || []).length;
    const totalTransactions = (reportData.branch_sales || []).reduce((sum, item) => sum + parseFloat(item.transactions || 0), 0);
    const avgSale = totalTransactions > 0 ? totalSales / totalTransactions : 0;

    if (cards[0]) cards[0].textContent = formatMoney(totalSales);
    if (cards[1]) cards[1].textContent = formatQty(totalCustomers);
    if (cards[2]) cards[2].textContent = formatQty(totalTransactions);
    if (cards[3]) cards[3].textContent = formatMoney(avgSale);
}

function populateOfficerTable() {
    const tableBody = document.getElementById('officerTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.officer_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.officer_sales.forEach((item, index) => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'officer-row';
        row.setAttribute('data-search', `${item.employee_id} ${item.officer_name}`.toLowerCase());
        row.innerHTML = `
            <td data-col="employee_id"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.employee_id}</td>
            <td data-col="officer_name">${item.officer_name}</td>
            <td data-col="total_invoices">${formatQty(item.total_invoices)}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
            <td data-col="status"><span class="badge ${item.status === 'Active' ? 'badge-success' : 'badge-warning'}">${item.status}</span></td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="8" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #1F7BFF;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Daily Breakdown</div>
                    <div id="officer-breakdown-${index}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleBreakdown(row, detailRow, 'officer', item.sale_officer_id, index));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-officer', '.officer-row', ['data-search']);
    setupPagination('officerTableBody', 50);
    setupColumnToggle('officer');
}

function populateProductTable() {
    const tableBody = document.getElementById('productTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.product_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.product_sales.forEach((item, index) => {
        const growth = item.growth;
        let growthHtml = '-';
        if (growth !== null && growth !== undefined) {
            const growthClass = growth >= 0 ? 'positive' : 'negative';
            const growthIcon = growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
            growthHtml = `<span class="card-change ${growthClass}"><i class="fas ${growthIcon}"></i> ${Math.abs(growth)}%</span>`;
        }

        const isChild = item.parent_row_id || item.parent_product_id;
        const indent = isChild ? 'padding-left: 32px;' : '';
        const prefix = isChild ? '→ ' : '';

        const batchNo = item.batch_no || '-';
        const expiryDate = item.nearest_expiry || '-';
        const isExpiringSoon = item.nearest_expiry && (new Date(item.nearest_expiry) - new Date()) / (1000*60*60*24) <= 30;
        const expiryHtml = isExpiringSoon
            ? `<span style="color:#d64545; font-weight:600;">${expiryDate}</span>`
            : expiryDate;
        const itemCompany = formatItemCompanyCell(item.item_company_name);

        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'product-row';
        row.setAttribute('data-search', `${item.product_id_display} ${item.product_name} ${item.category}`.toLowerCase());
        row.innerHTML = `
            <td data-col="product_id" style="${indent}"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.product_id_display}</td>
            <td data-col="product_name" style="${indent}">${prefix}${item.product_name}</td>
            <td data-col="category">${item.category}</td>
            <td data-col="batch_no">${batchNo}</td>
            <td data-col="expiry_date">${expiryHtml}</td>
            <td data-col="item_company">${itemCompany}</td>
            <td data-col="quantity_sold">${formatQty(item.quantity_sold)}${item.unit_symbol ? ' ' + item.unit_symbol : ''}</td>
            <td data-col="revenue">${formatMoney(item.revenue)}</td>
            <td data-col="growth">${growthHtml}</td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="10" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #2FBF71;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Invoices Including This Product</div>
                    <div id="product-details-${index}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleDetails(row, detailRow, 'product_invoices', item.product_id, index));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-product', '.product-row', ['data-search']);
    setupPagination('productTableBody', 50);
    setupColumnToggle('product');
}

function populateTerritoryTable() {
    const tableBody = document.getElementById('territoryTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.territory_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.territory_sales.forEach((item, index) => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'territory-row';
        row.setAttribute('data-search', `${item.country} ${item.region} ${item.city} ${item.zone} ${item.area}`.toLowerCase());
        row.innerHTML = `
            <td data-col="country"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.country}</td>
            <td data-col="region">${item.region}</td>
            <td data-col="city">${item.city}</td>
            <td data-col="zone">${item.zone}</td>
            <td data-col="area">${item.area}</td>
            <td data-col="total_invoices">${formatQty(item.total_invoices)}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="10" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #2FBF71;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Territory Invoices</div>
                    <div id="territory-details-${index}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleTerritoryDetails(row, detailRow, item, index));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-territory', '.territory-row', ['data-search']);
    setupPagination('territoryTableBody', 50);
    setupColumnToggle('territory');
}

function populateInvoiceTable() {
    const tableBody = document.getElementById('invoiceTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.invoice_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.invoice_sales.forEach(item => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'invoice-row';
        row.setAttribute('data-search', `${item.bill_no} ${item.customer_name} ${item.officer_name}`.toLowerCase());
        row.innerHTML = `
            <td data-col="bill_no"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.bill_no}</td>
            <td data-col="sale_date">${item.sale_date}</td>
            <td data-col="customer_name">${item.customer_name}</td>
            <td data-col="officer_name">${item.officer_name}</td>
            <td data-col="total_bill">${formatMoney(item.total_bill)}</td>
            <td data-col="discount">${formatMoney(item.total_discount_amount)}</td>
            <td data-col="net_amount">${formatMoney(item.net_amount)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="10" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #E8B23F;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Invoice Items</div>
                    <div id="invoice-details-${item.id}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleDetails(row, detailRow, 'invoice_items', item.id, item.id));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-invoice', '.invoice-row', ['data-search']);
    setupPagination('invoiceTableBody', 50);
    setupColumnToggle('invoice');
}

function populateVendorTable() {
    const tableBody = document.getElementById('vendorTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.vendor_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.vendor_sales.forEach(item => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'vendor-row';
        row.setAttribute('data-search', `${item.vendor_id_display} ${item.vendor_name}`.toLowerCase());
        row.innerHTML = `
            <td data-col="vendor_id"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.vendor_id_display}</td>
            <td data-col="vendor_name">${item.vendor_name}</td>
            <td data-col="total_invoices">${formatQty(item.total_invoices)}</td>
            <td data-col="total_quantity">${formatQty(item.total_quantity)}${item.unit_symbol ? ' ' + item.unit_symbol : ''}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="8" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #946CE6;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Products Sold</div>
                    <div id="vendor-details-${item.vendor_id}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleDetails(row, detailRow, 'vendor_products', item.vendor_id, item.vendor_id));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-vendor', '.vendor-row', ['data-search']);
    setupPagination('vendorTableBody', 50);
    setupColumnToggle('vendor');
}

function populateItemCompanyTable() {
    const tableBody = document.getElementById('itemCompanyTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.item_company_sales || !reportData.item_company_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.item_company_sales.forEach(item => {
        const row = document.createElement('tr');
        row.className = 'item-company-row';
        row.setAttribute('data-search', (item.item_company_name || '').toLowerCase());
        row.innerHTML = `
            <td data-col="item_company">${item.item_company_name || 'Unknown'}</td>
            <td data-col="total_invoices">${formatQty(item.total_invoices)}</td>
            <td data-col="total_quantity">${formatQty(item.total_quantity)}${item.unit_symbol ? ' ' + item.unit_symbol : ''}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
        `;
        tableBody.appendChild(row);
    });
    setupSearch('search-item-company', '.item-company-row', ['data-search']);
    setupPagination('itemCompanyTableBody', 50);
    setupColumnToggle('item_company');
}

function populateCustomerTable() {
    const tableBody = document.getElementById('customerTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.customer_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.customer_sales.forEach(item => {
        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'customer-row';
        row.setAttribute('data-search', `${item.customer_id_display} ${item.customer_name}`.toLowerCase());
        row.innerHTML = `
            <td data-col="customer_id"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.customer_id_display}</td>
            <td data-col="customer_name">${item.customer_name}</td>
            <td data-col="total_invoices">${formatQty(item.total_invoices)}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="7" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #1F7BFF;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Customer Invoices</div>
                    <div id="customer-details-${item.customer_id}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleDetails(row, detailRow, 'customer_invoices', item.customer_id, item.customer_id));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-customer', '.customer-row', ['data-search']);
    setupPagination('customerTableBody', 50);
    setupColumnToggle('customer');
}

function populateSupplierManTable() {
    const tableBody = document.getElementById('supplierManTableBody');
    tableBody.innerHTML = '';

    if (!reportData || !reportData.supplier_man_sales.length) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center">No data available</td></tr>';
        return;
    }

    reportData.supplier_man_sales.forEach((item, index) => {
        if (!item.supplier_man_id) return;

        const row = document.createElement('tr');
        row.style.cursor = 'pointer';
        row.className = 'supplier-man-row';
        row.setAttribute('data-search', `${item.employee_id} ${item.supplier_man_name}`.toLowerCase());
        row.innerHTML = `
            <td data-col="employee_id"><i class="fas fa-chevron-right" style="margin-right: 8px; transition: transform 0.3s;"></i>${item.employee_id}</td>
            <td data-col="supplier_man_name">${item.supplier_man_name}</td>
            <td data-col="total_invoices">${formatQty(item.total_invoices)}</td>
            <td data-col="total_sales">${formatMoney(item.total_sales)}</td>
            <td data-col="batch_no">${formatBatchCell(item.batch_no)}</td>
            <td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td>
            <td data-col="item_company">${formatItemCompanyCell(item.item_company_name)}</td>
            <td data-col="status"><span class="badge ${item.status === 'Active' ? 'badge-success' : 'badge-warning'}">${item.status}</span></td>
        `;

        const detailRow = document.createElement('tr');
        detailRow.style.display = 'none';
        detailRow.innerHTML = `
            <td colspan="8" style="padding: 0;">
                <div style="padding: 16px; background: #f7f9fc; border-left: 3px solid #946CE6;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Daily Breakdown</div>
                    <div id="supplier_man-breakdown-${index}">Loading...</div>
                </div>
            </td>
        `;

        row.addEventListener('click', () => toggleBreakdown(row, detailRow, 'supplier_man', item.supplier_man_id, index));

        tableBody.appendChild(row);
        tableBody.appendChild(detailRow);
    });
    setupSearch('search-supplier-man', '.supplier-man-row', ['data-search']);
    setupPagination('supplierManTableBody', 50);
    setupColumnToggle('supplier_man');
}

// Chart initialization (unchanged - kept same for brevity)
let trendChart = null;
let categoryChart = null;
let topOfficersChart = null;
let topProductsChart = null;
let topVendorsChart = null;
let branchComparisonChart = null;
let monthlyComparisonChart = null;

function initializeCharts() {
    if (!reportData) return;

    const trendLabels = (reportData.trend || []).length > 0 ? reportData.trend.map(t => t.month) : ['No Data'];
    const trendData = (reportData.trend || []).length > 0 ? reportData.trend.map(t => parseFloat(t.total || 0)) : [0];

    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    if (trendChart) trendChart.destroy();
    trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Sales (' + CURRENCY_SYMBOL + ')',
                data: trendData,
                borderColor: '#1F7BFF',
                backgroundColor: 'rgba(31, 123, 255, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(225, 230, 238, 0.5)' },
                    ticks: { callback: value => CURRENCY_SYMBOL + value.toLocaleString() }
                },
                x: { grid: { color: 'rgba(225, 230, 238, 0.5)' } }
            }
        }
    });

    const catLabels = (reportData.categories || []).length > 0 ? reportData.categories.map(c => c.category) : ['No Data'];
    const catData = (reportData.categories || []).length > 0 ? reportData.categories.map(c => parseFloat(c.total || 0)) : [0];

    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    if (categoryChart) categoryChart.destroy();
    categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: catLabels,
            datasets: [{
                data: catData,
                backgroundColor: ['#1F7BFF', '#2FBF71', '#E8B23F', '#946CE6'],
                borderWidth: 1,
                borderColor: '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' }
                }
            },
            cutout: '65%'
        }
    });

    const topOfficers = (reportData.officer_sales || []).slice(0, 5);
    const officerLabels = topOfficers.map(o => o.officer_name);
    const officerData = topOfficers.map(o => parseFloat(o.total_sales || 0));

    const topOfficersCtx = document.getElementById('topOfficersChart').getContext('2d');
    if (topOfficersChart) topOfficersChart.destroy();
    topOfficersChart = new Chart(topOfficersCtx, {
        type: 'bar',
        data: {
            labels: officerLabels,
            datasets: [{
                label: 'Sales (' + CURRENCY_SYMBOL + ')',
                data: officerData,
                backgroundColor: '#1F7BFF',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(225, 230, 238, 0.5)' },
                    ticks: { callback: value => CURRENCY_SYMBOL + value.toLocaleString() }
                },
                x: { grid: { display: false } }
            }
        }
    });

    const topProducts = (reportData.product_sales || []).filter(p => !p.parent_product_id && !p.parent_row_id).slice(0, 5);
    const productLabels = topProducts.map(p => p.product_name);
    const productData = topProducts.map(p => parseFloat(p.revenue || 0));

    const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
    if (topProductsChart) topProductsChart.destroy();
    topProductsChart = new Chart(topProductsCtx, {
        type: 'bar',
        data: {
            labels: productLabels,
            datasets: [{
                label: 'Revenue (' + CURRENCY_SYMBOL + ')',
                data: productData,
                backgroundColor: '#2FBF71',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(225, 230, 238, 0.5)' },
                    ticks: { callback: value => CURRENCY_SYMBOL + value.toLocaleString() }
                },
                y: { grid: { display: false } }
            }
        }
    });

    const topVendors = (reportData.vendor_sales || []).slice(0, 5);
    const vendorLabels = topVendors.map(v => v.vendor_name);
    const vendorData = topVendors.map(v => parseFloat(v.total_sales || 0));

    const topVendorsCtx = document.getElementById('topVendorsChart').getContext('2d');
    if (topVendorsChart) topVendorsChart.destroy();
    topVendorsChart = new Chart(topVendorsCtx, {
        type: 'bar',
        data: {
            labels: vendorLabels,
            datasets: [{
                label: 'Sales (' + CURRENCY_SYMBOL + ')',
                data: vendorData,
                backgroundColor: '#946CE6',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(225, 230, 238, 0.5)' },
                    ticks: { callback: value => CURRENCY_SYMBOL + value.toLocaleString() }
                },
                x: { grid: { display: false } }
            }
        }
    });

    const topBranches = (reportData.branch_sales || []).slice(0, 5);
    const branchLabels = topBranches.map(b => b.branch_name);
    const branchData = topBranches.map(b => parseFloat(b.total_sales || 0));

    const branchComparisonCtx = document.getElementById('branchComparisonChart').getContext('2d');
    if (branchComparisonChart) branchComparisonChart.destroy();
    branchComparisonChart = new Chart(branchComparisonCtx, {
        type: 'bar',
        data: {
            labels: branchLabels,
            datasets: [{
                label: 'Sales (' + CURRENCY_SYMBOL + ')',
                data: branchData,
                backgroundColor: '#E8B23F',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(225, 230, 238, 0.5)' },
                    ticks: { callback: value => CURRENCY_SYMBOL + value.toLocaleString() }
                },
                x: { grid: { display: false } }
            }
        }
    });

    const monthlyLabels = (reportData.trend || []).map(t => t.month);
    const currentData = (reportData.trend || []).map(t => parseFloat(t.total || 0));
    const previousData = currentData.map((val, idx) => idx > 0 ? currentData[idx - 1] * 0.85 : val * 0.85);

    const monthlyComparisonCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
    if (monthlyComparisonChart) monthlyComparisonChart.destroy();
    monthlyComparisonChart = new Chart(monthlyComparisonCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [
                {
                    label: 'Current Period',
                    data: currentData,
                    backgroundColor: '#1F7BFF',
                    borderRadius: 6
                },
                {
                    label: 'Previous Period',
                    data: previousData,
                    backgroundColor: '#E8B23F',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(225, 230, 238, 0.5)' },
                    ticks: { callback: value => CURRENCY_SYMBOL + value.toLocaleString() }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

document.getElementById('apply-filter').addEventListener('click', function () {
    document.querySelectorAll('.report-card').forEach(card => card.style.opacity = '0.7');
    fetchReportData().then(() => {
        document.querySelectorAll('.report-card').forEach(card => card.style.opacity = '1');
    });
});

document.getElementById('refresh-btn').addEventListener('click', function () {
    const icon = this.querySelector('i');
    icon.style.animation = 'spin 1s linear';
    document.querySelectorAll('.report-card').forEach(card => card.style.opacity = '0.7');
    fetchReportData().then(() => {
        document.querySelectorAll('.report-card').forEach(card => card.style.opacity = '1');
        icon.style.animation = '';
    });
});

const REPORT_TYPE_SEARCH_INPUT = {
    officer: 'search-officer',
    supplier_man: 'search-supplier-man',
    product: 'search-product',
    branch: 'search-branch',
    category: 'search-category',
    territory: 'search-territory',
    invoice: 'search-invoice',
    vendor: 'search-vendor',
    customer: 'search-customer'
};

function printReport() {
    const dateFrom = document.getElementById('date-from').value;
    const dateTo = document.getElementById('date-to').value;
    const reportType = document.getElementById('report-type').value;
    const company = document.getElementById('company').value;
    let printUrl = `print.php?date_from=${dateFrom}&date_to=${dateTo}&report_type=${reportType}`;
    if (company) printUrl += `&company_id=${company}`;

    const searchInputId = REPORT_TYPE_SEARCH_INPUT[reportType];
    const searchTerm = searchInputId ? (document.getElementById(searchInputId)?.value || '').trim() : '';
    if (searchTerm) printUrl += `&search=${encodeURIComponent(searchTerm)}`;

    window.open(printUrl, '_blank');
}

async function toggleBreakdown(row, detailRow, type, id, index) {
    const icon = row.querySelector('i.fa-chevron-right');

    if (detailRow.style.display === 'none') {
        icon.style.transform = 'rotate(90deg)';
        detailRow.style.display = 'table-row';

        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;

        try {
            const response = await fetch(`../../../../server/api/sale/sales_report/breakdown.php?type=${type}&id=${id}&date_from=${dateFrom}&date_to=${dateTo}`);
            const result = await response.json();

            if (result.success) {
                const container = document.getElementById(`${type}-breakdown-${index}`);

                if (!container) {
                    console.error(`Container not found: ${type}-breakdown-${index}`);
                    return;
                }

                const data = result.data;

                const headers = type === 'branch' ? '<tr><th data-col="bd_date">Date</th><th>Quantity</th><th>Unit</th><th data-col="bd_revenue">Revenue</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th></tr>' :
                               type === 'officer' ? '<tr><th data-col="bd_date">Date</th><th data-col="bd_invoices">Invoices</th><th data-col="bd_revenue">Revenue</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th></tr>' :
                               type === 'supplier_man' ? '<tr><th data-col="bd_date">Date</th><th data-col="bd_invoices">Invoices</th><th data-col="bd_revenue">Revenue</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th></tr>' :
                               '<tr><th>Date</th><th>Quantity</th><th>Revenue</th></tr>';

                let html = `
                    <div style="margin-bottom: 12px; display: flex; gap: 8px; align-items: center;">
                        <input type="date" id="search-${type}-${index}"
                            style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;" />
                        <button onclick="document.getElementById('search-${type}-${index}').value=''; document.getElementById('search-${type}-${index}').dispatchEvent(new Event('input'));"
                            style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; background: white; cursor: pointer; font-size: 13px;">Clear</button>
                        <span style="font-size: 12px; color: #666;">Showing ${data.length} records</span>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table style="width: 100%; font-size: 13px;" id="table-${type}-${index}">
                            <thead>${headers}</thead>
                            <tbody>`;

                data.forEach(item => {
                    const batchCell = formatBatchCell(item.batch_no);
                    const expiryCell = formatExpiryCell(item.nearest_expiry);
                    const itemCompanyCell = formatItemCompanyCell(item.item_company_name);
                    if (type === 'branch') {
                        html += `<tr class="breakdown-row"><td data-col="bd_date">${item.date}</td><td data-col="quantity_sold">${formatQty(item.volume)}</td><td data-col="quantity_sold">${item.unit || '-'}</td><td data-col="bd_revenue">${formatMoney(item.revenue)}</td><td data-col="batch_no">${batchCell}</td><td data-col="expiry_date">${expiryCell}</td><td data-col="item_company">${itemCompanyCell}</td></tr>`;
                    } else if (type === 'officer' || type === 'supplier_man') {
                        html += `<tr class="breakdown-row"><td data-col="bd_date">${item.date}</td><td data-col="bd_invoices">${formatQty(item.invoices)}</td><td data-col="bd_revenue">${formatMoney(item.revenue)}</td><td data-col="batch_no">${batchCell}</td><td data-col="expiry_date">${expiryCell}</td><td data-col="item_company">${itemCompanyCell}</td></tr>`;
                    } else {
                        html += `<tr class="breakdown-row"><td>${item.date}</td><td>${formatQty(item.volume)}</td><td>${formatMoney(item.revenue)}</td></tr>`;
                    }
                });

                html += '</tbody></table></div>';
                container.innerHTML = html;
                applyColumnVisibility(DETAIL_TYPE_TO_PREFIX[type] || type);

                document.getElementById(`search-${type}-${index}`).addEventListener('input', function(e) {
                    const searchValue = e.target.value;
                    const rows = document.querySelectorAll(`#table-${type}-${index} .breakdown-row`);
                    rows.forEach(row => {
                        const dateCell = row.cells[0].textContent;
                        row.style.display = searchValue === '' || dateCell === searchValue ? '' : 'none';
                    });
                });
            } else {
                const container = document.getElementById(`${type}-breakdown-${index}`);
                if (container) {
                    container.innerHTML = 'No breakdown data available';
                }
            }
        } catch (error) {
            console.error('Error loading breakdown:', error);
            const container = document.getElementById(`${type}-breakdown-${index}`);
            if (container) {
                container.innerHTML = 'Error loading breakdown';
            }
        }
    } else {
        icon.style.transform = 'rotate(0deg)';
        detailRow.style.display = 'none';
    }
}

async function toggleDetails(row, detailRow, type, id, index) {
    const icon = row.querySelector('i.fa-chevron-right');

    if (detailRow.style.display === 'none') {
        icon.style.transform = 'rotate(90deg)';
        detailRow.style.display = 'table-row';

        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;

        try {
            const response = await fetch(`../../../../server/api/sale/sales_report/details.php?type=${type}&id=${id}&date_from=${dateFrom}&date_to=${dateTo}`);
            const result = await response.json();

            if (result.success) {
                const container = document.getElementById(`${type.split('_')[0]}-details-${index}`);
                const data = result.data;

                let html = '<div style="max-height: 300px; overflow-y: auto;"><table style="width: 100%; font-size: 13px;">';

                if (type === 'product_invoices') {
                    html += '<thead><tr><th data-col="pi_bill_no">Invoice #</th><th data-col="pi_date">Date</th><th data-col="pi_customer">Customer</th><th>Quantity</th><th data-col="pi_price">Price</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th><th data-col="pi_amount">Amount</th></tr></thead><tbody>';
                    data.forEach(item => {
                        const expiringSoon = item.expiry_date && (new Date(item.expiry_date) - new Date()) / (1000*60*60*24) <= 30;
                        const expiryCell = item.expiry_date
                            ? (expiringSoon ? `<span style="color:#d64545; font-weight:600;">${item.expiry_date}</span>` : item.expiry_date)
                            : '-';
                        html += `<tr><td data-col="pi_bill_no">${item.bill_no}</td><td data-col="pi_date">${item.sale_date}</td><td data-col="pi_customer">${item.customer_name}</td><td data-col="quantity_sold">${formatQty(item.quantity)}${item.unit_symbol ? ' ' + item.unit_symbol : ''}</td><td data-col="pi_price">${formatMoney(item.sale_price)}</td><td data-col="batch_no">${item.batch_no || '-'}</td><td data-col="expiry_date">${expiryCell}</td><td data-col="item_company">${item.item_company_name || '-'}</td><td data-col="pi_amount">${formatMoney(item.net_amount)}</td></tr>`;
                    });
                } else if (type === 'vendor_products') {
                    html += '<thead><tr><th data-col="vp_product">Product</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th><th>Quantity</th><th data-col="vp_total_sales">Total Sales</th></tr></thead><tbody>';
                    data.forEach(item => {
                        const expiringSoon = item.nearest_expiry && (new Date(item.nearest_expiry) - new Date()) / (1000*60*60*24) <= 30;
                        const expiryCell = item.nearest_expiry
                            ? (expiringSoon ? `<span style="color:#d64545; font-weight:600;">${item.nearest_expiry}</span>` : item.nearest_expiry)
                            : '-';
                        html += `<tr><td data-col="vp_product">${item.product_name}</td><td data-col="batch_no">${item.batch_no || '-'}</td><td data-col="expiry_date">${expiryCell}</td><td data-col="item_company">${item.item_company_name || '-'}</td><td data-col="total_quantity">${formatQty(item.total_quantity)}${item.unit_symbol ? ' ' + item.unit_symbol : ''}</td><td data-col="vp_total_sales">${formatMoney(item.total_sales)}</td></tr>`;
                    });
                } else if (type === 'customer_invoices') {
                    html += '<thead><tr><th data-col="ci_bill_no">Invoice #</th><th data-col="ci_date">Date</th><th data-col="ci_total_bill">Total Bill</th><th data-col="ci_discount">Discount</th><th data-col="ci_net_amount">Net Amount</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th></tr></thead><tbody>';
                    data.forEach(item => {
                        html += `<tr><td data-col="ci_bill_no">${item.bill_no}</td><td data-col="ci_date">${item.sale_date}</td><td data-col="ci_total_bill">${formatMoney(item.total_bill)}</td><td data-col="ci_discount">${formatMoney(item.total_discount_amount)}</td><td data-col="ci_net_amount">${formatMoney(item.net_amount)}</td><td data-col="batch_no">${formatBatchCell(item.batch_no)}</td><td data-col="expiry_date">${formatExpiryCell(item.nearest_expiry)}</td><td data-col="item_company">${item.item_company_name || '-'}</td></tr>`;
                    });
                } else if (type === 'invoice_items') {
                    html += '<thead><tr><th data-col="ii_product">Product</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th><th>Quantity</th><th data-col="ii_price">Price</th><th data-col="ii_discount">Discount</th><th data-col="ii_net_amount">Net Amount</th></tr></thead><tbody>';
                    data.forEach(item => {
                        const expiringSoon = item.expiry_date && (new Date(item.expiry_date) - new Date()) / (1000*60*60*24) <= 30;
                        const expiryCell = item.expiry_date
                            ? (expiringSoon ? `<span style="color:#d64545; font-weight:600;">${item.expiry_date}</span>` : item.expiry_date)
                            : '-';
                        html += `<tr><td data-col="ii_product">${item.product_name}</td><td data-col="batch_no">${item.batch_no || '-'}</td><td data-col="expiry_date">${expiryCell}</td><td data-col="item_company">${item.item_company_name || '-'}</td><td data-col="quantity_sold">${formatQty(item.quantity)}${item.unit_symbol ? ' ' + item.unit_symbol : ''}</td><td data-col="ii_price">${formatMoney(item.sale_price)}</td><td data-col="ii_discount">${formatMoney(item.discount_amount)}</td><td data-col="ii_net_amount">${formatMoney(item.net_amount)}</td></tr>`;
                    });
                }

                html += '</tbody></table></div>';
                container.innerHTML = data.length > 0 ? html : 'No data available';
                applyColumnVisibility(DETAIL_TYPE_TO_PREFIX[type] || type);
            } else {
                document.getElementById(`${type.split('_')[0]}-details-${index}`).innerHTML = 'Error loading details';
            }
        } catch (error) {
            document.getElementById(`${type.split('_')[0]}-details-${index}`).innerHTML = 'Error loading details';
        }
    } else {
        icon.style.transform = 'rotate(0deg)';
        detailRow.style.display = 'none';
    }
}

async function toggleTerritoryDetails(row, detailRow, item, index) {
    const icon = row.querySelector('i.fa-chevron-right');

    if (detailRow.style.display === 'none') {
        icon.style.transform = 'rotate(90deg)';
        detailRow.style.display = 'table-row';

        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;

        try {
            const response = await fetch(`../../../../server/api/sale/sales_report/details.php?type=territory_invoices&date_from=${dateFrom}&date_to=${dateTo}&country=${encodeURIComponent(item.country)}&region=${encodeURIComponent(item.region)}&city=${encodeURIComponent(item.city)}&zone=${encodeURIComponent(item.zone)}&area=${encodeURIComponent(item.area)}`);
            const result = await response.json();

            if (result.success) {
                const container = document.getElementById(`territory-details-${index}`);
                const data = result.data;

                let html = '<div style="max-height: 300px; overflow-y: auto;"><table style="width: 100%; font-size: 13px;">';
                html += '<thead><tr><th data-col="ti_bill_no">Invoice #</th><th data-col="ti_date">Date</th><th data-col="ti_customer">Customer</th><th data-col="ti_net_amount">Net Amount</th><th data-col="batch_no">Batch No</th><th data-col="expiry_date">Expiry Date</th><th data-col="item_company">Item Company</th></tr></thead><tbody>';
                data.forEach(d => {
                    html += `<tr><td data-col="ti_bill_no">${d.bill_no}</td><td data-col="ti_date">${d.sale_date}</td><td data-col="ti_customer">${d.customer_name}</td><td data-col="ti_net_amount">${formatMoney(d.net_amount)}</td><td data-col="batch_no">${formatBatchCell(d.batch_no)}</td><td data-col="expiry_date">${formatExpiryCell(d.nearest_expiry)}</td><td data-col="item_company">${d.item_company_name || '-'}</td></tr>`;
                });
                html += '</tbody></table></div>';
                container.innerHTML = data.length > 0 ? html : 'No data available';
                applyColumnVisibility('territory');
            } else {
                document.getElementById(`territory-details-${index}`).innerHTML = 'Error loading details';
            }
        } catch (error) {
            document.getElementById(`territory-details-${index}`).innerHTML = 'Error loading details';
        }
    } else {
        icon.style.transform = 'rotate(0deg)';
        detailRow.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const today = new Date();
    const threeMonthsAgo = new Date();
    threeMonthsAgo.setMonth(today.getMonth() - 3);

    document.getElementById('date-from').valueAsDate = threeMonthsAgo;
    document.getElementById('date-to').valueAsDate = today;

    fetchReportData();

    document.getElementById('round-off-toggle')?.addEventListener('change', () => {
        if (reportData) updateDashboard();
    });
});

function setupSearch(inputId, rowSelector, dataAttributes) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll(rowSelector);

        rows.forEach(row => {
            const matches = dataAttributes.some(attr => {
                const value = row.getAttribute(attr);
                return value && value.includes(searchTerm);
            });
            row.style.display = matches || searchTerm === '' ? '' : 'none';
            const detailRow = row.nextElementSibling;
            if (detailRow && detailRow.classList.contains(rowSelector.substring(1) + '-detail-row')) {
                detailRow.style.display = 'none';
            }
        });
    });
}

// ---- Show/Hide Columns (shared modal, persisted per-table in localStorage) ----
const COLUMN_STORAGE_PREFIX = 'salesReport.columns.';
const COLUMN_TABLE_TITLES = {
    officer: 'Sales Officer Wise', supplier_man: 'Supplier Man Wise', product: 'Product Wise',
    branch: 'Branch Wise', category: 'Category Wise', territory: 'Territory Wise',
    invoice: 'Invoice Wise', item_company: 'Item Company Wise', vendor: 'Vendor Wise', customer: 'Customer Wise'
};
// Columns that only exist inside a table's expandable sub-table (breakdown/detail row),
// with no equivalent in the main table - so they can't be discovered by scanning the
// main table's <thead> the way main-table columns are. Listed statically here so they
// show up in the Columns modal (and stay toggleable) even before that row is ever expanded.
const SUB_TABLE_COLUMNS = {
    officer: { title: 'Daily Breakdown', cols: [
        {key:'bd_date', label:'Date'}, {key:'bd_invoices', label:'Invoices'}, {key:'bd_revenue', label:'Revenue'}
    ]},
    supplier_man: { title: 'Daily Breakdown', cols: [
        {key:'bd_date', label:'Date'}, {key:'bd_invoices', label:'Invoices'}, {key:'bd_revenue', label:'Revenue'}
    ]},
    branch: { title: 'Daily Breakdown', cols: [
        {key:'bd_date', label:'Date'}, {key:'bd_revenue', label:'Revenue'}
    ]},
    product: { title: 'Invoices Including This Product', cols: [
        {key:'pi_bill_no', label:'Invoice #'}, {key:'pi_date', label:'Date'}, {key:'pi_customer', label:'Customer'},
        {key:'pi_price', label:'Price'}, {key:'pi_amount', label:'Amount'}
    ]},
    vendor: { title: 'Products Sold', cols: [
        {key:'vp_product', label:'Product'}, {key:'vp_total_sales', label:'Total Sales'}
    ]},
    customer: { title: 'Customer Invoices', cols: [
        {key:'ci_bill_no', label:'Invoice #'}, {key:'ci_date', label:'Date'}, {key:'ci_total_bill', label:'Total Bill'},
        {key:'ci_discount', label:'Discount'}, {key:'ci_net_amount', label:'Net Amount'}
    ]},
    invoice: { title: 'Invoice Items', cols: [
        {key:'ii_product', label:'Product'}, {key:'ii_price', label:'Price'},
        {key:'ii_discount', label:'Discount'}, {key:'ii_net_amount', label:'Net Amount'}
    ]},
    territory: { title: 'Territory Invoices', cols: [
        {key:'ti_bill_no', label:'Invoice #'}, {key:'ti_date', label:'Date'}, {key:'ti_customer', label:'Customer'},
        {key:'ti_net_amount', label:'Net Amount'}
    ]}
};
let activeColumnsPrefix = null;

function getSavedColumnState(prefix) {
    try {
        const raw = localStorage.getItem(COLUMN_STORAGE_PREFIX + prefix);
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}

function saveColumnState(prefix, state) {
    try {
        localStorage.setItem(COLUMN_STORAGE_PREFIX + prefix, JSON.stringify(state));
    } catch (e) {
        console.error('Could not save column preferences:', e);
    }
}

// Re-applies the saved hide/show state to a table's current header+body cells.
// Must be re-run every time a table's rows are rebuilt (innerHTML wipes prior state).
function applyColumnVisibility(prefix) {
    const btn = document.getElementById(`${prefix}-columns-btn`);
    const table = btn?.closest('.table-card')?.querySelector('table');
    if (!table) return;
    const state = getSavedColumnState(prefix);
    table.querySelectorAll('thead th[data-col]').forEach(th => {
        const col = th.getAttribute('data-col');
        const visible = LOCKED_VISIBLE_COLS.has(col) ? true : (state[col] !== false); // default: visible
        th.style.display = visible ? '' : 'none';
        // querySelectorAll on the main table also reaches nested sub-tables inside
        // expandable breakdown/detail rows, so Batch No/Expiry Date/Item Company
        // toggles apply there too, in sync with the main columns.
        table.querySelectorAll(`td[data-col="${col}"]`).forEach(td => {
            td.style.display = visible ? '' : 'none';
        });
    });
}

// Quantity is never toggleable - the whole point of showing it is that the unit
// (Ctn/Pc/Kg) must always be readable, so it's excluded from hide/show entirely.
const LOCKED_VISIBLE_COLS = new Set(['quantity_sold', 'total_quantity']);

// Which expandable sub-table (breakdown/detail row) belongs to which main table's
// Columns modal, so toggling Batch No / Expiry Date / Item Company up top also
// hides/shows that same column inside the drilldown rows underneath.
const DETAIL_TYPE_TO_PREFIX = {
    officer: 'officer', supplier_man: 'supplier_man', branch: 'branch',
    product_invoices: 'product', vendor_products: 'vendor',
    customer_invoices: 'customer', invoice_items: 'invoice', territory_invoices: 'territory'
};

function setupColumnToggle(prefix) {
    const btn = document.getElementById(`${prefix}-columns-btn`);
    if (!btn) return;
    if (!btn.dataset.bound) {
        btn.dataset.bound = '1';
        btn.addEventListener('click', () => openColumnsModal(prefix));
    }
    applyColumnVisibility(prefix);
}

function openColumnsModal(prefix) {
    const table = document.getElementById(`${prefix}-columns-btn`)?.closest('.table-card')?.querySelector('table');
    if (!table) return;
    activeColumnsPrefix = prefix;

    document.getElementById('col-modal-title').textContent = `Show / Hide Columns — ${COLUMN_TABLE_TITLES[prefix] || ''}`;

    const state = getSavedColumnState(prefix);
    const body = document.getElementById('col-modal-body');
    body.innerHTML = '';
    table.querySelectorAll('thead th[data-col]').forEach(th => {
        const col = th.getAttribute('data-col');
        const locked = LOCKED_VISIBLE_COLS.has(col);
        const visible = locked ? true : (state[col] !== false);
        const label = document.createElement('label');
        if (locked) label.style.opacity = '0.65';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = visible;
        checkbox.disabled = locked;
        checkbox.setAttribute('data-toggle-col', col);
        const span = document.createElement('span');
        span.textContent = th.textContent.trim() + (locked ? ' (always shown)' : '');
        label.appendChild(checkbox);
        label.appendChild(span);
        body.appendChild(label);

        if (!locked) {
            checkbox.addEventListener('change', () => {
                const s = getSavedColumnState(activeColumnsPrefix);
                s[col] = checkbox.checked;
                saveColumnState(activeColumnsPrefix, s);
                applyColumnVisibility(activeColumnsPrefix);
            });
        }
    });

    const subConfig = SUB_TABLE_COLUMNS[prefix];
    if (subConfig) {
        const divider = document.createElement('div');
        divider.textContent = subConfig.title;
        divider.style.cssText = 'margin:10px 0 2px; padding-top:8px; border-top:1px solid #e1e6ee; font-size:12px; font-weight:600; opacity:0.7; text-transform:uppercase;';
        body.appendChild(divider);

        subConfig.cols.forEach(({key: col, label}) => {
            const visible = state[col] !== false;
            const label_ = document.createElement('label');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = visible;
            checkbox.setAttribute('data-toggle-col', col);
            const span = document.createElement('span');
            span.textContent = label;
            label_.appendChild(checkbox);
            label_.appendChild(span);
            body.appendChild(label_);

            checkbox.addEventListener('change', () => {
                const s = getSavedColumnState(activeColumnsPrefix);
                s[col] = checkbox.checked;
                saveColumnState(activeColumnsPrefix, s);
                applyColumnVisibility(activeColumnsPrefix);
            });
        });
    }

    document.getElementById('columns-modal').classList.add('open');
}

function closeColumnsModal() {
    document.getElementById('columns-modal').classList.remove('open');
    activeColumnsPrefix = null;
}

document.getElementById('col-modal-close')?.addEventListener('click', closeColumnsModal);
document.getElementById('col-modal-done')?.addEventListener('click', closeColumnsModal);
document.getElementById('columns-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'columns-modal') closeColumnsModal();
});
document.getElementById('col-modal-reset')?.addEventListener('click', () => {
    if (!activeColumnsPrefix) return;
    localStorage.removeItem(COLUMN_STORAGE_PREFIX + activeColumnsPrefix);
    applyColumnVisibility(activeColumnsPrefix);
    openColumnsModal(activeColumnsPrefix);
});

function setupPagination(tableBodyId, rowsPerPage = 50) {
    const tableBody = document.getElementById(tableBodyId);
    if (!tableBody) return;

    const tableCard = tableBody.closest('.table-card');
    let paginationDiv = tableCard.querySelector('.pagination');

    if (!paginationDiv) {
        paginationDiv = document.createElement('div');
        paginationDiv.className = 'pagination';
        tableCard.appendChild(paginationDiv);
    }

    const allRows = Array.from(tableBody.querySelectorAll('tr')).filter(row =>
        !row.querySelector('td[colspan]') && row.style.display !== 'none'
    );

    const totalRows = allRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    let currentPage = 1;

    function showPage(page) {
        currentPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        Array.from(tableBody.querySelectorAll('tr')).forEach(row => {
            if (row.querySelector('td[colspan]')) return;
            row.style.display = 'none';
        });

        allRows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            }
        });

        updatePaginationControls();
    }

    function updatePaginationControls() {
        const start = (currentPage - 1) * rowsPerPage + 1;
        const end = Math.min(currentPage * rowsPerPage, totalRows);

        paginationDiv.innerHTML = `
            <div class="pagination-info">Showing ${start}-${end} of ${totalRows} records</div>
            <div class="pagination-controls">
                <button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.paginationHandlers['${tableBodyId}'].first()">First</button>
                <button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.paginationHandlers['${tableBodyId}'].prev()">Previous</button>
                <span style="padding: 0 12px; font-size: 14px;">Page ${currentPage} of ${totalPages}</span>
                <button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.paginationHandlers['${tableBodyId}'].next()">Next</button>
                <button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.paginationHandlers['${tableBodyId}'].last()">Last</button>
            </div>
        `;
    }

    if (!window.paginationHandlers) window.paginationHandlers = {};
    window.paginationHandlers[tableBodyId] = {
        first: () => showPage(1),
        prev: () => showPage(Math.max(1, currentPage - 1)),
        next: () => showPage(Math.min(totalPages, currentPage + 1)),
        last: () => showPage(totalPages)
    };

    if (totalPages > 1) {
        showPage(1);
    } else {
        paginationDiv.style.display = 'none';
    }
}