document.addEventListener('DOMContentLoaded', function () {
    const API_BASE = '../../../../server/api/inventory/stock_position/stock-position.php';
    const PRODUCT_ID = window.HARDCODED_PRODUCT_ID;
    const BRANCH_ID = window.HARDCODED_BRANCH_ID;
    
    let reportData = [];
    let filteredData = [];
    let currentPage = 1;
    const rowsPerPage = 20;

    // DOM Elements
    const reportBody = document.getElementById('reportBody');
    const reportPeriodSpan = document.getElementById('reportPeriod');
    const generatedDateSpan = document.getElementById('generatedDate');
    const totalTransactionsSpan = document.getElementById('totalTransactions');
    const currentBalanceSpan = document.getElementById('currentBalance');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const paginationInfoSpan = document.getElementById('paginationInfo');
    const periodFilter = document.getElementById('periodFilter');
    const customFromDate = document.getElementById('customFromDate');
    const customToDate = document.getElementById('customToDate');
    const transactionTypeFilter = document.getElementById('transactionTypeFilter');
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    const exportBtn = document.getElementById('exportBtn');
    const printReportBtn = document.getElementById('printReportBtn');

    // Summary elements
    const summaryOpening = document.getElementById('summaryOpening');
    const summaryTotalIn = document.getElementById('summaryTotalIn');
    const summaryTotalOut = document.getElementById('summaryTotalOut');
    const summaryClosing = document.getElementById('summaryClosing');
    const summaryNet = document.getElementById('summaryNet');
    const topSupplier = document.getElementById('topSupplier');
    const supplierAmount = document.getElementById('supplierAmount');
    const topCustomer = document.getElementById('topCustomer');
    const customerAmount = document.getElementById('customerAmount');

    // Initialize
    updateGeneratedDate();
    loadReportData();
    
    // Toggle custom date fields
    periodFilter.addEventListener('change', function() {
        if (this.value === 'custom') {
            customFromDate.style.display = 'block';
            customToDate.style.display = 'block';
        } else {
            customFromDate.style.display = 'none';
            customToDate.style.display = 'none';
        }
    });

    // Load report data from API
    async function loadReportData() {
        try {
            const fromDate = getFilterFromDate();
            const toDate = getFilterToDate();
            
            let url = `${API_BASE}?action=ledger&product_id=${PRODUCT_ID}`;
            if (fromDate) url += `&from_date=${fromDate}`;
            if (toDate) url += `&to_date=${toDate}`;
            
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                reportData = [];
                
                // Add BBF row if exists
                if (result.bbf && result.bbf > 0) {
                    const fromDate = getFilterFromDate();
                    reportData.push({
                        id: 'bbf',
                        date: fromDate,
                        displayDate: formatDate(fromDate),
                        details: 'Balance Brought Forward',
                        type: 'BBF',
                        inAmount: '',
                        outAmount: '',
                        balance: parseFloat(result.bbf).toFixed(2)
                    });
                }
                
                // Add transaction data
                reportData = reportData.concat(result.data.map(entry => {
                    let details = entry.party_name ? entry.party_name : entry.transaction_type;
                    
                    return {
                        id: entry.reference,
                        date: entry.transaction_date,
                        displayDate: formatDate(entry.transaction_date),
                        details: details,
                        type: entry.qty_in > 0 ? 'IN' : 'OUT',
                        inAmount: entry.qty_in > 0 ? parseFloat(entry.qty_in).toFixed(2) : '',
                        outAmount: entry.qty_out > 0 ? parseFloat(entry.qty_out).toFixed(2) : '',
                        balance: parseFloat(entry.balance).toFixed(2)
                    };
                }));
                
                filteredData = [...reportData];
                renderReport();
                updateSummary();
                updatePagination();
            }
        } catch (error) {
            console.error('Error loading report data:', error);
        }
    }
    
    function getFilterFromDate() {
        const period = periodFilter.value;
        const now = new Date();
        
        switch(period) {
            case 'current_month':
                return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
            case 'last_month':
                return new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().split('T')[0];
            case 'last_quarter':
                const quarterStart = new Date(now.getFullYear(), now.getMonth() - 3, 1);
                return quarterStart.toISOString().split('T')[0];
            case 'custom':
                return customFromDate.value || null;
            default:
                return null;
        }
    }
    
    function getFilterToDate() {
        const period = periodFilter.value;
        const now = new Date();
        
        switch(period) {
            case 'last_month':
                return new Date(now.getFullYear(), now.getMonth(), 0).toISOString().split('T')[0];
            case 'current_month':
            case 'last_quarter':
                return now.toISOString().split('T')[0];
            case 'custom':
                return customToDate.value || null;
            default:
                return null;
        }
    }

    // Format date for display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    // Update generated date
    function updateGeneratedDate() {
        const now = new Date();
        generatedDateSpan.textContent = now.toLocaleDateString('en-US', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Render report table
    function renderReport() {
        reportBody.innerHTML = '';

        // Calculate start and end index for current page
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = Math.min(startIndex + rowsPerPage, filteredData.length);

        // Render rows for current page
        for (let i = startIndex; i < endIndex; i++) {
            const row = filteredData[i];
            const rowElement = document.createElement('tr');

            // Create transaction type indicator
            let typeIndicator = '';
            if (row.type === 'BBF') {
                typeIndicator = '<span class="transaction-type" style="background-color: rgba(31, 123, 255, 0.1); color: #1f7bff;">BBF</span>';
            } else if (row.type === 'IN') {
                typeIndicator = '<span class="transaction-type type-in">IN</span>';
            } else {
                typeIndicator = '<span class="transaction-type type-out">OUT</span>';
            }

            // Apply color classes to IN/OUT amounts
            const inDisplay = row.inAmount ?
                `<span class="in-amount">${row.inAmount} L</span>` : '-';

            const outDisplay = row.outAmount ?
                `<span class="out-amount">${row.outAmount} L</span>` : '-';

            rowElement.innerHTML = `
                <td class="date-col">${row.displayDate}</td>
                <td class="details-col">
                    ${typeIndicator}
                    ${row.details}
                </td>
                <td class="in-col highlight-col">${inDisplay}</td>
                <td class="out-col highlight-col">${outDisplay}</td>
                <td class="balance-col highlight-col">${row.balance} L</td>
            `;

            reportBody.appendChild(rowElement);
        }

        // Update transaction count
        totalTransactionsSpan.textContent = filteredData.length;

        // Update report period
        if (filteredData.length > 0) {
            const firstDate = formatDate(filteredData[0].date);
            const lastDate = formatDate(filteredData[filteredData.length - 1].date);
            reportPeriodSpan.textContent = `${firstDate} - ${lastDate}`;

            // Update current balance
            currentBalanceSpan.textContent = `${filteredData[filteredData.length - 1].balance} L`;
        }
    }

    // Update pagination
    function updatePagination() {
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        paginationInfoSpan.textContent = `Page ${currentPage} of ${totalPages}`;

        prevPageBtn.disabled = currentPage === 1;
        nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    // Update summary statistics
    function updateSummary() {
        if (filteredData.length === 0) {
            resetSummary();
            return;
        }

        // Calculate totals and track suppliers/customers
        let totalIn = 0;
        let totalOut = 0;
        const supplierTotals = {};
        const customerTotals = {};

        filteredData.forEach(row => {
            if (row.type === 'BBF') return; // Skip BBF row
            
            if (row.inAmount) {
                totalIn += parseFloat(row.inAmount);

                // Extract supplier name from details
                if (row.type === 'IN' && row.details) {
                    supplierTotals[row.details] = (supplierTotals[row.details] || 0) + parseFloat(row.inAmount);
                }
            }

            if (row.outAmount) {
                totalOut += parseFloat(row.outAmount);

                // Extract customer name from details
                if (row.type === 'OUT' && row.details) {
                    customerTotals[row.details] = (customerTotals[row.details] || 0) + parseFloat(row.outAmount);
                }
            }
        });

        // Opening balance (BBF or calculated from first row)
        let openingBalance = 0;
        if (filteredData[0].type === 'BBF') {
            openingBalance = parseFloat(filteredData[0].balance);
        } else {
            openingBalance = parseFloat(filteredData[0].balance) -
                (parseFloat(filteredData[0].inAmount) || 0) +
                (parseFloat(filteredData[0].outAmount) || 0);
        }

        // Closing balance (last row)
        const closingBalance = parseFloat(filteredData[filteredData.length - 1].balance);

        // Net movement
        const netMovement = totalIn - totalOut;

        // Find top supplier and customer
        let topSupplierName = "None";
        let topSupplierAmount = 0;
        let topCustomerName = "None";
        let topCustomerAmount = 0;

        Object.entries(supplierTotals).forEach(([supplier, amount]) => {
            if (amount > topSupplierAmount) {
                topSupplierName = supplier;
                topSupplierAmount = amount;
            }
        });

        Object.entries(customerTotals).forEach(([customer, amount]) => {
            if (amount > topCustomerAmount) {
                topCustomerName = customer;
                topCustomerAmount = amount;
            }
        });

        // Update UI
        summaryOpening.textContent = `${openingBalance.toFixed(2)} L`;
        summaryTotalIn.textContent = `${totalIn.toFixed(2)} L`;
        summaryTotalOut.textContent = `${totalOut.toFixed(2)} L`;
        summaryClosing.textContent = `${closingBalance.toFixed(2)} L`;
        summaryNet.textContent = `${netMovement >= 0 ? '+' : ''}${netMovement.toFixed(2)} L`;
        summaryNet.style.color = netMovement >= 0 ? '#2FBF71' : '#E34F4F';

        topSupplier.textContent = topSupplierName;
        supplierAmount.textContent = `${topSupplierAmount.toFixed(2)} L`;
        topCustomer.textContent = topCustomerName;
        customerAmount.textContent = `${topCustomerAmount.toFixed(2)} L`;
    }

    // Reset summary to zero values
    function resetSummary() {
        summaryOpening.textContent = '0.00 L';
        summaryTotalIn.textContent = '0.00 L';
        summaryTotalOut.textContent = '0.00 L';
        summaryClosing.textContent = '0.00 L';
        summaryNet.textContent = '0.00 L';
        summaryNet.style.color = '#0E1A2B';
        topSupplier.textContent = 'None';
        supplierAmount.textContent = '0.00 L';
        topCustomer.textContent = 'None';
        customerAmount.textContent = '0.00 L';
        currentBalanceSpan.textContent = '0.00 L';
    }

    // Apply filters
    async function applyFilters() {
        const transactionType = transactionTypeFilter.value;
        
        // Reload data with period filter
        await loadReportData();
        
        // Filter by transaction type
        if (transactionType !== 'all') {
            if (transactionType === 'in') {
                filteredData = reportData.filter(row => row.type === 'IN');
            } else if (transactionType === 'out') {
                filteredData = reportData.filter(row => row.type === 'OUT');
            }
        } else {
            filteredData = [...reportData];
        }
        
        currentPage = 1;
        renderReport();
        updateSummary();
        updatePagination();
    }

    // Export to CSV
    function exportToCSV() {
        if (filteredData.length === 0) {
            alert('No data to export.');
            return;
        }

        // Create CSV header
        const headers = ['Date', 'Details', 'IN (Liters)', 'OUT (Liters)', 'Balance'];

        // Create CSV rows
        const csvRows = [
            headers.join(','),
            ...filteredData.map(row => [
                row.displayDate,
                `"${row.details}"`,
                row.inAmount || '0',
                row.outAmount || '0',
                row.balance
            ].join(','))
        ];

        // Create CSV content
        const csvContent = csvRows.join('\n');

        // Create download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `petrol-stock-report-${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        alert(`Report exported successfully! ${filteredData.length} records exported.`);
    }

    // Event Listeners
    applyFilterBtn.addEventListener('click', applyFilters);

    exportBtn.addEventListener('click', exportToCSV);

    printReportBtn.addEventListener('click', function () {
        const fromDate = getFilterFromDate();
        const toDate = getFilterToDate();
        
        let url = 'worksheet-stock-pmg-print.php?';
        if (fromDate) url += `from_date=${fromDate}&`;
        if (toDate) url += `to_date=${toDate}&`;
        
        window.open(url, '_blank');
    });

    prevPageBtn.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderReport();
            updatePagination();
        }
    });

    nextPageBtn.addEventListener('click', function () {
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderReport();
            updatePagination();
        }
    });
});