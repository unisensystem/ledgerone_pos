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
// Company-wise Ledger pagination variables
let companyCurrentPage = 1;
let companyTotalItems = 0;
let allCompanyEntries = [];
let companyCurrency = '$';

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

    if (activeTab === 'company-ledger') {
        openCompanyLedgerPreview();
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

    let url = `print.php?type=${activeTab}&`;

    if (activeTab === 'position') {
        if (branch) url += `branch_id=${branch}&`;
        if (product) url += `product_id=${product}&`;
        if (inventoryType) url += `inventory_type_id=${inventoryType}&`;
        if (stockStatus) url += `stock_status=${stockStatus}&`;
        if (status) url += `status=${status}&`;
        if (stockLevelFilter) url += `stock_level=${stockLevelFilter}&`;
        if (fromDate) url += `from_date=${fromDate}&`;
        if (toDate) url += `to_date=${toDate}&`;
        if (valuationMethod) url += `valuation_method=${valuationMethod}&`;
    } else if (activeTab === 'item-ledger') {
        // Item-wise Ledger tab filters
        const itemName = document.getElementById('item').value;
        let productId = null;
        if (itemName) {
            const options = document.querySelectorAll('#item-list option');
            for (let option of options) {
                if (option.value === itemName) {
                    productId = option.getAttribute('data-id');
                    break;
                }
            }
        }
        const itemFromDate = document.getElementById('from-date').value;
        const itemToDate = document.getElementById('to-date').value;
        const batchFilter = document.getElementById('item-batch-filter')?.value;
        const expiryFilter = document.getElementById('item-expiry-filter')?.value;

        if (productId) url += `product_id=${productId}&`;
        if (itemFromDate) url += `from_date=${itemFromDate}&`;
        if (itemToDate) url += `to_date=${itemToDate}&`;
        if (batchFilter) url += `batch_no=${encodeURIComponent(batchFilter)}&`;
        if (expiryFilter) url += `expiry_date=${expiryFilter}&`;
    } else if (activeTab === 'branch-ledger') {
        const branchName = document.querySelector('input[list="branch-ledger-list"]')?.value;
        let branchId = null;
        if (branchName) {
            const options = document.querySelectorAll('#branch-ledger-list option');
            for (let option of options) {
                if (option.value === branchName) {
                    branchId = option.getAttribute('data-id');
                    break;
                }
            }
        }
        const branchFromDate = document.getElementById('from-date-branch')?.value;
        const branchToDate = document.getElementById('to-date-branch')?.value;

        if (branchId) url += `branch_id=${branchId}&`;
        if (branchFromDate) url += `from_date=${branchFromDate}&`;
        if (branchToDate) url += `to_date=${branchToDate}&`;
    } else if (activeTab === 'detailed-ledger') {
        const branchInput = document.getElementById('detailed-branch').value;
        const productInput = document.getElementById('detailed-product').value;
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
        const detailedFromDate = document.getElementById('detailed-from-date').value;
        const detailedToDate = document.getElementById('detailed-to-date').value;
        const batchFilter = document.getElementById('detailed-batch-filter')?.value;
        const expiryFilter = document.getElementById('detailed-expiry-filter')?.value;

        if (branchId) url += `branch_id=${branchId}&`;
        if (productId) url += `product_id=${productId}&`;
        if (detailedFromDate) url += `from_date=${detailedFromDate}&`;
        if (detailedToDate) url += `to_date=${detailedToDate}&`;
        if (batchFilter) url += `batch_no=${encodeURIComponent(batchFilter)}&`;
        if (expiryFilter) url += `expiry_date=${expiryFilter}&`;
    }

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

    // Company-wise Ledger: same pattern as Customer-wise Ledger — open the print preview first.
    if (activeTab === 'company-ledger') {
        openCompanyLedgerPreview();
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
                'Date': entry.transaction_date,
                'Transaction': entry.transaction_type,
                'Batch No': entry.batch_no || '-',
                'Expiry Date': entry.batch_expiry_date || '-',
                'Qty In': entry.qty_in > 0 ? `${parseFloat(entry.qty_in).toFixed(2)} ${entry.unit_symbol || 'L'}` : '-',
                'Qty Out': entry.qty_out > 0 ? `${parseFloat(entry.qty_out).toFixed(2)} ${entry.unit_symbol || 'L'}` : '-',
                'Balance': `${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}`,
                'Unit Cost': parseFloat(entry.unit_cost).toFixed(2),
                'Value': parseFloat(entry.value).toFixed(2)
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
                 'Batch No': entry.batch_no || '-',
                'Expiry Date': entry.batch_expiry_date || '-',
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
    const productName = document.getElementById('customer-product-input').value;

    if ((!customerName || customerName.trim() === '') && (!productName || productName.trim() === '')) {
        alert('Please select a Customer or a Product');
        return;
    }

    let customerId = null;
    if (customerName) {
        const options = document.querySelectorAll('#customer-ledger-list option');
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
    }

    let productId = null;
    if (productName) {
        const productOptions = document.querySelectorAll('#customer-product-list option');
        for (let option of productOptions) {
            if (option.value === productName) {
                productId = option.getAttribute('data-id');
                break;
            }
        }
        if (!productId) {
            alert('Invalid product selected. Please pick a product from the list.');
            return;
        }
    }

    const fromDate = document.getElementById('from-date-customer').value;
    const toDate = document.getElementById('to-date-customer').value;
    const batchFilter = document.getElementById('customer-batch-filter')?.value;
    const expiryFilter = document.getElementById('customer-expiry-filter')?.value;

    let url = `print_customer_ledger.php?`;
    if (customerId) url += `customer_id=${customerId}&`;
    if (productId) url += `product_id=${productId}&`;
    if (fromDate) url += `from_date=${fromDate}&`;
    if (toDate) url += `to_date=${toDate}&`;
    if (batchFilter) url += `batch_no=${encodeURIComponent(batchFilter)}&`;
    if (expiryFilter) url += `expiry_date=${expiryFilter}&`;

    window.open(url, '_blank');
}

// Company-wise (Supplier) Ledger ke liye print preview page kholta hai
function openCompanyLedgerPreview() {
    const companyName = document.getElementById('company-ledger-input').value;
    const branchName = document.getElementById('company-branch-input').value;
    const productName = document.getElementById('company-product-input').value;

    if (!companyName || companyName.trim() === '') {
        alert('Please select a Supplier');
        return;
    }

    let companyId = null;
    const companyOptions = document.querySelectorAll('#company-ledger-list option');
    for (let option of companyOptions) {
        if (option.value === companyName) {
            companyId = option.getAttribute('data-id');
            break;
        }
    }
    if (!companyId) {
        alert('Invalid supplier selected. Please pick from the list.');
        return;
    }

    let branchId = null;
    if (branchName) {
        const branchOptions = document.querySelectorAll('#company-branch-list option');
        for (let option of branchOptions) {
            if (option.value === branchName) {
                branchId = option.getAttribute('data-id');
                break;
            }
        }
    }

    let productId = null;
    if (productName) {
        const productOptions = document.querySelectorAll('#company-product-list option');
        for (let option of productOptions) {
            if (option.value === productName) {
                productId = option.getAttribute('data-id');
                break;
            }
        }
    }

    const fromDate = document.getElementById('from-date-company').value;
    const toDate = document.getElementById('to-date-company').value;
    const batchFilter = document.getElementById('company-batch-filter')?.value;
    const expiryFilter = document.getElementById('company-expiry-filter')?.value;

    let url = `print_company_ledger.php?company_id=${companyId}&`;
    if (branchId) url += `branch_id=${branchId}&`;
    if (productId) url += `product_id=${productId}&`;
    if (fromDate) url += `from_date=${fromDate}&`;
    if (toDate) url += `to_date=${toDate}&`;
    if (batchFilter) url += `batch_no=${encodeURIComponent(batchFilter)}&`;
    if (expiryFilter) url += `expiry_date=${expiryFilter}&`;

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
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            allPositions = Array.isArray(data.data) ? data.data : [];
            
            // Apply stock level filter
            if (stockLevelFilter === 'zero-or-less') {
                allPositions = allPositions.filter(pos => {
                    const stock = parseFloat(pos.current_stock);
                    return stock <= 0;
                });
            } else if (stockLevelFilter === 'greater-than-zero') {
                allPositions = allPositions.filter(pos => {
                    const stock = parseFloat(pos.current_stock);
                    return stock > 0;
                });
            }
            
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

function populateProductDropdowns(products) {
const productLists = ['#product-list', '#item-list', '#detailed-product-list', '#customer-product-list', '#company-product-list'];
    productLists.forEach(selector => {
        const datalist = document.querySelector(selector);
        if (datalist) {
            datalist.innerHTML = products.map(product => `<option value="${product.name}" data-id="${product.id}">`).join('');
        }
    });
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
function filterLedgerByBatchExpiry(entries, batchFilter, expiryFilter) {
    const batch = (batchFilter || '').trim().toLowerCase();

    return entries.filter(entry => {
        // Batch No filter
        if (batch) {
            const entryBatch = String(entry.batch_no || '').toLowerCase();

            if (!entryBatch.includes(batch)) {
                return false;
            }
        }

        // Expiry Date filter
        if (expiryFilter) {
            const entryExpiry = String(entry.batch_expiry_date || '').slice(0, 10);

            if (entryExpiry !== expiryFilter) {
                return false;
            }
        }

        return true;
    });
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

function renderLedgerTable(currency = '$') {
    const tbody = document.querySelector('#item-ledger .table tbody');
    
    // Get current page data
    const startIndex = (itemCurrentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = allLedgerEntries.slice(startIndex, endIndex);
    
    // Calculate totals for all data
    const totalQtyIn = allLedgerEntries.reduce((sum, entry) => sum + parseFloat(entry.qty_in || 0), 0);
    const totalQtyOut = allLedgerEntries.reduce((sum, entry) => sum + parseFloat(entry.qty_out || 0), 0);
    const finalBalance = allLedgerEntries.length > 0 ? parseFloat(allLedgerEntries[allLedgerEntries.length - 1].balance) : 0;
    const finalValue = allLedgerEntries.length > 0 ? parseFloat(allLedgerEntries[allLedgerEntries.length - 1].value) : 0;
    
tbody.innerHTML = pageData.map(entry => `
    <tr>
        <td>${entry.transaction_date}</td>
        <td><span class="transaction-badge transaction-${entry.transaction_type.toLowerCase().replace(' ', '-')}">${entry.transaction_type}</span></td>
        <td>${entry.batch_no || '-'}</td>
        <td>${entry.batch_expiry_date || '-'}</td>
        <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
        <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
        <td>${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}</td>
        <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
        <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
    </tr>
`).join('') + `
    <tr style="font-weight: bold; background-color: var(--table-header);">
        <td colspan="4">Total (${allLedgerEntries.length} transactions)</td>
        <td>${totalQtyIn.toFixed(2)} ${allLedgerEntries[0]?.unit_symbol || 'L'}</td>
        <td>${totalQtyOut.toFixed(2)} ${allLedgerEntries[0]?.unit_symbol || 'L'}</td>
        <td>${finalBalance.toFixed(2)} ${allLedgerEntries[0]?.unit_symbol || 'L'}</td>
        <td>-</td>
        <td>${currency}${finalValue.toFixed(2)}</td>
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
        // NEW: Batch No and Expiry Date filters
        const batchFilter = document.getElementById('detailed-batch-filter').value;
        const expiryFilter = document.getElementById('detailed-expiry-filter').value;
        
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
        
        const response = await fetch(url);
        const data = await response.json();
        
       if (data.success) {
    // Filter by Batch No and Expiry Date
    const filteredData = filterLedgerByBatchExpiry(
        data.data,
        batchFilter,
        expiryFilter
    );

    updateDetailedLedgerTable(filteredData, data.currency);
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
        <td>${entry.batch_no || '-'}</td>
        <td>${entry.batch_expiry_date || '-'}</td>
        <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
        <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
        <td>${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}</td>
        <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
        <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
    </tr>
`).join('') + `
    <tr style="font-weight: bold; background-color: var(--table-header);">
        <td colspan="6">Total (${allDetailedEntries.length} transactions)</td>
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
        <td>${entry.invoice_number || '-'}</td>
        <td>${entry.product_name}</td>
        <td>${entry.branch_name}</td>
        <td><span class="transaction-badge transaction-${entry.transaction_type.toLowerCase().replace(' ', '-')}">${entry.transaction_type}</span></td>
        <td>${entry.batch_no || '-'}</td>
        <td>${entry.batch_expiry_date || '-'}</td>
        <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
        <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
        <td>${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}</td>
        <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
        <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
    </tr>
`).join('') + `
    <tr style="font-weight: bold; background-color: var(--table-header);">
        <td colspan="7">Total (${allCustomerEntries.length} transactions)</td>
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
function updateCompanyLedgerTable(ledger, currency = '$') {
    allCompanyEntries = ledger;
    companyTotalItems = allCompanyEntries.length;
    companyCurrentPage = 1;
    companyCurrency = currency;

    renderCompanyLedgerTable(companyCurrency);
    updateCompanyPaginationControls();
}

function renderCompanyLedgerTable(currency = '$') {
    const tbody = document.querySelector('#company-ledger .table tbody');

    const balances = {};
    const processedLedger = allCompanyEntries.map(entry => {
        const key = `${entry.product_name}-${entry.branch_name}`;
        if (!balances[key]) balances[key] = 0;
        balances[key] += parseFloat(entry.qty_in || 0) - parseFloat(entry.qty_out || 0) - parseFloat(entry.qty_foc || 0);
        entry.balance = balances[key];
        entry.value = balances[key] * parseFloat(entry.unit_cost);
        return entry;
    });

    const startIndex = (companyCurrentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = processedLedger.slice(startIndex, endIndex);

    const totalQtyIn = processedLedger.reduce((sum, e) => sum + parseFloat(e.qty_in || 0), 0);
    const totalQtyOut = processedLedger.reduce((sum, e) => sum + parseFloat(e.qty_out || 0), 0);
    const totalFoc = processedLedger.reduce((sum, e) => sum + parseFloat(e.qty_foc || 0), 0);
    const totalValue = processedLedger.reduce((sum, e) => sum + parseFloat(e.value || 0), 0);

    tbody.innerHTML = pageData.map(entry => `
        <tr>
            <td>${entry.transaction_date}</td>
            <td>${entry.invoice_number || '-'}</td>
            <td>${entry.product_name}</td>
            <td>${entry.batch_no || '-'}</td>
            <td>${entry.batch_expiry_date || '-'}</td>
            <td>${entry.branch_name}</td>
            <td><span class="transaction-badge transaction-${entry.transaction_type.toLowerCase().replace(' ', '-')}">${entry.transaction_type}</span></td>
            <td>${entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${entry.qty_foc > 0 ? parseFloat(entry.qty_foc).toFixed(2) + ' ' + (entry.unit_symbol || 'L') : '-'}</td>
            <td>${parseFloat(entry.balance).toFixed(2)} ${entry.unit_symbol || 'L'}</td>
            <td>${currency}${parseFloat(entry.unit_cost).toFixed(2)}</td>
            <td>${currency}${parseFloat(entry.value).toFixed(2)}</td>
        </tr>
    `).join('') + `
        <tr style="font-weight: bold; background-color: var(--table-header);">
            <td colspan="7">Total (${allCompanyEntries.length} transactions)</td>
            <td>${totalQtyIn.toFixed(2)} ${allCompanyEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalQtyOut.toFixed(2)} ${allCompanyEntries[0]?.unit_symbol || 'L'}</td>
            <td>${totalFoc.toFixed(2)} ${allCompanyEntries[0]?.unit_symbol || 'L'}</td>
            <td>-</td>
            <td>-</td>
            <td>${currency}${totalValue.toFixed(2)}</td>
        </tr>
    `;
}

function updateCompanyPaginationControls() {
    const totalPages = Math.ceil(companyTotalItems / itemsPerPage);
    const startItem = companyTotalItems === 0 ? 0 : (companyCurrentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(companyCurrentPage * itemsPerPage, companyTotalItems);

    document.getElementById('company-pagination-info').textContent = `Showing ${startItem} - ${endItem} of ${companyTotalItems} entries`;
    document.getElementById('company-page-info').textContent = `Page ${companyCurrentPage} of ${totalPages}`;

    document.getElementById('company-prev-btn').disabled = companyCurrentPage === 1;
    document.getElementById('company-next-btn').disabled = companyCurrentPage === totalPages || totalPages === 0;
}

async function loadCompaniesForLedger() {
    try {
        const response = await fetch(`${API_BASE}?action=companies`);
        const data = await response.json();
        if (data.success) {
            const datalist = document.getElementById('company-ledger-list');
            if (datalist) {
                datalist.innerHTML = data.data.map(c => `<option value="${c.company_name}" data-id="${c.id}">`).join('');
            }
        }
    } catch (error) {
        console.error('Error loading suppliers:', error);
    }
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

function populateCompanyBranchDropdown(branches) {
    const datalist = document.getElementById('company-branch-list');
    if (datalist) {
        datalist.innerHTML = branches.map(b => `<option value="${b.display_name}" data-id="${b.id}">`).join('');
    }
}
function populateCompanyProductDropdown(products) {
    const datalist = document.getElementById('company-product-list');
    if (datalist) {
        datalist.innerHTML = products.map(p => `<option value="${p.name}" data-id="${p.id}">`).join('');
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
const branchLists = ['#branch-list', '#branch-ledger-list', '#detailed-branch-list', '#company-branch-list'];

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
    document.getElementById('customer-product-input').value = '';
    document.getElementById('from-date-customer').value = '';
    document.getElementById('to-date-customer').value = '';

    document.getElementById('customer-batch-filter').value = '';
    document.getElementById('customer-expiry-filter').value = '';

    allCustomerEntries = [];
    customerTotalItems = 0;
    customerCurrentPage = 1;

    const tbody = document.querySelector('#customer-ledger .table tbody');
    tbody.innerHTML = '';

    document.getElementById('customer-pagination-info').textContent =
        'Showing 0 - 0 of 0 entries';

    document.getElementById('customer-page-info').textContent =
        'Page 1 of 1';
});

    // Apply Customer Filters
 document.getElementById('applyCustomerFilters').addEventListener('click', async () => {
    const batchFilter = document.getElementById('customer-batch-filter').value;
const expiryFilter = document.getElementById('customer-expiry-filter').value;
    const customerName = document.getElementById('customer-ledger-input').value;
    const productName = document.getElementById('customer-product-input').value;
    const fromDate = document.getElementById('from-date-customer').value;
    const toDate = document.getElementById('to-date-customer').value;

    if ((!customerName || customerName.trim() === '') && (!productName || productName.trim() === '')) {
        alert('Please select a Customer or a Product');
        return;
    }

    // Resolve customer id (optional)
    let customerId = null;
    if (customerName) {
        const options = document.querySelectorAll('#customer-ledger-list option');
        for (let option of options) {
            if (option.value === customerName) {
                customerId = option.getAttribute('data-id');
                break;
            }
        }
        if (!customerId) {
            alert('Invalid customer selected. Please pick from the list.');
            return;
        }
    }

    // Resolve product id (optional)
    let productId = null;
    if (productName) {
        const productOptions = document.querySelectorAll('#customer-product-list option');
        for (let option of productOptions) {
            if (option.value === productName) {
                productId = option.getAttribute('data-id');
                break;
            }
        }
        if (!productId) {
            alert('Invalid product selected. Please pick from the list.');
            return;
        }
    }

    try {
        let url = `${API_BASE}?action=customer-ledger`;
        if (customerId) url += `&customer_id=${customerId}`;
        if (productId) url += `&product_id=${productId}`;
        if (fromDate) url += `&from_date=${fromDate}`;
        if (toDate) url += `&to_date=${toDate}`;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
    const filteredData = filterLedgerByBatchExpiry(
        data.data,
        batchFilter,
        expiryFilter
    );

    updateCustomerLedgerTable(filteredData, data.currency);
} else {
            alert(data.message || 'Failed to load ledger');
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

    document.getElementById('item-batch-filter').value = '';
    document.getElementById('item-expiry-filter').value = '';

    allLedgerEntries = [];
    itemTotalItems = 0;
    itemCurrentPage = 1;

    const tbody = document.querySelector('#item-ledger .table tbody');
    tbody.innerHTML = '';

    document.getElementById('item-pagination-info').textContent =
        'Showing 0 - 0 of 0 entries';

    document.getElementById('item-page-info').textContent =
        'Page 1 of 1';
});

    // Apply Item Filters
   document.getElementById('applyFilters').addEventListener('click', async () => {
    const itemName = document.getElementById('item').value;
    const fromDate = document.getElementById('from-date').value;
    const toDate = document.getElementById('to-date').value;

    const batchFilter = document.getElementById('item-batch-filter').value;
    const expiryFilter = document.getElementById('item-expiry-filter').value;

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

            let filteredData = filterLedgerByBatchExpiry(
                data.data,
                batchFilter,
                expiryFilter
            );

            updateLedgerTable(filteredData, data.currency);
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
        // Company-wise Ledger pagination event listeners
    document.getElementById('company-prev-btn').addEventListener('click', () => {
        if (companyCurrentPage > 1) {
            companyCurrentPage--;
            renderCompanyLedgerTable(companyCurrency);
            updateCompanyPaginationControls();
        }
    });

    document.getElementById('company-next-btn').addEventListener('click', () => {
        const totalPages = Math.ceil(companyTotalItems / itemsPerPage);
        if (companyCurrentPage < totalPages) {
            companyCurrentPage++;
            renderCompanyLedgerTable(companyCurrency);
            updateCompanyPaginationControls();
        }
    });

    // Clear Company Filters
    document.getElementById('clearCompanyFilters').addEventListener('click', () => {
        document.getElementById('company-ledger-input').value = '';
        document.getElementById('company-branch-input').value = '';
        document.getElementById('company-product-input').value = '';
        document.getElementById('from-date-company').value = '';
        document.getElementById('to-date-company').value = '';
        const tbody = document.querySelector('#company-ledger .table tbody');
        tbody.innerHTML = '';
        document.getElementById('company-pagination-info').textContent = 'Showing 0 - 0 of 0 entries';
        document.getElementById('company-page-info').textContent = 'Page 1 of 1';
    });

    // Apply Company Filters
    document.getElementById('applyCompanyFilters').addEventListener('click', async () => {
        const batchFilter = document.getElementById('company-batch-filter').value;
const expiryFilter = document.getElementById('company-expiry-filter').value;
        const companyName = document.getElementById('company-ledger-input').value;
        const branchName = document.getElementById('company-branch-input').value;
        const productName = document.getElementById('company-product-input').value;
        const fromDate = document.getElementById('from-date-company').value;
        const toDate = document.getElementById('to-date-company').value;

        if (!companyName || companyName.trim() === '') {
            alert('Please select a Supplier');
            return;
        }

        let companyId = null;
        const companyOptions = document.querySelectorAll('#company-ledger-list option');
        for (let option of companyOptions) {
            if (option.value === companyName) {
                companyId = option.getAttribute('data-id');
                break;
            }
        }
        if (!companyId) {
            alert('Invalid supplier selected. Please pick from the list.');
            return;
        }

        let branchId = null;
        if (branchName) {
            const branchOptions = document.querySelectorAll('#company-branch-list option');
            for (let option of branchOptions) {
                if (option.value === branchName) {
                    branchId = option.getAttribute('data-id');
                    break;
                }
            }
        }

        let productId = null;
        if (productName) {
            const productOptions = document.querySelectorAll('#company-product-list option');
            for (let option of productOptions) {
                if (option.value === productName) {
                    productId = option.getAttribute('data-id');
                    break;
                }
            }
        }

        try {
            let url = `${API_BASE}?action=company-ledger&company_id=${companyId}`;
            if (branchId) url += `&branch_id=${branchId}`;
            if (productId) url += `&product_id=${productId}`;
            if (fromDate) url += `&from_date=${fromDate}`;
            if (toDate) url += `&to_date=${toDate}`;

            const response = await fetch(url);
            const data = await response.json();

           if (data.success) {
    const filteredData = filterLedgerByBatchExpiry(
        data.data,
        batchFilter,
        expiryFilter
    );

    updateCompanyLedgerTable(filteredData, data.currency);
}else {
                alert(data.message || 'Failed to load ledger');
            }
        } catch (error) {
            console.error('Error loading supplier ledger:', error);
        }
    });

    // Load initial data
    loadDashboardStats();
    loadBranches();
    loadProducts();
    loadInventoryTypes();
    loadDistributions();
        loadCustomers();
            loadCompaniesForLedger();

    loadStockPosition();
    
    // Expand/Collapse functionality
    document.getElementById('expandAllBtn').addEventListener('click', () => {
        document.querySelectorAll('.child-row').forEach(row => row.style.display = '');
    });
    
    document.getElementById('collapseAllBtn').addEventListener('click', () => {
        document.querySelectorAll('.child-row').forEach(row => row.style.display = 'none');
    });
});
