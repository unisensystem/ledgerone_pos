const API_BASE = '../../../../server/api/inventory/stock_position/stock-position.php';

// Pagination variables
let currentPage = 1;
const itemsPerPage = 20;  // Increased from 10 to show more rows per page (fixes FOC pagination issue)
let totalItems = 0;
let allPositions = [];
let currentCurrency = '$';
// Customer-wise Ledger pagination variables
let customerCurrentPage = 1;
let customerTotalItems = 0;
let allCustomerEntries = [];
let customerCurrency = '$';

// Item-wise Ledger pagination variables
let itemCurrentPage = 1;
let itemTotalItems = 0;
let allLedgerEntries = [];
let itemCurrency = '$';

// Branch-wise Ledger pagination variables
let branchCurrentPage = 1;
let branchTotalItems = 0;
let allBranchEntries = [];
let branchCurrency = '$';

// Detailed Ledger pagination variables
let detailedCurrentPage = 1;
let detailedTotalItems = 0;
let allDetailedEntries = [];
let detailedCurrency = '$';

// Column visibility state
let columnVisibility = {
    branch: true,
    inventoryType: true,
    openingBalance: true,
    qtyIn: true,
    qtyOut: true,
    currentStock: true,
    unitCost: true,
    stockValue: true,
    status: true
};

// Load column visibility from localStorage
function loadColumnVisibility() {
    const saved = localStorage.getItem('stockPositionColumnVisibility');
    if (saved) {
        columnVisibility = JSON.parse(saved);
    }
    applyColumnVisibility();
}

// Save column visibility to localStorage
function saveColumnVisibility() {
    localStorage.setItem('stockPositionColumnVisibility', JSON.stringify(columnVisibility));
}

// Apply column visibility
function applyColumnVisibility() {
    const columns = {
        'branch': document.querySelectorAll('.col-branch'),
        'inventoryType': document.querySelectorAll('.col-inventory-type'),
        'openingBalance': document.querySelectorAll('.col-opening-balance'),
        'qtyIn': document.querySelectorAll('.col-qty-in'),
        'qtyOut': document.querySelectorAll('.col-qty-out'),
        'currentStock': document.querySelectorAll('.col-current-stock'),
        'unitCost': document.querySelectorAll('.col-unit-cost'),
        'stockValue': document.querySelectorAll('.col-stock-value'),
        'status': document.querySelectorAll('.col-status')
    };
    
    Object.keys(columns).forEach(key => {
        columns[key].forEach(col => {
            col.style.display = columnVisibility[key] ? '' : 'none';
        });
    });
    
    // Update checkboxes
    const checkboxes = {
        'branch': document.getElementById('toggle-branch'),
        'inventoryType': document.getElementById('toggle-inventory-type'),
        'openingBalance': document.getElementById('toggle-opening-balance'),
        'qtyIn': document.getElementById('toggle-qty-in'),
        'qtyOut': document.getElementById('toggle-qty-out'),
        'currentStock': document.getElementById('toggle-current-stock'),
        'unitCost': document.getElementById('toggle-unit-cost'),
        'stockValue': document.getElementById('toggle-stock-value'),
        'status': document.getElementById('toggle-status')
    };
    
    Object.keys(checkboxes).forEach(key => {
        if (checkboxes[key]) checkboxes[key].checked = columnVisibility[key];
    });
}

// Toggle children for a specific product
function toggleChildren(productId) {
    const childRows = document.querySelectorAll(`tr.child-row[data-parent-id="${productId}"]`);
    const toggleIcon = document.querySelector(`tr[data-product-id="${productId}"] i`);
    
    if (childRows.length > 0) {
        const isHidden = childRows[0].style.display === 'none';
        childRows.forEach(row => row.style.display = isHidden ? '' : 'none');
        if (toggleIcon) {
            toggleIcon.className = isHidden ? 'fas fa-chevron-down' : 'fas fa-chevron-right';
        }
    }
}

// Print function
function printList() {
    const activeTab = getActiveTabId();

    if (activeTab === 'customer-ledger') {
        openCustomerLedgerPreview();
        return;
    }

    const branch = document.getElementById('branch').value;
    const product = document.getElementById('product').value;
    const inventoryType = document.getElementById('inventory-type').value;
    const stockStatus = document.getElementById('stock-status').value;
    const status = document.getElementById('status').value;
    const stockLevelFilter = document.getElementById('stock-level-filter').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;
    const valuationMethod = document.getElementById('valuation-method').value;
    
    let url = 'print.php?';
    if (branch) url += `branch_id=${branch}&`;
    if (product) url += `product_id=${product}&`;
    if (inventoryType) url += `inventory_type_id=${inventoryType}&`;
    if (stockStatus) url += `stock_status=${stockStatus}&`;
    if (status) url += `status=${status}&`;
    if (stockLevelFilter) url += `stock_level=${stockLevelFilter}&`;
    if (fromDate) url += `from_date=${fromDate}&`;
    if (toDate) url += `to_date=${toDate}&`;
    if (valuationMethod) url += `valuation_method=${valuationMethod}&`;
    
    window.open(url, '_blank');
}

// Returns the data-tab id of whichever tab is currently active
function getActiveTabId() {
    const activeTab = document.querySelector('.tab.active');
    return activeTab ? activeTab.getAttribute('data-tab') : 'position';
}

// Shared running-balance calculation used by Detailed Ledger & Customer-wise Ledger exports
// (mirrors the logic already used when rendering those two tables)
function computeRunningBalances(entries) {
    const balances = {};
    return entries.map(entry => {
        const key = `${entry.product_name}-${entry.branch_name}`;
        if (!balances[key]) balances[key] = 0;
        balances[key] += parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0);
        return {
            ...entry,
            balance: balances[key],
            value: balances[key] * parseFloat(entry.unit_cost || 0)
        };
    });
}

// Builds a CSV file from an array of plain objects and triggers a download.
// No external library needed — Excel opens CSV files natively.
function downloadAsExcelCSV(rows, filename) {
    if (!rows || rows.length === 0) {
        alert('No data available to export.');
        return;
    }

    const headers = Object.keys(rows[0]);
    const escapeCell = (val) => {
        let str = (val === undefined || val === null) ? '' : String(val);
        if (/[",\n]/.test(str)) {
            str = '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
    };

    const lines = [headers.map(escapeCell).join(',')];
    rows.forEach(row => {
        lines.push(headers.map(h => escapeCell(row[h])).join(','));
    });

    // BOM prefix so Excel correctly detects UTF-8 (needed for currency symbols etc.)
    const csvContent = '\uFEFF' + lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportToExcel() {
    const activeTab = getActiveTabId();
    const today = new Date().toISOString().slice(0, 10);
    let rows = [];
    let filename = `export_${today}.csv`;

    // Customer-wise Ledger: seedha CSV download nahi — pehle print preview khulega,
    // wahan se user Print ya Export to Excel kar sakta hai (jaisa Stock Position tab me hota hai)
    if (activeTab === 'customer-ledger') {
        openCustomerLedgerPreview();
        return;
    }

    switch (activeTab) {
        case 'position': {
            filename = `stock_position_${today}.csv`;
            rows = allPositions.map(pos => {
                const unit = pos.unit_symbol || 'L';
                const row = { 'Product': pos.product_name };
                if (columnVisibility.branch) row['Branch'] = pos.branch_display;
                if (columnVisibility.inventoryType) row['Inventory Type'] = pos.inventory_type || '-';
                if (columnVisibility.openingBalance) row['Opening Balance'] = `${parseFloat(pos.opening_balance || 0).toFixed(2)} ${unit}`;
                if (columnVisibility.qtyIn) row['Total Qty In'] = `${parseFloat(pos.total_qty_in || 0).toFixed(2)} ${unit}`;
                if (columnVisibility.qtyOut) row['Total Qty Out'] = `${parseFloat(pos.total_qty_out || 0).toFixed(2)} ${unit}`;
                if (columnVisibility.currentStock) row['Current Stock'] = `${parseFloat(pos.current_stock).toFixed(2)} ${unit}`;
                if (columnVisibility.unitCost) row['Unit Cost'] = parseFloat(pos.unit_cost).toFixed(2);
                if (columnVisibility.stockValue) row['Stock Value'] = parseFloat(pos.stock_value).toFixed(2);
                if (columnVisibility.status) row['Status'] = pos.status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
                return row;
            });
            break;
        }
        case 'item-ledger': {
            filename = `item_ledger_${today}.csv`;
            rows = allLedgerEntries.map(entry => ({
                'Date': formatLedgerDate(entry.transaction_date),
                'Transaction': entry.transaction_type,
                'Reference': entry.reference_no || entry.reference_id || '-',
                'Inventory Item': `${entry.product_code} - ${entry.product_name}`,
                'Customer': entry.customer_name || '-',
                'Supplier': entry.supplier_name || '-',
                'Qty Owned': (parseFloat(entry.qty_change || 0) > 0 ? '+' : '') + parseFloat(entry.qty_change || 0).toFixed(2),
                'Balance': `${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}`
            }));
            break;
        }
        case 'branch-ledger': {
            filename = `branch_ledger_${today}.csv`;
            rows = allBranchEntries.map(entry => ({
                'Date': entry.transaction_date,
                'Product': entry.product_name,
                'Transaction': entry.transaction_type,
                'Qty In': entry.qty_in > 0 ? `${parseFloat(entry.qty_in).toFixed(2)} ${entry.unit_symbol || 'L'}` : '-',
                'Qty Out': entry.qty_out > 0 ? `${parseFloat(entry.qty_out).toFixed(2)} ${entry.unit_symbol || 'L'}` : '-',
                'Unit Cost': parseFloat(entry.unit_cost).toFixed(2),
                'Value': ((parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0)) * parseFloat(entry.unit_cost)).toFixed(2)
            }));
            break;
        }
        case 'detailed-ledger': {
            filename = `detailed_ledger_${today}.csv`;
            rows = computeRunningBalances(allDetailedEntries).map(entry => ({
                'Date': entry.transaction_date,
                'Product': entry.product_name,
                'Branch': entry.branch_name,
                'Transaction': entry.transaction_type,
                'Qty In': entry.qty_in > 0 ? `${parseFloat(entry.qty_in).toFixed(2)} ${entry.unit_symbol || 'L'}` : '-',
                'Qty Out': entry.qty_out > 0 ? `${parseFloat(entry.qty_out).toFixed(2)} ${entry.unit_symbol || 'L'}` : '-',
                'Balance': `${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}`,
                'Unit Cost': parseFloat(entry.unit_cost).toFixed(2),
                'Value': parseFloat(entry.value).toFixed(2)
            }));
            break;
        }
        default:
            alert('No data available to export for this tab.');
            return;
    }

    downloadAsExcelCSV(rows, filename);
}

// Customer-wise Ledger ke liye print preview page kholta hai (CSV direct download ki jagah)
function openCustomerLedgerPreview() {
    const customerName = document.getElementById('customer-ledger-input').value;

    if (!customerName || customerName.trim() === '') {
        alert('Please select a customer and apply filters first.');
        return;
    }

    const options = document.querySelectorAll('#customer-ledger-list option');
    let customerId = null;
    for (let option of options) {
        if (option.value === customerName) {
            customerId = option.getAttribute('data-id');
            break;
        }
    }

    if (!customerId) {
        alert('Invalid customer selected. Please pick a customer from the list.');
        return;
    }

    const fromDate = document.getElementById('from-date-customer').value;
    const toDate = document.getElementById('to-date-customer').value;

    let url = `print_customer_ledger.php?customer_id=${customerId}`;
    if (fromDate) url += `&from_date=${fromDate}`;
    if (toDate) url += `&to_date=${toDate}`;

    window.open(url, '_blank');
}
function exportToJSON() {
    alert('JSON export functionality to be implemented');
}

// Tab Functionality
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const tabId = tab.getAttribute('data-tab');
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tabId).classList.add('active');
        
        if (tabId === 'position') loadStockPosition();
        if (tabId === 'detailed-ledger') loadDetailedLedger();
    });
});

// Load Stock Position
async function loadStockPosition() {
    try {
        const branchInput = document.getElementById('branch').value;
        const product = document.getElementById('product').value;
        const inventoryType = document.getElementById('inventory-type').value;
        const distribution = document.getElementById('distribution').value;
        const stockStatus = document.getElementById('stock-status').value;
        const status = document.getElementById('status').value;
        const stockLevelFilter = document.getElementById('stock-level-filter').value;
        const fromDate = document.getElementById('from-date').value;
        const toDate = document.getElementById('to-date').value;
        const valuationMethod = document.getElementById('valuation-method').value;
        const showBaseUnits = document.getElementById('show-base-units').checked;
        
        // Show/hide trade price note
        const tradePriceNote = document.getElementById('trade-price-note');
        if (valuationMethod === 'TRADE_PRICE') {
            tradePriceNote.style.display = 'block';
        } else {
            tradePriceNote.style.display = 'none';
        }
        
        // Find branch ID from datalist
        let branchId = null;
        if (branchInput) {
            const options = document.querySelectorAll('#branch-list option');
            for (let option of options) {
                if (option.value === branchInput) {
                    branchId = option.getAttribute('data-id');
                    break;
                }
            }
        }
        
        // Find product ID from datalist
        let productId = null;
        if (product) {
            const options = document.querySelectorAll('#product-list option');
            for (let option of options) {
                if (option.value === product) {
                    productId = option.getAttribute('data-id');
                    break;
                }
            }
        }
        
        console.log('=== Filter Debug ===');
        console.log('Show Base Units Checkbox:', showBaseUnits);
        console.log('Branch ID:', branchId);
        console.log('Product ID:', productId);
        
        let url = `${API_BASE}?action=position`;
        if (branchId) url += `&branch_id=${branchId}`;
        if (productId) url += `&product_id=${productId}`;
        if (inventoryType) url += `&inventory_type_id=${inventoryType}`;
        if (distribution) url += `&vendor_id=${distribution}`;
        if (stockStatus) url += `&stock_status=${stockStatus}`;
        if (status) url += `&status=${status}`;
        if (fromDate) url += `&from_date=${fromDate}`;
        if (toDate) url += `&to_date=${toDate}`;
        if (valuationMethod) url += `&valuation_method=${valuationMethod}`;
        if (showBaseUnits) url += `&show_base_units=true`;
        
        console.log('API URL:', url);
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            console.log('Debug info:', data.debug);
            console.log('Raw data count:', data.data.length);
            allPositions = Array.isArray(data.data) ? data.data : [];
            
            // Apply stock level filter
            if (stockLevelFilter === 'zero-or-less') {
                console.log('Applying filter: zero-or-less');
                allPositions = allPositions.filter(pos => {
                    const stock = parseFloat(pos.current_stock);
                    console.log(`Product: ${pos.product_name}, Stock: ${stock}, Include: ${stock <= 0}`);
                    return stock <= 0;
                });
            } else if (stockLevelFilter === 'greater-than-zero') {
                console.log('Applying filter: greater-than-zero');
                allPositions = allPositions.filter(pos => {
                    const stock = parseFloat(pos.current_stock);
                    console.log(`Product: ${pos.product_name}, Stock: ${stock}, Include: ${stock > 0}`);
                    return stock > 0;
                });
            }
            
            console.log('Filtered data count:', allPositions.length);
            
            // Add product_id to each position for toggle functionality
            allPositions = allPositions.map((pos, idx) => ({
                ...pos,
                product_id: pos.product_id || `temp_${idx}`
            }));
            
            totalItems = allPositions.length;
            currentPage = 1;
            currentCurrency = data.currency || '$';
            updatePositionTable(currentCurrency);
            updatePaginationControls();
        }
    } catch (error) {
        console.error('Error loading stock position:', error);
    }
}

function updatePositionTable(currency = '$') {
    const tbody = document.querySelector('#position .table tbody');
    
    // Get current page data
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = allPositions.slice(startIndex, endIndex);
    
    // Calculate totals for all data (only parent products or standalone products - exclude children)
    const totalValue = allPositions.reduce((sum, pos) => {
        if (!pos.parent_product_id) {  // Only count if it's NOT a child (parent or standalone)
            return sum + parseFloat(pos.stock_value);
        }
        return sum;
    }, 0);
    const totalEntries = allPositions.filter(pos => !pos.parent_product_id).length;
    
    // Calculate unit-wise totals for all data (only parent products or standalone products)
    const unitTotals = allPositions.reduce((totals, pos) => {
        if (!pos.parent_product_id) {  // Only count if it's NOT a child
            const unit = pos.unit_symbol || 'L';
            totals[unit] = (totals[unit] || 0) + parseFloat(pos.current_stock);
        }
        return totals;
    }, {});
    
    const unitTotalsText = Object.entries(unitTotals)
        .map(([unit, total]) => `${total.toFixed(2)} ${unit}`)
        .join(', ');
    
    tbody.innerHTML = pageData.map((pos, idx) => {
        const isChild = pos.parent_product_id;
        const hasChildren = !isChild && pageData.some(p => p.parent_product_id === pos.product_id);
        const toggleBtn = hasChildren ? `<i class="fas fa-chevron-down" style="cursor: pointer; margin-right: 8px;" onclick="toggleChildren(${pos.product_id})"></i>` : '';
        const productDisplay = isChild ? `<span style="margin-left: 28px; color: var(--subtext);">↳ ${pos.product_name}</span>` : `${toggleBtn}${pos.product_name}`;
        
        return `
        <tr class="${isChild ? 'child-row' : 'parent-row'}" data-product-id="${pos.product_id}" data-parent-id="${pos.parent_product_id || ''}" style="${isChild ? 'background-color: var(--table-row-alt);' : ''}">
            <td>${productDisplay}</td>
            <td class="col-branch">${pos.branch_display}</td>
            <td class="col-inventory-type">${pos.inventory_type || '-'}</td>
            <td class="col-opening-balance">${parseFloat(pos.opening_balance || 0).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
            <td class="col-qty-in">${parseFloat(pos.total_qty_in || 0).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
            <td class="col-qty-out">${parseFloat(pos.total_qty_out || 0).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
            <td class="col-current-stock">${parseFloat(pos.current_stock).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
            <td class="col-unit-cost">${currency}${parseFloat(pos.unit_cost).toFixed(2)}</td>
            <td class="col-stock-value">${currency}${parseFloat(pos.stock_value).toFixed(2)}</td>
            <td class="col-status"><span class="status-badge status-${pos.status}">${pos.status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></td>
        </tr>
    `;
    }).join('') + `
        <tr style="font-weight: bold; background-color: var(--table-header);">
            <td colspan="3">Total (${totalEntries} entries)</td>
            <td class="col-opening-balance">-</td>
            <td class="col-qty-in">-</td>
            <td class="col-qty-out">-</td>
            <td class="col-current-stock">${unitTotalsText}</td>
            <td class="col-unit-cost">-</td>
            <td class="col-stock-value">${currency}${totalValue.toFixed(2)}</td>
            <td class="col-status"></td>
        </tr>
    `;
    
    applyColumnVisibility();
}

function updatePaginationControls() {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const startItem = totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    
    document.getElementById('position-pagination-info').textContent = `Showing ${startItem} - ${endItem} of ${totalItems} entries`;
    document.getElementById('position-page-info').textContent = `Page ${currentPage} of ${totalPages}`;
    
    document.getElementById('position-prev-btn').disabled = currentPage === 1;
    document.getElementById('position-next-btn').disabled = currentPage === totalPages || totalPages === 0;
}

function updateLedgerTable(ledger, currency = '$') {
    allLedgerEntries = ledger;
    itemTotalItems = allLedgerEntries.length;
    itemCurrentPage = 1;
    itemCurrency = currency;
    
    renderLedgerTable(itemCurrency);
    updateItemPaginationControls();
}

// Tenant-entered data (customer/supplier/product names) is rendered via innerHTML — escape it first.
function escLedger(val) {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// DB date (YYYY-MM-DD) -> DD/MM/YYYY
function formatLedgerDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
}

function renderLedgerTable(currency = '$') {
    const tbody = document.querySelector('#item-ledger .table tbody');

    // Get current page data
    const startIndex = (itemCurrentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = allLedgerEntries.slice(startIndex, endIndex);

    const finalBalance = allLedgerEntries.length > 0 ? parseFloat(allLedgerEntries[allLedgerEntries.length - 1].balance) : 0;

    tbody.innerHTML = pageData.map(entry => {
        const dateLabel = formatLedgerDate(entry.transaction_date);
        const transactionLabel = entry.reference_no
            ? `${entry.transaction_type} — ${entry.reference_no} — ${dateLabel}`
            : entry.transaction_type;
        const qtyChange = parseFloat(entry.qty_change || 0);
        const qtyLabel = (qtyChange > 0 ? '+' : '') + qtyChange.toFixed(2);

        return `
        <tr>
            <td>${dateLabel}</td>
            <td><span class="transaction-badge transaction-${escLedger(entry.transaction_type.toLowerCase().replace(/\s+/g, '-'))}">${escLedger(transactionLabel)}</span></td>
            <td>${escLedger(entry.reference_no) || (entry.reference_id ?? '-')}</td>
            <td>${escLedger(entry.product_code)} - ${escLedger(entry.product_name)}</td>
            <td>${escLedger(entry.customer_name) || '-'}</td>
            <td>${escLedger(entry.supplier_name) || '-'}</td>
            <td>${qtyLabel}</td>
            <td>${parseFloat(entry.balance).toFixed(2)} ${escLedger(entry.unit_symbol) || 'L'}</td>
        </tr>
        `;
    }).join('') + `
        <tr style="font-weight: bold; background-color: var(--table-header);">
            <td colspan="6">Total (${allLedgerEntries.length} transactions)</td>
            <td colspan="1"></td>
            <td>${finalBalance.toFixed(2)} ${escLedger(allLedgerEntries[0]?.unit_symbol) || 'L'}</td>
        </tr>
    `;
}

function updateItemPaginationControls() {
    const totalPages = Math.ceil(itemTotalItems / itemsPerPage);
    const startItem = itemTotalItems === 0 ? 0 : (itemCurrentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(itemCurrentPage * itemsPerPage, itemTotalItems);
    
    document.getElementById('item-pagination-info').textContent = `Showing ${startItem} - ${endItem} of ${itemTotalItems} entries`;
    document.getElementById('item-page-info').textContent = `Page ${itemCurrentPage} of ${totalPages}`;
    
    document.getElementById('item-prev-btn').disabled = itemCurrentPage === 1;
    document.getElementById('item-next-btn').disabled = itemCurrentPage === totalPages || totalPages === 0;
}

// Load Detailed Ledger
async function loadDetailedLedger() {
    try {
        const branchInput = document.getElementById('detailed-branch').value;
        const productInput = document.getElementById('detailed-product').value;
        const fromDate = document.getElementById('detailed-from-date').value;
        const toDate = document.getElementById('detailed-to-date').value;
        
        // Find branch ID from datalist
        let branchId = null;
        if (branchInput) {
            const options = document.querySelectorAll('#detailed-branch-list option');
            for (let option of options) {
                if (option.value === branchInput) {
                    branchId = option.getAttribute('data-id');
                    break;
                }
            }
        }
        
        // Find product ID from datalist
        let productId = null;
        if (productInput) {
            const options = document.querySelectorAll('#detailed-product-list option');
            for (let option of options) {
                if (option.value === productInput) {
                    productId = option.getAttribute('data-id');
                    break;
                }
            }
        }
        
        let url = `${API_BASE}?action=detailed-ledger`;
        if (branchId) url += `&branch_id=${branchId}`;
        if (productId) url += `&product_id=${productId}`;
        if (fromDate) url += `&from_date=${fromDate}`;
        if (toDate) url += `&to_date=${toDate}`;
        
        console.log('Fetching Detailed Ledger from URL:', url);
        const response = await fetch(url);
        const data = await response.json();
        
        console.log('API Response Status:', data.success);
        console.log('Total Rows Returned:', data.data?.length);
        console.log('All Transaction Types:', data.data?.map(t => t.transaction_type) ?? []);
        console.log('FOC Transactions:', data.data?.filter(t => t.transaction_type.includes('FOC')) ?? []);
        
        if (data.success) {
            console.log('DEBUG - Detailed Ledger API Response:', {
                totalRows: data.data.length,
                transactions: data.data.map(t => ({ id: t.id, type: t.transaction_type, qty_in: t.qty_in, qty_out: t.qty_out, product: t.product_name }))
            });
            updateDetailedLedgerTable(data.data, data.currency);
        }
    } catch (error) {
        console.error('Error loading detailed ledger:', error);
    }
}

function updateDetailedLedgerTable(ledger, currency = '$') {
    allDetailedEntries = ledger;
    detailedTotalItems = allDetailedEntries.length;
    detailedCurrentPage = 1;
    detailedCurrency = currency;
    
    renderDetailedLedgerTable(detailedCurrency);
    updateDetailedPaginationControls();
}

function renderDetailedLedgerTable(currency = '$') {
    const tbody = document.querySelector('#detailed-ledger .table tbody');
    
    // Calculate running balance by product-branch combination for all data
    const balances = {};
    const processedLedger = allDetailedEntries.map(entry => {
        const key = `${entry.product_name}-${entry.branch_name}`;
        if (!balances[key]) balances[key] = 0;
        
        balances[key] += parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0);
        entry.balance = balances[key];
        entry.value = balances[key] * parseFloat(entry.unit_cost);
        
        return entry;
    });
    
    // Get current page data
    const startIndex = (detailedCurrentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = processedLedger.slice(startIndex, endIndex);
    
    // Calculate totals - simple sum of all displayed transactions
    const totalQtyIn = processedLedger.reduce((sum, entry) => sum + parseFloat(entry.qty_in || 0), 0);
    const totalQtyOut = processedLedger.reduce((sum, entry) => sum + parseFloat(entry.qty_out || 0), 0);
    
    console.log('=== Detailed Ledger Totals Debug ===');
    console.log('Total transactions:', processedLedger.length);
    console.log('Total Qty In:', totalQtyIn);
    console.log('Total Qty Out:', totalQtyOut);
    console.log('All OUT transactions:', processedLedger.filter(e => parseFloat(e.qty_out) > 0).map(e => ({ type: e.transaction_type, out: e.qty_out })));
    console.log('Manual sum check:', processedLedger.reduce((sum, e) => sum + parseFloat(e.qty_out || 0), 0));
    
    // Calculate total balance - sum of all final balances per product-branch
    const totalBalance = Object.values(balances).reduce((sum, balance) => sum + balance, 0);
    
    // Calculate total value - sum of final values per product-branch
    const finalValues = {};
    Object.keys(balances).forEach(key => {
        const entries = processedLedger.filter(e => `${e.product_name}-${e.branch_name}` === key);
        const lastEntry = entries[entries.length - 1];
        if (lastEntry) {
            finalValues[key] = parseFloat(lastEntry.value);
        }
    });
    const totalValue = Object.values(finalValues).reduce((sum, value) => sum + value, 0);
    
    tbody.innerHTML = pageData.map(entry => `
        <tr>
            <td>${entry.transaction_date}</td>
            <td>${entry.product_name}</td>
            <td>${entry.branch_name}</td>
            <td><span class="transaction-badge transaction-${entry.transaction_type.toLowerCase().replace(' ', '-')}">${entry.transaction_type}</span></td>
            <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}</td>
            <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
            <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
        </tr>
    `).join('') + `
        <tr style="font-weight: bold; background-color: var(--table-header);">
            <td colspan="4">Total (${allDetailedEntries.length} transactions)</td>
            <td>${totalQtyIn.toFixed(2)} ${allDetailedEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalQtyOut.toFixed(2)} ${allDetailedEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalBalance.toFixed(2)} ${allDetailedEntries[0]?.unit_symbol || 'L'}</td>
            <td>-</td>
            <td>${currency}${totalValue.toFixed(2)}</td>
        </tr>
    `;
}

function updateDetailedPaginationControls() {
    const totalPages = Math.ceil(detailedTotalItems / itemsPerPage);
    const startItem = detailedTotalItems === 0 ? 0 : (detailedCurrentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(detailedCurrentPage * itemsPerPage, detailedTotalItems);
    
    document.getElementById('detailed-pagination-info').textContent = `Showing ${startItem} - ${endItem} of ${detailedTotalItems} entries`;
    document.getElementById('detailed-page-info').textContent = `Page ${detailedCurrentPage} of ${totalPages}`;
    
    document.getElementById('detailed-prev-btn').disabled = detailedCurrentPage === 1;
    document.getElementById('detailed-next-btn').disabled = detailedCurrentPage === totalPages || totalPages === 0;
}

function updateBranchLedgerTable(ledger, currency = '$') {
    allBranchEntries = ledger;
    branchTotalItems = allBranchEntries.length;
    branchCurrentPage = 1;
    branchCurrency = currency;
    
    renderBranchLedgerTable(branchCurrency);
    updateBranchPaginationControls();
}

function renderBranchLedgerTable(currency = '$') {
    const tbody = document.querySelector('#branch-ledger .table tbody');
    
    // Get current page data
    const startIndex = (branchCurrentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = allBranchEntries.slice(startIndex, endIndex);
    
    // Calculate totals for all data
    const totalQtyIn = allBranchEntries.reduce((sum, entry) => sum + parseFloat(entry.qty_in || 0), 0);
    const totalQtyOut = allBranchEntries.reduce((sum, entry) => sum + parseFloat(entry.qty_out || 0), 0);
    const totalBalance = totalQtyIn - totalQtyOut;
    const totalValue = allBranchEntries.reduce((sum, entry) => sum + (parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0)) * parseFloat(entry.unit_cost), 0);
    
    tbody.innerHTML = pageData.map(entry => `
        <tr>
            <td>${entry.transaction_date}</td>
            <td>${entry.product_name}</td>
            <td><span class="transaction-badge transaction-${entry.transaction_type.toLowerCase().replace(' ', '-')}">${entry.transaction_type}</span></td>
            <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>-</td>
            <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
            <td>${currency}${((parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0)) * parseFloat(entry.unit_cost)).toFixed(2)}</td>
        </tr>
    `).join('') + `
        <tr style="font-weight: bold; background-color: var(--table-header);">
            <td colspan="3">Total (${allBranchEntries.length} transactions)</td>
            <td>${totalQtyIn.toFixed(2)} ${allBranchEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalQtyOut.toFixed(2)} ${allBranchEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalBalance.toFixed(2)} ${allBranchEntries[0]?.unit_symbol || 'L'}</td>
            <td>-</td>
            <td>${currency}${totalValue.toFixed(2)}</td>
        </tr>
    `;
}

function updateBranchPaginationControls() {
    const totalPages = Math.ceil(branchTotalItems / itemsPerPage);
    const startItem = branchTotalItems === 0 ? 0 : (branchCurrentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(branchCurrentPage * itemsPerPage, branchTotalItems);
    
    document.getElementById('branch-pagination-info').textContent = `Showing ${startItem} - ${endItem} of ${branchTotalItems} entries`;
    document.getElementById('branch-page-info').textContent = `Page ${branchCurrentPage} of ${totalPages}`;
    
    document.getElementById('branch-prev-btn').disabled = branchCurrentPage === 1;
    document.getElementById('branch-next-btn').disabled = branchCurrentPage === totalPages || totalPages === 0;
}
function updateCustomerLedgerTable(ledger, currency = '$') {
    allCustomerEntries = ledger;
    customerTotalItems = allCustomerEntries.length;
    customerCurrentPage = 1;
    customerCurrency = currency;

    renderCustomerLedgerTable(customerCurrency);
    updateCustomerPaginationControls();
}

function renderCustomerLedgerTable(currency = '$') {
    const tbody = document.querySelector('#customer-ledger .table tbody');

    const balances = {};
    const processedLedger = allCustomerEntries.map(entry => {
        const key = `${entry.product_name}-${entry.branch_name}`;
        if (!balances[key]) balances[key] = 0;
        balances[key] += parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0);
        entry.balance = balances[key];
        entry.value = balances[key] * parseFloat(entry.unit_cost);
        return entry;
    });

    const startIndex = (customerCurrentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = processedLedger.slice(startIndex, endIndex);

    const totalQtyIn = processedLedger.reduce((sum, e) => sum + parseFloat(e.qty_in || 0), 0);
    const totalQtyOut = processedLedger.reduce((sum, e) => sum + parseFloat(e.qty_out || 0), 0);
    const totalValue = processedLedger.reduce((sum, e) => sum + parseFloat(e.value || 0), 0);

    tbody.innerHTML = pageData.map(entry => `
        <tr>
            <td>${entry.transaction_date}</td>
            <td>${entry.product_name}</td>
            <td>${entry.branch_name}</td>
            <td><span class="transaction-badge transaction-${entry.transaction_type.toLowerCase().replace(' ', '-')}">${entry.transaction_type}</span></td>
            <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}</td>
            <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
            <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
        </tr>
    `).join('') + `
        <tr style="font-weight: bold; background-color: var(--table-header);">
            <td colspan="4">Total (${allCustomerEntries.length} transactions)</td>
            <td>${totalQtyIn.toFixed(2)} ${allCustomerEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalQtyOut.toFixed(2)} ${allCustomerEntries[0]?.unit_symbol || 'L'}</td>
            <td>-</td>
            <td>-</td>
            <td>${currency}${totalValue.toFixed(2)}</td>
        </tr>
    `;
}

function updateCustomerPaginationControls() {
    const totalPages = Math.ceil(customerTotalItems / itemsPerPage);
    const startItem = customerTotalItems === 0 ? 0 : (customerCurrentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(customerCurrentPage * itemsPerPage, customerTotalItems);

    document.getElementById('customer-pagination-info').textContent = `Showing ${startItem} - ${endItem} of ${customerTotalItems} entries`;
    document.getElementById('customer-page-info').textContent = `Page ${customerCurrentPage} of ${totalPages}`;

    document.getElementById('customer-prev-btn').disabled = customerCurrentPage === 1;
    document.getElementById('customer-next-btn').disabled = customerCurrentPage === totalPages || totalPages === 0;
}

async function loadCustomers() {
    try {
        const response = await fetch(`${API_BASE}?action=customers`);
        const data = await response.json();
        if (data.success) {
            const datalist = document.getElementById('customer-ledger-list');
            if (datalist) {
                datalist.innerHTML = data.data.map(c => `<option value="${c.customer_name}" data-id="${c.id}">`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}
// Load Dashboard Stats
async function loadDashboardStats() {
    try {
        const valuationMethod = document.getElementById('valuation-method').value;
        let url = `${API_BASE}?action=stats`;
        if (valuationMethod) url += `&valuation_method=${valuationMethod}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            updateDashboardStats(data.data, data.currency || '$');
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

function updateDashboardStats(stats, currency = '$') {
    document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = `${currency}${parseFloat(stats.total_value || 0).toFixed(2)}`;
    document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = `${stats.in_stock || 0} Products`;
    document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = `${stats.low_stock || 0} Products`;
    document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = `${stats.out_of_stock || 0} Products`;
    document.querySelector('.stat-card:nth-child(5) .stat-value').textContent = `${stats.overstock || 0} Products`;
}

// Load Branches
async function loadBranches() {
    try {
        const response = await fetch(`${API_BASE}?action=branches`);
        const data = await response.json();
        
        if (data.success) {
            populateBranchDropdowns(data.data);
        }
    } catch (error) {
        console.error('Error loading branches:', error);
    }
}

function populateBranchDropdowns(branches) {
    const branchLists = ['#branch-list', '#branch-ledger-list', '#detailed-branch-list'];
    branchLists.forEach(selector => {
        const datalist = document.querySelector(selector);
        if (datalist) {
            datalist.innerHTML = branches.map(branch => `<option value="${branch.display_name}" data-id="${branch.id}">`).join('');
        }
    });
}

// Load Products
async function loadProducts() {
    try {
        const response = await fetch(`${API_BASE}?action=products`);
        const data = await response.json();
        
        if (data.success) {
            populateProductDropdowns(data.data);
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

function populateProductDropdowns(products) {
    const productLists = ['#product-list', '#item-list', '#detailed-product-list'];
    productLists.forEach(selector => {
        const datalist = document.querySelector(selector);
        if (datalist) {
            datalist.innerHTML = products.map(product => `<option value="${product.name}" data-id="${product.id}">`).join('');
        }
    });
}

// Load Inventory Types
async function loadInventoryTypes() {
    try {
        const response = await fetch(`${API_BASE}?action=inventory-types`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('inventory-type');
            data.data.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading inventory types:', error);
    }
}

// Load Distributions
async function loadDistributions() {
    try {
        const response = await fetch(`${API_BASE}?action=distributions`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('distribution');
            data.data.forEach(dist => {
                const option = document.createElement('option');
                option.value = dist.id;
                option.textContent = dist.supplier_name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading distributions:', error);
    }
}

// Load Companies
async function loadCompanies() {
    // Removed - no longer needed
}

// DOMContentLoaded event handler
document.addEventListener('DOMContentLoaded', () => {
    // Load column visibility settings
    loadColumnVisibility();
    
    // Column visibility toggles
    const toggles = [
        { id: 'toggle-branch', key: 'branch' },
        { id: 'toggle-inventory-type', key: 'inventoryType' },
        { id: 'toggle-opening-balance', key: 'openingBalance' },
        { id: 'toggle-qty-in', key: 'qtyIn' },
        { id: 'toggle-qty-out', key: 'qtyOut' },
        { id: 'toggle-current-stock', key: 'currentStock' },
        { id: 'toggle-unit-cost', key: 'unitCost' },
        { id: 'toggle-stock-value', key: 'stockValue' },
        { id: 'toggle-status', key: 'status' }
    ];
    
    toggles.forEach(toggle => {
        document.getElementById(toggle.id).addEventListener('change', (e) => {
            columnVisibility[toggle.key] = e.target.checked;
            saveColumnVisibility();
            applyColumnVisibility();
        });
    });
    
    // Apply Position Filters
    document.getElementById('applyPositionFilters').addEventListener('click', () => {
        loadStockPosition();
        loadDashboardStats();
    });
// Clear Customer Filters
    document.getElementById('clearCustomerFilters').addEventListener('click', () => {
        document.getElementById('customer-ledger-input').value = '';
        document.getElementById('from-date-customer').value = '';
        document.getElementById('to-date-customer').value = '';
        const tbody = document.querySelector('#customer-ledger .table tbody');
        tbody.innerHTML = '';
        document.getElementById('customer-pagination-info').textContent = 'Showing 0 - 0 of 0 entries';
        document.getElementById('customer-page-info').textContent = 'Page 1 of 1';
    });

    // Apply Customer Filters
    document.getElementById('applyCustomerFilters').addEventListener('click', async () => {
        const customerName = document.getElementById('customer-ledger-input').value;
        const fromDate = document.getElementById('from-date-customer').value;
        const toDate = document.getElementById('to-date-customer').value;

        if (!customerName || customerName.trim() === '') {
            alert('Please select a customer');
            return;
        }

        const options = document.querySelectorAll('#customer-ledger-list option');
        let customerId = null;
        for (let option of options) {
            if (option.value === customerName) {
                customerId = option.getAttribute('data-id');
                break;
            }
        }

        if (!customerId) {
            alert('Invalid customer selected');
            return;
        }

        try {
            let url = `${API_BASE}?action=customer-ledger&customer_id=${customerId}`;
            if (fromDate) url += `&from_date=${fromDate}`;
            if (toDate) url += `&to_date=${toDate}`;

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                updateCustomerLedgerTable(data.data, data.currency);
            }
        } catch (error) {
            console.error('Error loading customer ledger:', error);
        }
    });

    // Customer-wise Ledger pagination event listeners
    document.getElementById('customer-prev-btn').addEventListener('click', () => {
        if (customerCurrentPage > 1) {
            customerCurrentPage--;
            renderCustomerLedgerTable(customerCurrency);
            updateCustomerPaginationControls();
        }
    });

    document.getElementById('customer-next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(customerTotalItems / itemsPerPage);
        if (customerCurrentPage < totalPages) {
            customerCurrentPage++;
            renderCustomerLedgerTable(customerCurrency);
            updateCustomerPaginationControls();
        }
    });
    // Clear Position Filters
    document.getElementById('clearPositionFilters').addEventListener('click', () => {
        document.getElementById('branch').value = '';
        document.getElementById('product').value = '';
        document.getElementById('inventory-type').value = '';
        document.getElementById('distribution').value = '';
        document.getElementById('stock-status').value = '';
        document.getElementById('status').value = '';
        document.getElementById('stock-level-filter').value = '';
        document.getElementById('from-date').value = '';
        document.getElementById('to-date').value = '';
        document.getElementById('valuation-method').value = 'AVCO';
        loadStockPosition();
        loadDashboardStats();
    });

    // Clear Item Filters
    document.getElementById('clearItemFilters').addEventListener('click', () => {
        document.getElementById('item').value = '';
        document.getElementById('from-date').value = '';
        document.getElementById('to-date').value = '';
        const tbody = document.querySelector('#item-ledger .table tbody');
        tbody.innerHTML = '';
        document.getElementById('item-pagination-info').textContent = 'Showing 0 - 0 of 0 entries';
        document.getElementById('item-page-info').textContent = 'Page 1 of 1';
    });

    // Apply Item Filters
    document.getElementById('applyFilters').addEventListener('click', async () => {
        const itemName = document.getElementById('item').value;
        const fromDate = document.getElementById('from-date').value;
        const toDate = document.getElementById('to-date').value;
        
        if (!itemName) {
            alert('Please select an item');
            return;
        }
        
        // Find product ID from datalist
        const options = document.querySelectorAll('#item-list option');
        let productId = null;
        for (let option of options) {
            if (option.value === itemName) {
                productId = option.getAttribute('data-id');
                break;
            }
        }
        
        if (!productId) {
            alert('Invalid product selected');
            return;
        }
        
        try {
            let url = `${API_BASE}?action=ledger&product_id=${productId}`;
            if (fromDate) url += `&from_date=${fromDate}`;
            if (toDate) url += `&to_date=${toDate}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                updateLedgerTable(data.data, data.currency);
            }
        } catch (error) {
            console.error('Error loading ledger:', error);
        }
    });

    // Clear Branch Filters
    document.getElementById('clearBranchFilters').addEventListener('click', () => {
        document.querySelector('input[list="branch-ledger-list"]').value = '';
        document.getElementById('from-date-branch').value = '';
        document.getElementById('to-date-branch').value = '';
        const tbody = document.querySelector('#branch-ledger .table tbody');
        tbody.innerHTML = '';
        document.getElementById('branch-pagination-info').textContent = 'Showing 0 - 0 of 0 entries';
        document.getElementById('branch-page-info').textContent = 'Page 1 of 1';
    });

    // Apply Branch Filters
    document.getElementById('applyBranchFilters').addEventListener('click', async () => {
        const branchName = document.querySelector('input[list="branch-ledger-list"]').value;
        const fromDate = document.getElementById('from-date-branch').value;
        const toDate = document.getElementById('to-date-branch').value;
        
        console.log('Branch name:', branchName);
        
        if (!branchName || branchName.trim() === '') {
            alert('Please select a branch');
            return;
        }
        
        // Find branch ID from datalist
        const options = document.querySelectorAll('#branch-ledger-list option');
        let branchId = null;
        for (let option of options) {
            if (option.value === branchName) {
                branchId = option.getAttribute('data-id');
                break;
            }
        }
        
        if (!branchId) {
            alert('Invalid branch selected');
            return;
        }
        
        try {
            let url = `${API_BASE}?action=branch-ledger&branch_id=${branchId}`;
            if (fromDate) url += `&from_date=${fromDate}`;
            if (toDate) url += `&to_date=${toDate}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                updateBranchLedgerTable(data.data, data.currency);
            }
        } catch (error) {
            console.error('Error loading branch ledger:', error);
        }
    });

    // Stock Position pagination event listeners
    document.getElementById('position-prev-btn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updatePositionTable(currentCurrency);
            updatePaginationControls();
        }
    });
    
    document.getElementById('position-next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            updatePositionTable(currentCurrency);
            updatePaginationControls();
        }
    });

    // Item-wise Ledger pagination event listeners
    document.getElementById('item-prev-btn').addEventListener('click', () => {
        if (itemCurrentPage > 1) {
            itemCurrentPage--;
            renderLedgerTable(itemCurrency);
            updateItemPaginationControls();
        }
    });
    
    document.getElementById('item-next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(itemTotalItems / itemsPerPage);
        if (itemCurrentPage < totalPages) {
            itemCurrentPage++;
            renderLedgerTable(itemCurrency);
            updateItemPaginationControls();
        }
    });

    // Branch-wise Ledger pagination event listeners
    document.getElementById('branch-prev-btn').addEventListener('click', () => {
        if (branchCurrentPage > 1) {
            branchCurrentPage--;
            renderBranchLedgerTable(branchCurrency);
            updateBranchPaginationControls();
        }
    });
    
    document.getElementById('branch-next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(branchTotalItems / itemsPerPage);
        if (branchCurrentPage < totalPages) {
            branchCurrentPage++;
            renderBranchLedgerTable(branchCurrency);
            updateBranchPaginationControls();
        }
    });

    // Detailed Ledger pagination event listeners
    document.getElementById('detailed-prev-btn').addEventListener('click', () => {
        if (detailedCurrentPage > 1) {
            detailedCurrentPage--;
            renderDetailedLedgerTable(detailedCurrency);
            updateDetailedPaginationControls();
        }
    });
    
    document.getElementById('detailed-next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(detailedTotalItems / itemsPerPage);
        if (detailedCurrentPage < totalPages) {
            detailedCurrentPage++;
            renderDetailedLedgerTable(detailedCurrency);
            updateDetailedPaginationControls();
        }
    });

    // Apply Detailed Filters
    document.getElementById('applyDetailedFilters').addEventListener('click', () => {
        loadDetailedLedger();
    });

    // Clear Detailed Filters
    document.getElementById('clearDetailedFilters').addEventListener('click', () => {
        document.getElementById('detailed-branch').value = '';
        document.getElementById('detailed-product').value = '';
        document.getElementById('detailed-from-date').value = '';
        document.getElementById('detailed-to-date').value = '';
        loadDetailedLedger();
    });

    // Load initial data
    loadDashboardStats();
    loadBranches();
    loadProducts();
    loadInventoryTypes();
    loadDistributions();
        loadCustomers();          // <-- YE LINE ADD KARO

    loadStockPosition();
    
    // Expand/Collapse functionality
    document.getElementById('expandAllBtn').addEventListener('click', () => {
        document.querySelectorAll('.child-row').forEach(row => row.style.display = '');
    });
    
    document.getElementById('collapseAllBtn').addEventListener('click', () => {
        document.querySelectorAll('.child-row').forEach(row => row.style.display = 'none');
    });
});