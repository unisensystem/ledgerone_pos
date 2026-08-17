document.addEventListener('DOMContentLoaded', async function () {
    // Initial state
    let currentPage = 1;
    const rowsPerPage = 30;
    let worksheetData = [];

    // Fetch existing data
    await fetchWorksheetData();

    // If no data, initialize with 30 empty rows
    if (worksheetData.length === 0) {
        for (let i = 0; i < 30; i++) {
            worksheetData.push({
                id: i + 1,
                date: i === 0 ? getCurrentDate() : '',
                openingMeter: '',
                closingMeter: '',
                liters: '',
                rate: '',
                amount: '',
                page: 1
            });
        }
    }

    // DOM elements
    const worksheetBody = document.getElementById('worksheetBody');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const currentPageSpan = document.getElementById('currentPage');
    const pageNumberSpan = document.getElementById('pageNumber');
    const totalPagesSpan = document.getElementById('totalPages');
    const totalEntriesSpan = document.getElementById('totalEntries');
    const addRowBtn = document.getElementById('addRowBtn');
    const clearBtn = document.getElementById('clearBtn');
    const saveBtn = document.getElementById('saveBtn');
    const printBtn = document.getElementById('printBtn');
    const totalLitersSpan = document.getElementById('totalLiters');
    const totalAmountSpan = document.getElementById('totalAmount');
    const avgRateSpan = document.getElementById('avgRate');

    // Initialize the worksheet
    renderWorksheet();
    updateSummary();
    updatePagination();

    // Helper function to get current date in YYYY-MM-DD format
    function getCurrentDate() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Calculate amount from liters and rate
    function calculateAmount(liters, rate) {
        if (!liters || !rate) return '';
        const litersNum = parseFloat(liters);
        const rateNum = parseFloat(rate);
        if (isNaN(litersNum) || isNaN(rateNum)) return '';
        return (litersNum * rateNum).toFixed(2);
    }

    // Calculate liters from opening and closing meter
    function calculateLiters(opening, closing) {
        if (!opening || !closing) return '';
        const openingNum = parseFloat(opening);
        const closingNum = parseFloat(closing);
        if (isNaN(openingNum) || isNaN(closingNum)) return '';
        if (closingNum < openingNum) return 'ERROR';
        return (closingNum - openingNum).toFixed(2);
    }

    // Update pagination info
    function updatePagination() {
        const totalPages = Math.ceil(worksheetData.length / rowsPerPage);
        totalPagesSpan.textContent = totalPages;
        currentPageSpan.textContent = currentPage;
        pageNumberSpan.textContent = currentPage;
        totalEntriesSpan.textContent = `${rowsPerPage} entries`;

        prevPageBtn.disabled = currentPage === 1;
        nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    // Update summary totals
    function updateSummary() {
        let totalLiters = 0;
        let totalAmount = 0;
        let totalRate = 0;
        let rateCount = 0;

        worksheetData.forEach(row => {
            if (row.liters && !isNaN(parseFloat(row.liters))) {
                totalLiters += parseFloat(row.liters);
            }

            if (row.amount && !isNaN(parseFloat(row.amount))) {
                totalAmount += parseFloat(row.amount);
            }

            if (row.rate && !isNaN(parseFloat(row.rate))) {
                totalRate += parseFloat(row.rate);
                rateCount++;
            }
        });

        totalLitersSpan.textContent = totalLiters.toFixed(2);
        totalAmountSpan.textContent = `Rs ${totalAmount.toFixed(2)}`;
        avgRateSpan.textContent = rateCount > 0 ? `Rs ${(totalRate / rateCount).toFixed(2)}` : 'Rs 0.00';
    }

    // Render worksheet rows for current page
    function renderWorksheet() {
        worksheetBody.innerHTML = '';

        // Calculate start and end index for current page
        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = Math.min(startIndex + rowsPerPage, worksheetData.length);

        // Render rows for current page
        for (let i = startIndex; i < endIndex; i++) {
            const row = worksheetData[i];
            const rowElement = document.createElement('tr');

            // Set page number for this row
            row.page = currentPage;

            rowElement.innerHTML = `
                <td>
                    <input type="date" class="input-field" data-id="${row.id}" data-field="date" value="${row.date}">
                </td>
                <td>
                    <input type="number" class="input-field" data-id="${row.id}" data-field="openingMeter" value="${row.openingMeter}" min="0" step="0.01" placeholder="0.00">
                </td>
                <td>
                    <input type="number" class="input-field" data-id="${row.id}" data-field="closingMeter" value="${row.closingMeter}" min="0" step="0.01" placeholder="0.00">
                </td>
                <td>
                    <input type="text" class="input-field" data-id="${row.id}" data-field="liters" value="${row.liters}" readonly>
                </td>
                <td>
                    <input type="number" class="input-field" data-id="${row.id}" data-field="rate" value="${row.rate}" min="0" step="0.01" placeholder="0.00">
                </td>
                <td>
                    <input type="text" class="input-field" data-id="${row.id}" data-field="amount" value="${row.amount}" readonly>
                </td>
                <td>
                    <button class="btn btn-ghost delete-row" data-id="${row.id}" style="padding: 8px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

            worksheetBody.appendChild(rowElement);
        }

        // Add event listeners to inputs
        document.querySelectorAll('.input-field').forEach(input => {
            input.addEventListener('input', handleInputChange);
        });

        // Add event listeners to delete buttons
        document.querySelectorAll('.delete-row').forEach(button => {
            button.addEventListener('click', handleDeleteRow);
        });

        updatePagination();
    }

    // Handle input changes
    function handleInputChange(e) {
        const field = e.target.dataset.field;
        const id = parseInt(e.target.dataset.id);
        const value = e.target.value;

        // Find the row in worksheetData
        const rowIndex = worksheetData.findIndex(row => row.id === id);
        if (rowIndex === -1) return;

        // Update the row data
        worksheetData[rowIndex][field] = value;

        // Auto-calculate liters if opening or closing meter changed
        if (field === 'openingMeter' || field === 'closingMeter') {
            const opening = worksheetData[rowIndex].openingMeter;
            const closing = worksheetData[rowIndex].closingMeter;

            if (opening && closing) {
                const liters = calculateLiters(opening, closing);
                worksheetData[rowIndex].liters = liters;

                // Update the liters input field
                const litersInput = document.querySelector(`.input-field[data-id="${id}"][data-field="liters"]`);
                if (litersInput) litersInput.value = liters;

                // If rate exists, calculate amount
                if (worksheetData[rowIndex].rate) {
                    const amount = calculateAmount(liters, worksheetData[rowIndex].rate);
                    worksheetData[rowIndex].amount = amount;

                    // Update the amount input field
                    const amountInput = document.querySelector(`.input-field[data-id="${id}"][data-field="amount"]`);
                    if (amountInput) amountInput.value = amount;
                }
            }
        }

        // Auto-calculate amount if liters or rate changed
        if (field === 'liters' || field === 'rate') {
            const liters = field === 'liters' ? value : worksheetData[rowIndex].liters;
            const rate = field === 'rate' ? value : worksheetData[rowIndex].rate;

            if (liters && rate) {
                const amount = calculateAmount(liters, rate);
                worksheetData[rowIndex].amount = amount;

                // Update the amount input field
                const amountInput = document.querySelector(`.input-field[data-id="${id}"][data-field="amount"]`);
                if (amountInput) amountInput.value = amount;
            }
        }

        updateSummary();
    }

    // Handle delete row
    async function handleDeleteRow(e) {
        const id = parseInt(e.target.closest('.delete-row').dataset.id);
        const rowIndex = worksheetData.findIndex(row => row.id === id);

        if (rowIndex !== -1) {
            const row = worksheetData[rowIndex];
            
            // Check if record exists in database
            if (row.date) {
                try {
                    const checkResponse = await fetch('../../../../server/api/sale/meter_invoice/check-invoice-exists.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            date: row.date,
                            branch_id: window.HARDCODED_BRANCH_ID,
                            station_id: window.HARDCODED_STATION_ID,
                            product_id: window.HARDCODED_PRODUCT_ID,
                            unit_id: window.HARDCODED_UNIT_ID
                        })
                    });
                    const checkResult = await checkResponse.json();
                    
                    if (checkResult.exists) {
                        if (!confirm('Delete this record from database?')) return;
                        
                        const response = await fetch('../../../../server/api/sale/meter_invoice/invoice-delete.php', {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ invoice_id: checkResult.invoice_id })
                        });
                        const result = await response.json();
                        if (!result.success) {
                            alert('Failed to delete: ' + result.message);
                            return;
                        }
                    }
                } catch (error) {
                    alert('Error deleting record');
                    return;
                }
            }
            
            worksheetData.splice(rowIndex, 1);

            // Re-index IDs to maintain sequence
            worksheetData.forEach((row, index) => {
                row.id = index + 1;
            });

            // If current page becomes empty, go to previous page
            const startIndex = (currentPage - 1) * rowsPerPage;
            if (startIndex >= worksheetData.length && currentPage > 1) {
                currentPage--;
            }

            renderWorksheet();
            updateSummary();
        }
    }

    // Add new row
    addRowBtn.addEventListener('click', function () {
        // Check if we need a new page
        if (worksheetData.length % rowsPerPage === 0) {
            currentPage = Math.ceil(worksheetData.length / rowsPerPage) + 1;
        }

        const newId = worksheetData.length > 0 ? worksheetData[worksheetData.length - 1].id + 1 : 1;
        worksheetData.push({
            id: newId,
            date: '',
            openingMeter: '',
            closingMeter: '',
            liters: '',
            rate: window.DEFAULT_RATE || '',
            amount: '',
            page: currentPage
        });

        renderWorksheet();
        updateSummary();
    });

    // Clear all rows
    clearBtn.addEventListener('click', function () {
        if (confirm('Are you sure you want to clear all data? This cannot be undone.')) {
            worksheetData = [];

            // Add one empty row
            worksheetData.push({
                id: 1,
                date: getCurrentDate(),
                openingMeter: '',
                closingMeter: '',
                liters: '',
                rate: '',
                amount: '',
                page: 1
            });

            currentPage = 1;
            renderWorksheet();
            updateSummary();
        }
    });

    // Save worksheet
    saveBtn.addEventListener('click', async function () {
        // Filter out empty rows and rows already saved to database
        const nonEmptyRows = worksheetData.filter(row =>
            row.date && row.openingMeter && row.closingMeter && row.rate && !row.dbId
        );

        if (nonEmptyRows.length === 0) {
            alert('No new data to save.');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        let successCount = 0;
        let errorCount = 0;

        for (const row of nonEmptyRows) {
            try {
                // Check if invoice already exists
                const checkResponse = await fetch('../../../../server/api/sale/meter_invoice/check-invoice-exists.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        date: row.date,
                        branch_id: window.HARDCODED_BRANCH_ID,
                        station_id: window.HARDCODED_STATION_ID,
                        product_id: window.HARDCODED_PRODUCT_ID,
                        unit_id: window.HARDCODED_UNIT_ID
                    })
                });

                const checkResult = await checkResponse.json();
                if (checkResult.exists) {
                    // Update existing invoice
                    const editResponse = await fetch('../../../../server/api/sale/meter_invoice/invoice-edit.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            invoice_id: checkResult.invoice_id,
                            date: row.date,
                            opening_reading: row.openingMeter,
                            rate: row.rate,
                            closing_reading: row.closingMeter
                        })
                    });
                    const editResult = await editResponse.json();
                    if (editResult.success) {
                        successCount++;
                    } else {
                        errorCount++;
                    }
                    continue;
                }

                // Save opening reading
                const addResponse = await fetch('../../../../server/api/sale/meter_invoice/invoice-add.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        date: row.date,
                        branch_id: window.HARDCODED_BRANCH_ID,
                        station_id: window.HARDCODED_STATION_ID,
                        product_id: window.HARDCODED_PRODUCT_ID,
                        unit_id: window.HARDCODED_UNIT_ID,
                        opening_reading: row.openingMeter
                    })
                });

                const addResult = await addResponse.json();
                if (!addResult.success || !addResult.invoice_id) {
                    errorCount++;
                    continue;
                }

                // Complete invoice with closing reading
                const completeResponse = await fetch('../../../../server/api/sale/meter_invoice/invoice-complete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        invoice_id: addResult.invoice_id,
                        rate: row.rate,
                        closing_reading: row.closingMeter
                    })
                });

                const completeResult = await completeResponse.json();
                if (completeResult.success) {
                    successCount++;
                    row.dbId = addResult.invoice_id;
                } else {
                    errorCount++;
                }
            } catch (error) {
                errorCount++;
            }
        }

        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Worksheet';

        alert(`Worksheet saved! Success: ${successCount}, Errors: ${errorCount}`);
        
        if (successCount > 0) {
            // Clear worksheet after successful save
            worksheetData = [{
                id: 1,
                date: getCurrentDate(),
                openingMeter: '',
                closingMeter: '',
                liters: '',
                rate: '',
                amount: '',
                page: 1
            }];
            currentPage = 1;
            renderWorksheet();
            updateSummary();
        }
    });



    // Fetch worksheet data
    async function fetchWorksheetData() {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-worksheet-data.php?product_id=${window.HARDCODED_PRODUCT_ID}&branch_id=${window.HARDCODED_BRANCH_ID}&unit_id=${window.HARDCODED_UNIT_ID}&station_id=${window.HARDCODED_STATION_ID}`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                worksheetData = result.data.map((row, index) => {
                    const liters = row.closing_reading ? (parseFloat(row.closing_reading) - parseFloat(row.opening_reading)).toFixed(2) : '';
                    const amount = liters && row.rate ? (parseFloat(liters) * parseFloat(row.rate)).toFixed(2) : '';
                    
                    return {
                        id: index + 1,
                        dbId: row.id,
                        date: row.usage_date,
                        openingMeter: row.opening_reading || '',
                        closingMeter: row.closing_reading || '',
                        liters: liters,
                        rate: row.rate || '',
                        amount: amount,
                        page: Math.floor(index / rowsPerPage) + 1
                    };
                });
            }
            
            // Fetch product rate if no data exists
            if (worksheetData.length === 0) {
                await fetchProductRate();
            }
        } catch (error) {
            console.error('Error fetching worksheet data:', error);
        }
    }
    
    async function fetchProductRate() {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-products.php`);
            const result = await response.json();
            if (result.success) {
                const product = result.data.find(p => p.id == window.HARDCODED_PRODUCT_ID);
                if (product && product.mrp) {
                    window.DEFAULT_RATE = product.mrp;
                }
            }
        } catch (error) {
            console.error('Error fetching product rate:', error);
        }
    }
    
    // Override handleInputChange to auto-populate rate
    const originalHandleInputChange = handleInputChange;
    function handleInputChangeWithRate(e) {
        const field = e.target.dataset.field;
        const id = parseInt(e.target.dataset.id);
        
        // Auto-populate rate if empty and default rate exists
        if (field === 'closingMeter' && window.DEFAULT_RATE) {
            const rowIndex = worksheetData.findIndex(row => row.id === id);
            if (rowIndex !== -1 && !worksheetData[rowIndex].rate) {
                worksheetData[rowIndex].rate = window.DEFAULT_RATE;
                const rateInput = document.querySelector(`.input-field[data-id="${id}"][data-field="rate"]`);
                if (rateInput) rateInput.value = window.DEFAULT_RATE;
            }
        }
        
        return originalHandleInputChange.call(this, e);
    }
    
    // Replace handleInputChange reference
    document.querySelectorAll('.input-field').forEach(input => {
        input.removeEventListener('input', handleInputChange);
        input.addEventListener('input', handleInputChangeWithRate);
    });
    
    // Fetch worksheet data
    async function fetchWorksheetData_old() {
        try {
            const response = await fetch(`../../../../server/api/sale/meter_invoice/get-worksheet-data.php?product_id=${window.HARDCODED_PRODUCT_ID}&branch_id=${window.HARDCODED_BRANCH_ID}&unit_id=${window.HARDCODED_UNIT_ID}&station_id=${window.HARDCODED_STATION_ID}`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                worksheetData = result.data.map((row, index) => {
                    const liters = row.closing_reading ? (parseFloat(row.closing_reading) - parseFloat(row.opening_reading)).toFixed(2) : '';
                    const amount = liters && row.rate ? (parseFloat(liters) * parseFloat(row.rate)).toFixed(2) : '';
                    
                    return {
                        id: index + 1,
                        dbId: row.id,
                        date: row.usage_date,
                        openingMeter: row.opening_reading || '',
                        closingMeter: row.closing_reading || '',
                        liters: liters,
                        rate: row.rate || '',
                        amount: amount,
                        page: Math.floor(index / rowsPerPage) + 1
                    };
                });
            }
        } catch (error) {
            console.error('Error fetching worksheet data:', error);
        }
    }

    // Pagination controls
    prevPageBtn.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderWorksheet();
        }
    });

    nextPageBtn.addEventListener('click', function () {
        const totalPages = Math.ceil(worksheetData.length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderWorksheet();
        }
    });

    // Auto-calculate liters when opening and closing are both entered
    document.addEventListener('blur', function (e) {
        if (e.target.classList.contains('input-field')) {
            const field = e.target.dataset.field;
            const id = parseInt(e.target.dataset.id);

            if (field === 'openingMeter' || field === 'closingMeter') {
                const rowIndex = worksheetData.findIndex(row => row.id === id);
                if (rowIndex === -1) return;

                const opening = worksheetData[rowIndex].openingMeter;
                const closing = worksheetData[rowIndex].closingMeter;

                if (opening && closing) {
                    const liters = calculateLiters(opening, closing);
                    worksheetData[rowIndex].liters = liters;

                    // Update the liters input field
                    const litersInput = document.querySelector(`.input-field[data-id="${id}"][data-field="liters"]`);
                    if (litersInput) {
                        litersInput.value = liters;

                        // If liters is ERROR, add error class
                        if (liters === 'ERROR') {
                            litersInput.classList.add('error');
                        } else {
                            litersInput.classList.remove('error');
                        }
                    }

                    // If rate exists, calculate amount
                    if (worksheetData[rowIndex].rate) {
                        const amount = calculateAmount(liters, worksheetData[rowIndex].rate);
                        worksheetData[rowIndex].amount = amount;

                        // Update the amount input field
                        const amountInput = document.querySelector(`.input-field[data-id="${id}"][data-field="amount"]`);
                        if (amountInput) amountInput.value = amount;
                    }

                    updateSummary();
                }
            }
        }
    }, true);
});