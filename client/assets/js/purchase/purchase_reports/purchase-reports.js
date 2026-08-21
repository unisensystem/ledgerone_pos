let reportData = {};
let currentReportType = 'item';
let currentDateRange = 'last30';
let currentSearchTerm = '';

// Column headers for each report type
const tableHeaders = {
    item: ["Item ID", "Item Name", "Category", "Batch No", "Expiry Date", "Quantity", "Unit Price", "Total Value", "Supplier", "Actions"],
    invoice: ["Invoice ID", "Invoice Name", "Category", "Items", "Due Date", "Total Value", "Supplier", "Actions"],
    category: ["Category ID", "Category Name", "Type", "Items", "Avg. Price", "Total Value", "Suppliers", "Actions"],
    supplier: ["Supplier ID", "Supplier Name", "Items", "Avg. Price", "Total Value", "Primary Contact", "Actions"]
};

const colKeys = {
    item: ["id","name","category","batchNo","expiryDate","quantity","unitPrice","total","supplier","actions"],
    invoice: ["id","name","category","quantity","dueDate","total","supplier","actions"],
    category: ["id","name","category","quantity","unitPrice","total","supplier","actions"],
    supplier: ["id","name","quantity","unitPrice","total","contact","actions"]
};

// Initialize with item-wise report
document.addEventListener('DOMContentLoaded', function () {
    loadCompanies();
    loadProjects();
    loadSuppliers();
    fetchReportData('item', 'last30');

    // Add event listeners to view details buttons
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function () {
            const reportType = this.getAttribute('data-report');
            currentReportType = reportType;
            fetchReportData(reportType, currentDateRange);
            updateActiveCard(reportType);
        });
    });

    // Apply filters button
    document.getElementById('applyFilters').addEventListener('click', function () {
        const reportType = document.getElementById('reportType').value;
        const dateRange = document.getElementById('dateRange').value;
        const searchTerm = document.getElementById('searchInput').value.trim();
        currentDateRange = dateRange;
        currentSearchTerm = searchTerm;

        if (reportType !== 'all') {
            currentReportType = reportType;
            fetchReportData(reportType, dateRange);
            updateActiveCard(reportType);
        } else {
            fetchReportData(currentReportType, dateRange);
        }
    });

    // Reset filters button
    document.getElementById('resetFilters').addEventListener('click', function () {
        document.getElementById('reportType').value = 'all';
        document.getElementById('dateRange').value = 'last30';
        document.getElementById('searchInput').value = '';
        currentReportType = 'item';
        currentDateRange = 'last30';
        currentSearchTerm = '';
        fetchReportData('item', 'last30');
        updateActiveCard('item');
    });

    // Report type filter change
    document.getElementById('reportType').addEventListener('change', function () {
        const reportType = this.value;
        if (reportType !== 'all') {
            currentReportType = reportType;
            fetchReportData(reportType, currentDateRange);
            updateActiveCard(reportType);
        }
    });

    // Date range filter change
    document.getElementById('dateRange').addEventListener('change', function () {
        const dateRange = this.value;
        currentDateRange = dateRange;
        
        if (dateRange === 'custom') {
            document.getElementById('customDateRange').style.display = 'flex';
        } else {
            document.getElementById('customDateRange').style.display = 'none';
            fetchReportData(currentReportType, dateRange);
        }
    });

    // Custom date range inputs
    document.getElementById('startDate').addEventListener('change', applyCustomDateRange);
    document.getElementById('endDate').addEventListener('change', applyCustomDateRange);
    
    // Search input with debounce
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentSearchTerm = this.value.trim();
            filterTableData();
        }, 300);
    });
    
    // Export dropdown
    document.getElementById('exportBtn').addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('exportMenu').classList.toggle('show');
    });
    
    document.querySelectorAll('.export-option').forEach(option => {
        option.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            handleExport(type);
            document.getElementById('exportMenu').classList.remove('show');
        });
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        document.getElementById('exportMenu').classList.remove('show');
    });
});

// Load Companies
async function loadCompanies() {
    try {
        const response = await fetch('../../../../server/api/purchase/purchase_reports/get-companies.php');
        const result = await response.json();
        
        if (result.success) {
            const companySelect = document.getElementById('company');
            result.companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.company_name;
                companySelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading companies:', error);
    }
}

// Load Projects
async function loadProjects() {
    try {
        const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const result = await response.json();
        
        if (result.success) {
            const projectSelect = document.getElementById('project');
            result.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = `${project.project_code} - ${project.project_name}`;
                projectSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// Load Suppliers
async function loadSuppliers() {
    try {
        const response = await fetch('../../../../server/api/purchase/purchase_reports/get-suppliers.php');
        const result = await response.json();
        
        if (result.success) {
            const supplierSelect = document.getElementById('supplier');
            result.suppliers.forEach(supplier => {
                const option = document.createElement('option');
                option.value = supplier.id;
                option.textContent = `${supplier.supplier_code} - ${supplier.supplier_name}`;
                supplierSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading suppliers:', error);
    }
}

// Fetch report data from API
async function fetchReportData(reportType, dateRange) {
    const company = document.getElementById('company').value;
    const project = document.getElementById('project').value;
    const supplier = document.getElementById('supplier').value;
    try {
        let url = `../../../../server/api/purchase/purchase_reports/purchase-reports.php?type=${reportType}&date_range=${dateRange}`;
        if (company) url += `&company_id=${company}`;
        if (project) url += `&project_id=${project}`;
        if (supplier) url += `&supplier_id=${supplier}`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            reportData[reportType] = result.data;
            updateReportCards(result.summary, result.currency);
            loadReportData(reportType);
        } else {
            console.error('Error fetching report data:', result.message);
            alert('Failed to load report data');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to fetch report data');
    }
}

// Update report cards with real data
function updateReportCards(summary, currency) {
    // Item-wise card
    if (summary.item) {
        document.querySelector('#itemReportCard .stat-item:nth-child(1) .stat-value').textContent = summary.item.items_purchased || 0;
        document.querySelector('#itemReportCard .stat-item:nth-child(2) .stat-value').textContent = parseFloat(summary.item.total_quantity || 0).toFixed(0) + ' units';
        document.querySelector('#itemReportCard .stat-item:nth-child(3) .stat-value').textContent = currency + parseFloat(summary.item.avg_unit_price || 0).toFixed(2);
        document.querySelector('#itemReportCard .stat-item:nth-child(4) .stat-value').textContent = currency + parseFloat(summary.item.total_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Invoice-wise card
    if (summary.invoice) {
        document.querySelector('#invoiceReportCard .stat-item:nth-child(1) .stat-value').textContent = summary.invoice.total_invoices || 0;
        document.querySelector('#invoiceReportCard .stat-item:nth-child(2) .stat-value').textContent = currency + parseFloat(summary.invoice.avg_invoice_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.querySelector('#invoiceReportCard .stat-item:nth-child(3) .stat-value').textContent = summary.invoice.pending_invoices || 0;
        document.querySelector('#invoiceReportCard .stat-item:nth-child(4) .stat-value').textContent = currency + parseFloat(summary.invoice.total_invoice_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Category-wise card
    if (summary.category) {
        document.querySelector('#categoryReportCard .stat-item:nth-child(1) .stat-value').textContent = summary.category.categories || 0;
        document.querySelector('#categoryReportCard .stat-item:nth-child(2) .stat-value').textContent = summary.category.top_category || 'N/A';
        document.querySelector('#categoryReportCard .stat-item:nth-child(3) .stat-value').textContent = parseFloat(summary.category.category_percent || 0).toFixed(0) + '%';
        document.querySelector('#categoryReportCard .stat-item:nth-child(4) .stat-value').textContent = currency + parseFloat(summary.category.category_spend || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Supplier-wise card
    if (summary.supplier) {
        document.querySelector('#supplierReportCard .stat-item:nth-child(1) .stat-value').textContent = summary.supplier.active_suppliers || 0;
        document.querySelector('#supplierReportCard .stat-item:nth-child(2) .stat-value').textContent = parseFloat(summary.item.total_quantity || 0).toFixed(0) + ' units';
        document.querySelector('#supplierReportCard .stat-item:nth-child(3) .stat-value').textContent = currency + parseFloat(summary.item.avg_unit_price || 0).toFixed(2);
        document.querySelector('#supplierReportCard .stat-item:nth-child(4) .stat-value').textContent = currency + parseFloat(summary.supplier.supplier_spend || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

// Load report data into the table
function loadReportData(reportType) {
    const data = reportData[reportType] || [];
    const tableBody = document.getElementById('tableBody');
    const tableTitle = document.getElementById('tableTitle');
    const tableHead = document.querySelector('table thead tr');

    tableBody.innerHTML = '';
    tableTitle.textContent = `${getReportTypeText(reportType)} Details`;
    
    // Update table headers
    tableHead.innerHTML = '';
tableHeaders[reportType].forEach((header, idx) => {
    const th = document.createElement('th');
    th.textContent = header;
    th.setAttribute('data-col', colKeys[reportType][idx]);
    tableHead.appendChild(th);
});

    if (data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No data available</td></tr>';
        return;
    }

    // Apply search filter
    const filteredData = filterData(data, currentSearchTerm, reportType);
    
    if (filteredData.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No matching results found</td></tr>';
        return;
    }

    renderTableRows(filteredData, reportType, tableBody);
setupColumnToggle(reportType);
}

// Filter data based on search term
function filterData(data, searchTerm, reportType) {
    if (!searchTerm) return data;
    
    const term = searchTerm.toLowerCase();
    return data.filter(item => {
        if (reportType === 'item') {
            return item.id.toLowerCase().includes(term) ||
                   item.name.toLowerCase().includes(term) ||
                   item.category.toLowerCase().includes(term) ||
                   item.supplier.toLowerCase().includes(term);
        } else if (reportType === 'invoice') {
            return item.id.toLowerCase().includes(term) ||
                   item.name.toLowerCase().includes(term) ||
                   item.supplier.toLowerCase().includes(term);
        } else if (reportType === 'category') {
            return item.id.toLowerCase().includes(term) ||
                   item.name.toLowerCase().includes(term) ||
                   item.category.toLowerCase().includes(term);
        } else if (reportType === 'supplier') {
            return item.id.toLowerCase().includes(term) ||
                   item.name.toLowerCase().includes(term) ||
                   item.contact.toLowerCase().includes(term);
        }
        return true;
    });
}

// Filter table data without refetching
function filterTableData() {
    loadReportData(currentReportType);
}

// Render table rows
function renderTableRows(data, reportType, tableBody) {

    data.forEach(item => {
        const row = document.createElement('tr');
        let cells = [];
        
       if (reportType === 'item') {
    const isExpiringSoon = item.expiryDate && item.expiryDate !== '-' &&
        (new Date(item.expiryDate) - new Date()) / (1000*60*60*24) <= 30;
    const expiryHtml = isExpiringSoon
        ? `<span style="color:#d64545; font-weight:600;">${item.expiryDate}</span>`
        : (item.expiryDate || '-');

    cells = [item.id, item.name, item.category, item.batchNo || '-', expiryHtml, item.quantity, item.unitPrice, item.total, item.supplier];

    cells.forEach((cellContent, idx) => {
        const cell = document.createElement('td');
        cell.innerHTML = cellContent;
        cell.setAttribute('data-col', colKeys.item[idx]);
        row.appendChild(cell);
    });

    const actionCell = document.createElement('td');
    actionCell.setAttribute('data-col', 'actions');
    actionCell.innerHTML = '<button class="btn-ghost btn-sm expand-btn" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>';
    row.appendChild(actionCell);

    actionCell.querySelector('.expand-btn').addEventListener('click', function() {
        toggleInvoicesBreakdown(row, item.invoices, this);
    });

    tableBody.appendChild(row);
    return;
}
         else if (reportType === 'invoice') {
            cells = [item.id, item.name, item.category, item.quantity, item.dueDate, item.total, item.supplier];
            
            cells.forEach(cellContent => {
                const cell = document.createElement('td');
                cell.innerHTML = cellContent;
                row.appendChild(cell);
            });
            
            // Add expand button
            const actionCell = document.createElement('td');
            actionCell.innerHTML = '<button class="btn-ghost btn-sm expand-btn" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>';
            row.appendChild(actionCell);
            
            // Add click event to expand button
            actionCell.querySelector('.expand-btn').addEventListener('click', function() {
                toggleItemsBreakdown(row, item.items, this);
            });
            
            tableBody.appendChild(row);
            return;
        } else if (reportType === 'category') {
            cells = [item.id, item.name, item.category, item.quantity, item.unitPrice, item.total, item.supplier];
            
            cells.forEach(cellContent => {
                const cell = document.createElement('td');
                cell.innerHTML = cellContent;
                row.appendChild(cell);
            });
            
            const actionCell = document.createElement('td');
            actionCell.innerHTML = '<button class="btn-ghost btn-sm expand-btn" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>';
            row.appendChild(actionCell);
            
            actionCell.querySelector('.expand-btn').addEventListener('click', function() {
                toggleCategoryBreakdown(row, item.subcategories, this);
            });
            
            tableBody.appendChild(row);
            return;
        } else if (reportType === 'supplier') {
            cells = [item.id, item.name, item.quantity, item.unitPrice, item.total, item.contact];
            
            cells.forEach(cellContent => {
                const cell = document.createElement('td');
                cell.innerHTML = cellContent;
                row.appendChild(cell);
            });
            
            const actionCell = document.createElement('td');
            actionCell.innerHTML = '<button class="btn-ghost btn-sm expand-btn" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>';
            row.appendChild(actionCell);
            
            actionCell.querySelector('.expand-btn').addEventListener('click', function() {
                toggleSupplierInvoicesBreakdown(row, item.invoices, this);
            });
            
            tableBody.appendChild(row);
            return;
        }

        cells.forEach(cellContent => {
            const cell = document.createElement('td');
            cell.innerHTML = cellContent;
            row.appendChild(cell);
        });

        tableBody.appendChild(row);
    });
}

// Toggle invoices breakdown for item
function toggleInvoicesBreakdown(row, invoices, button) {
    const existingBreakdown = row.nextElementSibling;
    
    if (existingBreakdown && existingBreakdown.classList.contains('breakdown-row')) {
        existingBreakdown.remove();
        button.innerHTML = '<i class="fas fa-chevron-down"></i>';
        return;
    }
    
    const breakdownRow = document.createElement('tr');
    breakdownRow.classList.add('breakdown-row');
    breakdownRow.innerHTML = `
        <td colspan="8" style="padding: 0; background-color: #f7f9fc;">
            <div style="padding: 16px; margin: 8px;">
                <h4 style="margin-bottom: 12px; color: var(--heading); font-size: 14px;">Purchase Invoices</h4>
                <table style="width: 100%; background: white; border-radius: 4px;">
                    <thead>
                        <tr style="background-color: #eff2f7;">
                            <th style="padding: 8px;">Invoice No</th>
                            <th style="padding: 8px;">Date</th>
                            <th style="padding: 8px;">Quantity</th>
                            <th style="padding: 8px;">Unit Price</th>
                            <th style="padding: 8px;">Total</th>
                            <th style="padding: 8px;">Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${invoices.map(inv => `
                            <tr>
                                <td style="padding: 8px;">${inv.billNo}</td>
                                <td style="padding: 8px;">${inv.date}</td>
                                <td style="padding: 8px;">${inv.quantity}</td>
                                <td style="padding: 8px;">${inv.price}</td>
                                <td style="padding: 8px;">${inv.total}</td>
                                <td style="padding: 8px;">${inv.supplier}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </td>
    `;
    
    row.parentNode.insertBefore(breakdownRow, row.nextSibling);
    button.innerHTML = '<i class="fas fa-chevron-up"></i>';
}

// Toggle items breakdown for invoice
function toggleItemsBreakdown(row, items, button) {
    const existingBreakdown = row.nextElementSibling;
    
    if (existingBreakdown && existingBreakdown.classList.contains('breakdown-row')) {
        existingBreakdown.remove();
        button.innerHTML = '<i class="fas fa-chevron-down"></i>';
        return;
    }
    
    const breakdownRow = document.createElement('tr');
    breakdownRow.classList.add('breakdown-row');
    breakdownRow.innerHTML = `
        <td colspan="8" style="padding: 0; background-color: #f7f9fc;">
            <div style="padding: 16px; margin: 8px;">
                <h4 style="margin-bottom: 12px; color: var(--heading); font-size: 14px;">Invoice Items Breakdown</h4>
                <table style="width: 100%; background: white; border-radius: 4px;">
                    <thead>
                        <tr style="background-color: #eff2f7;">
                            <th style="padding: 8px;">Product</th>
                            <th style="padding: 8px;">Quantity</th>
                            <th style="padding: 8px;">Unit Price</th>
                            <th style="padding: 8px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${items.map(item => `
                            <tr>
                                <td style="padding: 8px;">${item.product}</td>
                                <td style="padding: 8px;">${item.quantity}</td>
                                <td style="padding: 8px;">${item.price}</td>
                                <td style="padding: 8px;">${item.total}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </td>
    `;
    
    row.parentNode.insertBefore(breakdownRow, row.nextSibling);
    button.innerHTML = '<i class="fas fa-chevron-up"></i>';
}

// Toggle supplier invoices breakdown with nested items
function toggleSupplierInvoicesBreakdown(row, invoices, button) {
    const existingBreakdown = row.nextElementSibling;
    
    if (existingBreakdown && existingBreakdown.classList.contains('breakdown-row')) {
        existingBreakdown.remove();
        button.innerHTML = '<i class="fas fa-chevron-down"></i>';
        return;
    }
    
    const breakdownRow = document.createElement('tr');
    breakdownRow.classList.add('breakdown-row');
    breakdownRow.innerHTML = `
        <td colspan="8" style="padding: 0; background-color: #f7f9fc;">
            <div style="padding: 16px; margin: 8px;">
                <h4 style="margin-bottom: 12px; color: var(--heading); font-size: 14px;">Supplier Invoices</h4>
                ${invoices.map((inv, idx) => `
                    <div style="margin-bottom: 16px; background: white; border-radius: 4px; padding: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #e1e6ee;">
                            <strong>Invoice: ${inv.billNo}</strong>
                            <span>Date: ${inv.date}</span>
                            <strong>Total: ${inv.total}</strong>
                            <button class="btn-ghost btn-sm expand-items-btn" data-idx="${idx}" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>
                        </div>
                        <div class="items-container" data-idx="${idx}" style="display: none;">
                            <table style="width: 100%;">
                                <thead>
                                    <tr style="background-color: #eff2f7;">
                                        <th style="padding: 8px; text-align: left;">Product</th>
                                        <th style="padding: 8px; text-align: left;">Quantity</th>
                                        <th style="padding: 8px; text-align: left;">Unit Price</th>
                                        <th style="padding: 8px; text-align: left;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${inv.items.map(item => `
                                        <tr>
                                            <td style="padding: 8px;">${item.product}</td>
                                            <td style="padding: 8px;">${item.quantity}</td>
                                            <td style="padding: 8px;">${item.price}</td>
                                            <td style="padding: 8px;">${item.total}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `).join('')}
            </div>
        </td>
    `;
    
    row.parentNode.insertBefore(breakdownRow, row.nextSibling);
    button.innerHTML = '<i class="fas fa-chevron-up"></i>';
    
    // Add event listeners for sub-expand buttons
    breakdownRow.querySelectorAll('.expand-items-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const idx = this.getAttribute('data-idx');
            const itemsContainer = breakdownRow.querySelector(`.items-container[data-idx="${idx}"]`);
            
            if (itemsContainer.style.display === 'none') {
                itemsContainer.style.display = 'block';
                this.innerHTML = '<i class="fas fa-chevron-up"></i>';
            } else {
                itemsContainer.style.display = 'none';
                this.innerHTML = '<i class="fas fa-chevron-down"></i>';
            }
        });
    });
}

// Toggle category breakdown with subcategories, invoices, and items
function toggleCategoryBreakdown(row, subcategories, button) {
    const existingBreakdown = row.nextElementSibling;
    
    if (existingBreakdown && existingBreakdown.classList.contains('breakdown-row')) {
        existingBreakdown.remove();
        button.innerHTML = '<i class="fas fa-chevron-down"></i>';
        return;
    }
    
    const breakdownRow = document.createElement('tr');
    breakdownRow.classList.add('breakdown-row');
    breakdownRow.innerHTML = `
        <td colspan="8" style="padding: 0; background-color: #f7f9fc;">
            <div style="padding: 16px; margin: 8px;">
                <h4 style="margin-bottom: 12px; color: var(--heading); font-size: 14px;">Subcategories</h4>
                ${subcategories.map((subcat, scIdx) => `
                    <div style="margin-bottom: 16px; background: white; border-radius: 4px; padding: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #e1e6ee;">
                            <strong>Subcategory: ${subcat.name}</strong>
                            <span>Quantity: ${subcat.quantity}</span>
                            <strong>Total: ${subcat.total}</strong>
                            <button class="btn-ghost btn-sm expand-subcat-btn" data-sc-idx="${scIdx}" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>
                        </div>
                        <div class="subcat-invoices-container" data-sc-idx="${scIdx}" style="display: none;">
                            ${subcat.invoices.map((inv, invIdx) => `
                                <div style="margin-bottom: 12px; background: #f7f9fc; border-radius: 4px; padding: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                        <strong>Invoice: ${inv.billNo}</strong>
                                        <span>Date: ${inv.date}</span>
                                        <strong>Total: ${inv.total}</strong>
                                        <button class="btn-ghost btn-sm expand-inv-items-btn" data-sc-idx="${scIdx}" data-inv-idx="${invIdx}" style="padding: 4px 8px;"><i class="fas fa-chevron-down"></i></button>
                                    </div>
                                    <div class="inv-items-container" data-sc-idx="${scIdx}" data-inv-idx="${invIdx}" style="display: none;">
                                        <table style="width: 100%; background: white;">
                                            <thead>
                                                <tr style="background-color: #eff2f7;">
                                                    <th style="padding: 6px; text-align: left;">Product</th>
                                                    <th style="padding: 6px; text-align: left;">Quantity</th>
                                                    <th style="padding: 6px; text-align: left;">Unit Price</th>
                                                    <th style="padding: 6px; text-align: left;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${inv.items.map(item => `
                                                    <tr>
                                                        <td style="padding: 6px;">${item.product}</td>
                                                        <td style="padding: 6px;">${item.quantity}</td>
                                                        <td style="padding: 6px;">${item.price}</td>
                                                        <td style="padding: 6px;">${item.total}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `).join('')}
            </div>
        </td>
    `;
    
    row.parentNode.insertBefore(breakdownRow, row.nextSibling);
    button.innerHTML = '<i class="fas fa-chevron-up"></i>';
    
    // Add event listeners for subcategory expand buttons
    breakdownRow.querySelectorAll('.expand-subcat-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const scIdx = this.getAttribute('data-sc-idx');
            const container = breakdownRow.querySelector(`.subcat-invoices-container[data-sc-idx="${scIdx}"]`);
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                this.innerHTML = '<i class="fas fa-chevron-up"></i>';
            } else {
                container.style.display = 'none';
                this.innerHTML = '<i class="fas fa-chevron-down"></i>';
            }
        });
    });
    
    // Add event listeners for invoice items expand buttons
    breakdownRow.querySelectorAll('.expand-inv-items-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const scIdx = this.getAttribute('data-sc-idx');
            const invIdx = this.getAttribute('data-inv-idx');
            const container = breakdownRow.querySelector(`.inv-items-container[data-sc-idx="${scIdx}"][data-inv-idx="${invIdx}"]`);
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                this.innerHTML = '<i class="fas fa-chevron-up"></i>';
            } else {
                container.style.display = 'none';
                this.innerHTML = '<i class="fas fa-chevron-down"></i>';
            }
        });
    });
}

// Update active report card
function updateActiveCard(reportType) {
    // Remove active class from all cards
    document.querySelectorAll('.report-card').forEach(card => {
        card.classList.remove('active');
    });

    // Add active class to selected card
    const activeCard = document.getElementById(`${reportType}ReportCard`);
    if (activeCard) {
        activeCard.classList.add('active');
        activeCard.style.borderLeft = '4px solid var(--primary)';
    }

    // Reset other cards
    document.querySelectorAll('.report-card:not(.active)').forEach(card => {
        card.style.borderLeft = 'none';
    });
}

// Helper functions
function getReportTypeText(type) {
    const types = {
        'item': 'Item-wise Report',
        'invoice': 'Invoice-wise Report',
        'category': 'Category-wise Report',
        'supplier': 'Supplier-wise Report'
    };
    return types[type] || 'Report';
}

function applyCustomDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (startDate && endDate) {
        currentDateRange = `${startDate}_${endDate}`;
        fetchReportData(currentReportType, currentDateRange);
    }
}
function setupColumnToggle(reportType) {
    const btn = document.getElementById('columns-btn');
    const menu = document.getElementById('columns-menu');
    if (!btn || !menu) return;

    const table = document.querySelector('#detailedTable table');
    const headers = table.querySelectorAll('thead th[data-col]');

    let html = '';
    headers.forEach(th => {
        const col = th.getAttribute('data-col');
        html += `
            <label style="display:flex; align-items:center; gap:8px; padding:4px 2px; font-size:13px; cursor:pointer;">
                <input type="checkbox" checked data-toggle-col="${col}"> ${th.textContent}
            </label>`;
    });
    menu.innerHTML = html;

    menu.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => {
            const col = cb.getAttribute('data-toggle-col');
            const cells = table.querySelectorAll(`[data-col="${col}"]`);
            cells.forEach(c => c.style.display = cb.checked ? '' : 'none');
        });
    });

    btn.onclick = (e) => {
        e.stopPropagation();
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    };
}

document.addEventListener('click', (e) => {
    const menu = document.getElementById('columns-menu');
    const btn = document.getElementById('columns-btn');
    if (menu && btn && !menu.contains(e.target) && e.target !== btn) {
        menu.style.display = 'none';
    }
});

function getDateRangeText(range) {
    const ranges = {
        'last7': 'Last 7 Days',
        'last30': 'Last 30 Days',
        'last90': 'Last 90 Days',
        'ytd': 'Year to Date',
        'custom': 'Custom Range'
    };
    return ranges[range] || 'Last 30 Days';
}

function handleExport(type) {
    const reportName = getReportTypeText(currentReportType);
    const company = document.getElementById('company').value;
    
    if (type === 'print') {
        let printUrl = `print.php?type=${currentReportType}&date_range=${currentDateRange}`;
        if (company) printUrl += `&company_id=${company}`;
        window.open(printUrl, '_blank');
    } else if (type === 'excel') {
        alert(`Exporting ${reportName} to Excel format`);
    } else if (type === 'json') {
        alert(`Exporting ${reportName} to JSON format`);
    }
}
