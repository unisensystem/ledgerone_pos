let productsData = [];
let uomData = [];

document.addEventListener('DOMContentLoaded', function () {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('purchaseDate').value = today;
    
    const itemsTable = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const addRowBtn = document.getElementById('addRowBtn');
    const saveBtn = document.getElementById('saveBtn');
    const resetBtn = document.getElementById('resetBtn');
    const form = document.getElementById('invoiceForm');

    Promise.all([loadSuppliers(), loadBranches(), loadProducts(), loadUOM(), loadCurrencies(), loadCompanies()]).then(() => {
        initSearchableDropdown('supplierCodeSearch', 'supplierCodeOptions', 'supplierCode');
        initSearchableDropdown('branchSearch', 'branchOptions', 'branch');
        addRow();
    });

    addRowBtn.addEventListener('click', addRow);
    resetBtn.addEventListener('click', resetForm);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (validateForm()) {
            saveInvoice();
        }
    });

    async function loadSuppliers() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-suppliers.php');
            const data = await response.json();
            
            if (data.success) {
                const codeOptions = document.getElementById('supplierCodeOptions');
                codeOptions.innerHTML = '';
                
                data.suppliers.forEach(supplier => {
                    const codeOption = document.createElement('div');
                    codeOption.className = 'dropdown-option';
                    codeOption.setAttribute('data-value', supplier.id);
                    codeOption.textContent = `${supplier.supplier_code} - ${supplier.supplier_name}`;
                    codeOptions.appendChild(codeOption);
                });
            }
        } catch (error) {
            console.error('Error loading suppliers:', error);
        }
    }

    async function loadBranches() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-branches.php');
            const data = await response.json();
            
            if (data.success) {
                const branchOptions = document.getElementById('branchOptions');
                branchOptions.innerHTML = '';
                
                data.branches.forEach(branch => {
                    const option = document.createElement('div');
                    option.className = 'dropdown-option';
                    option.setAttribute('data-value', branch.id);
                    const displayText = branch.parent_branch_name 
                        ? `${branch.parent_branch_name} > ${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`
                        : `${branch.branch_code} - ${branch.branch_name} (${branch.branch_type})`;
                    option.textContent = displayText;
                    branchOptions.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading branches:', error);
        }
    }

    async function loadProducts() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-products-new.php');
            const data = await response.json();
            
            if (data.success) {
                productsData = data.products;
            }
        } catch (error) {
            console.error('Error loading products:', error);
        }
    }

    async function loadUOM() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-uom.php');
            const data = await response.json();
            
            if (data.success) {
                uomData = data.uoms;
            }
        } catch (error) {
            console.error('Error loading UOM:', error);
        }
    }

    async function loadCurrencies() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-currencies.php');
            const data = await response.json();
            
            if (data.success) {
                const currencySelect = document.getElementById('currency');
                currencySelect.innerHTML = '<option value="">Select Currency</option>';
                
                data.currencies.forEach(currency => {
                    const option = document.createElement('option');
                    option.value = currency.currency_id;
                    option.textContent = `${currency.code} - ${currency.name} (${currency.symbol})`;
                    option.setAttribute('data-symbol', currency.symbol);
                    
                    if (currency.is_base_currency == 1) {
                        option.selected = true;
                    }
                    
                    currencySelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading currencies:', error);
        }
    }

    async function loadCompanies() {
        try {
            const response = await fetch('../../../../server/api/purchase/purchase_invoice/get-companies.php');
            const data = await response.json();

            if (data.success) {
                const companySelect = document.getElementById('company');
                companySelect.innerHTML = '<option value="">Select Company</option>';

                data.companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = `${company.company_code} - ${company.company_name}`;
                    companySelect.appendChild(option);
                });

                if (data.companies.length === 1) {
                    companySelect.value = data.companies[0].id;
                }
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    function initSearchableDropdown(searchInputId, optionsContainerId, hiddenInputId) {
        const searchInput = document.getElementById(searchInputId);
        const optionsContainer = document.getElementById(optionsContainerId);
        const hiddenInput = document.getElementById(hiddenInputId);

        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
            optionsContainer.style.display = 'block';
            filterOptions();
        });

        searchInput.addEventListener('input', filterOptions);

        optionsContainer.addEventListener('click', function (e) {
            if (e.target.classList.contains('dropdown-option')) {
                const value = e.target.getAttribute('data-value');
                const displayText = e.target.textContent;

                searchInput.value = displayText;
                hiddenInput.value = value;
                optionsContainer.style.display = 'none';
            }
        });

        document.addEventListener('click', function () {
            optionsContainer.style.display = 'none';
        });

        function filterOptions() {
            const searchTerm = searchInput.value.toLowerCase();
            const options = optionsContainer.getElementsByClassName('dropdown-option');

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'block' : 'none';
            }
        }
    }

    function addRow() {
        const rowCount = itemsTable.rows.length;
        const row = itemsTable.insertRow();

        // S#
        const cell0 = row.insertCell(0);
        cell0.textContent = rowCount + 1;

        // Product Code / Name
        const cell1 = row.insertCell(1);
        const codeContainer = document.createElement('div');
        codeContainer.className = 'searchable-dropdown';
        const codeOptionsHtml = productsData.map(product => {
            return `<div class="dropdown-option" data-value="${product.id}" data-mrp="${product.mrp}" data-trade-price="${product.trade_price}" data-unit="${product.default_unit_id}" data-sales-tax="${product.sales_tax || 0}">${product.code} - ${product.name}</div>`;
        }).join('');
        codeContainer.innerHTML = `
            <input type="text" class="search-input table-input" placeholder="Search product...">
            <input type="hidden" class="item-code" required>
        `;
        
        const dropdownOptions = document.createElement('div');
        dropdownOptions.className = 'dropdown-options';
        dropdownOptions.innerHTML = codeOptionsHtml;
        dropdownOptions.style.position = 'absolute';
        dropdownOptions.style.display = 'none';
        dropdownOptions.style.zIndex = '9999';
        document.body.appendChild(dropdownOptions);
        
        codeContainer.querySelector('.search-input').dropdownOptions = dropdownOptions;
        cell1.appendChild(codeContainer);
        initTableDropdown(codeContainer);

        // Unit
        const cell2 = row.insertCell(2);
        const unitSelect = document.createElement('select');
        unitSelect.className = 'table-input';
        const uomOptionsHtml = uomData.map(uom => 
            `<option value="${uom.id}">${uom.uom_name}</option>`
        ).join('');
        unitSelect.innerHTML = `<option value="">Select Unit</option>${uomOptionsHtml}`;
        unitSelect.required = true;
        cell2.appendChild(unitSelect);

        // Qty
        const cell3 = row.insertCell(3);
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'table-input';
        qtyInput.min = '0';
        qtyInput.step = '0.01';
        qtyInput.required = true;
        qtyInput.addEventListener('input', () => calculateRow(row));
        cell3.appendChild(qtyInput);

        // RP Unit Price (readonly)
        const cell4 = row.insertCell(4);
        const rpUnitPrice = document.createElement('input');
        rpUnitPrice.type = 'text';
        rpUnitPrice.className = 'table-input';
        rpUnitPrice.readOnly = true;
        rpUnitPrice.value = '0.00';
        cell4.appendChild(rpUnitPrice);

        // TP Unit Price (readonly)
        const cell5 = row.insertCell(5);
        const tpUnitPrice = document.createElement('input');
        tpUnitPrice.type = 'text';
        tpUnitPrice.className = 'table-input';
        tpUnitPrice.readOnly = true;
        tpUnitPrice.value = '0.00';
        cell5.appendChild(tpUnitPrice);

        // RP Total Value (readonly)
        const cell6 = row.insertCell(6);
        const rpTotalValue = document.createElement('input');
        rpTotalValue.type = 'text';
        rpTotalValue.className = 'table-input';
        rpTotalValue.readOnly = true;
        rpTotalValue.value = '0.00';
        cell6.appendChild(rpTotalValue);

        // TP Total Value (readonly)
        const cell7 = row.insertCell(7);
        const tpTotalValue = document.createElement('input');
        tpTotalValue.type = 'text';
        tpTotalValue.className = 'table-input';
        tpTotalValue.readOnly = true;
        tpTotalValue.value = '0.00';
        cell7.appendChild(tpTotalValue);

        // Disc %
        const cell8 = row.insertCell(8);
        const discountPercent = document.createElement('input');
        discountPercent.type = 'number';
        discountPercent.className = 'table-input';
        discountPercent.min = '0';
        discountPercent.max = '100';
        discountPercent.step = '0.01';
        discountPercent.value = '0';
        discountPercent.addEventListener('input', () => calculateRow(row));
        cell8.appendChild(discountPercent);

        // Discount Amount (readonly)
        const cell9 = row.insertCell(9);
        const discountAmount = document.createElement('input');
        discountAmount.type = 'text';
        discountAmount.className = 'table-input';
        discountAmount.readOnly = true;
        discountAmount.value = '0.00';
        cell9.appendChild(discountAmount);

        // Sales Tax (readonly)
        const cell10 = row.insertCell(10);
        const salesTax = document.createElement('input');
        salesTax.type = 'text';
        salesTax.className = 'table-input';
        salesTax.readOnly = true;
        salesTax.value = '0.00';
        cell10.appendChild(salesTax);

        // TP Amount (readonly)
        const cell11 = row.insertCell(11);
        const tpAmount = document.createElement('input');
        tpAmount.type = 'text';
        tpAmount.className = 'table-input';
        tpAmount.readOnly = true;
        tpAmount.value = '0.00';
        cell11.appendChild(tpAmount);

        // Net Amount (readonly)
        const cell12 = row.insertCell(12);
        const netAmount = document.createElement('input');
        netAmount.type = 'text';
        netAmount.className = 'table-input';
        netAmount.readOnly = true;
        netAmount.value = '0.00';
        cell12.appendChild(netAmount);

        // Actions
        const cell13 = row.insertCell(13);
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-sm';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.addEventListener('click', () => deleteRow(row));
        cell13.appendChild(deleteBtn);
    }

    function initTableDropdown(container) {
        const searchInput = container.querySelector('.search-input');
        const hiddenInput = container.querySelector('.item-code');
        const dropdownOptions = searchInput.dropdownOptions;

        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdownOptions.style.display = 'block';
            positionDropdown(searchInput, dropdownOptions);
            filterTableOptions();
        });

        searchInput.addEventListener('input', filterTableOptions);

        dropdownOptions.addEventListener('click', function (e) {
            if (e.target.classList.contains('dropdown-option')) {
                const value = e.target.getAttribute('data-value');
                const displayText = e.target.textContent;
                const mrp = parseFloat(e.target.getAttribute('data-mrp')) || 0;
                const tradePrice = parseFloat(e.target.getAttribute('data-trade-price')) || 0;
                const unitId = e.target.getAttribute('data-unit');
                const salesTax = parseFloat(e.target.getAttribute('data-sales-tax')) || 0;

                searchInput.value = displayText;
                hiddenInput.value = value;

                const row = searchInput.closest('tr');
                row.cells[4].querySelector('input').value = mrp.toFixed(2);
                row.cells[5].querySelector('input').value = tradePrice.toFixed(2);
                row.cells[2].querySelector('select').value = unitId;
                row.dataset.salesTax = salesTax;

                calculateRow(row);
                dropdownOptions.style.display = 'none';
            }
        });

        document.addEventListener('click', function () {
            dropdownOptions.style.display = 'none';
        });

        function filterTableOptions() {
            const searchTerm = searchInput.value.toLowerCase();
            const options = dropdownOptions.getElementsByClassName('dropdown-option');

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'block' : 'none';
            }
        }

        function positionDropdown(input, dropdown) {
            const rect = input.getBoundingClientRect();
            dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
        }
    }

    function calculateRow(row) {
        const qty = parseFloat(row.cells[3].querySelector('input').value) || 0;
        const rpUnitPrice = parseFloat(row.cells[4].querySelector('input').value) || 0;
        const tpUnitPrice = parseFloat(row.cells[5].querySelector('input').value) || 0;
        const discountPercent = parseFloat(row.cells[8].querySelector('input').value) || 0;
        const salesTaxRate = parseFloat(row.dataset.salesTax) || 0;

        // Qty * RP Unit Price = RP Total Value
        const rpTotalValue = qty * rpUnitPrice;
        row.cells[6].querySelector('input').value = rpTotalValue.toFixed(2);

        // Qty * TP Unit Price = TP Total Value
        const tpTotalValue = qty * tpUnitPrice;
        row.cells[7].querySelector('input').value = tpTotalValue.toFixed(2);

        // Discount Amount = (Discount % / 100) * TP Total Value
        const discountAmount = (discountPercent / 100) * tpTotalValue;
        row.cells[9].querySelector('input').value = discountAmount.toFixed(2);

        // Sales Tax = (sales_tax / 100) × RP Total Value
        const salesTax = (salesTaxRate / 100) * rpTotalValue;
        row.cells[10].querySelector('input').value = salesTax.toFixed(2);

        // TP Amount = TP Total Value + Sales Tax
        const tpAmount = tpTotalValue + salesTax;
        row.cells[11].querySelector('input').value = tpAmount.toFixed(2);

        // Net Amount = TP Amount - Discount Amount
        const netAmount = tpAmount - discountAmount;
        row.cells[12].querySelector('input').value = netAmount.toFixed(2);

        updateTotals();
    }

    function updateTotals() {
        let totalQty = 0, totalRPUnitPrice = 0, totalTPUnitPrice = 0, totalRPValue = 0, totalTPValue = 0;
        let totalDiscountAmount = 0, totalSalesTax = 0, totalTPAmount = 0, totalNetAmount = 0;

        for (let i = 0; i < itemsTable.rows.length; i++) {
            const row = itemsTable.rows[i];
            totalQty += parseFloat(row.cells[3].querySelector('input').value) || 0;
            totalRPUnitPrice += parseFloat(row.cells[4].querySelector('input').value) || 0;
            totalTPUnitPrice += parseFloat(row.cells[5].querySelector('input').value) || 0;
            totalRPValue += parseFloat(row.cells[6].querySelector('input').value) || 0;
            totalTPValue += parseFloat(row.cells[7].querySelector('input').value) || 0;
            totalDiscountAmount += parseFloat(row.cells[9].querySelector('input').value) || 0;
            totalSalesTax += parseFloat(row.cells[10].querySelector('input').value) || 0;
            totalTPAmount += parseFloat(row.cells[11].querySelector('input').value) || 0;
            totalNetAmount += parseFloat(row.cells[12].querySelector('input').value) || 0;
        }

        document.getElementById('totalQty').textContent = totalQty.toFixed(2);
        document.getElementById('totalRPUnitPrice').textContent = totalRPUnitPrice.toFixed(2);
        document.getElementById('totalTPUnitPrice').textContent = totalTPUnitPrice.toFixed(2);
        document.getElementById('totalRPValue').textContent = totalRPValue.toFixed(2);
        document.getElementById('totalTPValue').textContent = totalTPValue.toFixed(2);
        document.getElementById('totalDiscountAmount').textContent = totalDiscountAmount.toFixed(2);
        document.getElementById('totalSalesTax').textContent = totalSalesTax.toFixed(2);
        document.getElementById('totalTPAmount').textContent = totalTPAmount.toFixed(2);
        document.getElementById('totalNetAmount').textContent = totalNetAmount.toFixed(2);

        document.getElementById('summaryTotalAmount').textContent = totalTPValue.toFixed(2);
        document.getElementById('summaryTotalTax').textContent = totalSalesTax.toFixed(2);
        document.getElementById('summaryNetAmount').textContent = totalNetAmount.toFixed(2);
    }

    function deleteRow(row) {
        if (row.dropdownOptions) {
            row.dropdownOptions.remove();
        }
        row.remove();
        updateRowNumbers();
        updateTotals();
    }

    function updateRowNumbers() {
        for (let i = 0; i < itemsTable.rows.length; i++) {
            itemsTable.rows[i].cells[0].textContent = i + 1;
        }
    }

    function resetForm() {
        if (confirm('Are you sure you want to reset the form? All data will be lost.')) {
            location.reload();
        }
    }

    function validateForm() {
        let isValid = true;

        if (!document.getElementById('company').value) {
            alert('Please select a company');
            isValid = false;
        }

        if (!document.getElementById('supplierCode').value) {
            alert('Please select a supplier');
            isValid = false;
        }

        if (!document.getElementById('branch').value) {
            alert('Please select a branch');
            isValid = false;
        }

        if (!document.getElementById('currency').value) {
            alert('Please select a currency');
            isValid = false;
        }

        if (itemsTable.rows.length === 0) {
            alert('Please add at least one item');
            isValid = false;
        }

        return isValid;
    }

    function saveInvoice() {
        const items = [];
        
        for (let i = 0; i < itemsTable.rows.length; i++) {
            const row = itemsTable.rows[i];
            items.push({
                productId: row.cells[1].querySelector('.item-code').value,
                uomId: row.cells[2].querySelector('select').value,
                quantity: parseFloat(row.cells[3].querySelector('input').value),
                rpUnitPrice: parseFloat(row.cells[4].querySelector('input').value),
                tpUnitPrice: parseFloat(row.cells[5].querySelector('input').value),
                rpTotalValue: parseFloat(row.cells[6].querySelector('input').value),
                tpTotalValue: parseFloat(row.cells[7].querySelector('input').value),
                discountPercent: parseFloat(row.cells[8].querySelector('input').value),
                discountAmount: parseFloat(row.cells[9].querySelector('input').value),
                salesTax: parseFloat(row.cells[10].querySelector('input').value),
                tpAmount: parseFloat(row.cells[11].querySelector('input').value),
                netAmount: parseFloat(row.cells[12].querySelector('input').value)
            });
        }

        const invoiceData = {
            purchaseDate: document.getElementById('purchaseDate').value,
            supplierInvoiceNo: document.getElementById('supplierInvoiceNo').value,
            companyId: document.getElementById('company').value,
            supplierId: document.getElementById('supplierCode').value,
            branchId: document.getElementById('branch').value,
            currencyId: document.getElementById('currency').value,
            remarks: document.getElementById('remarks').value,
            items: items,
            totalAmount: parseFloat(document.getElementById('summaryTotalAmount').textContent),
            totalTax: parseFloat(document.getElementById('summaryTotalTax').textContent),
            netAmount: parseFloat(document.getElementById('summaryNetAmount').textContent)
        };

        fetch('../../../../server/api/purchase/purchase_invoice/purchase-add-new.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(invoiceData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Invoice saved successfully!');
                window.location.href = 'purchase-list.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving invoice');
        });
    }
});