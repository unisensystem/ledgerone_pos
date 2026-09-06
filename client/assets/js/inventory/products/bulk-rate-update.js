const addedProducts = {};
let rowCounter = 0;

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('addRowBtn').addEventListener('click', addRow);
    document.getElementById('bulkRateForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveRates();
    });
    addRow(); // one empty row by default
});

function addRow() {
    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();

    rowCounter++;
    const tbody = document.getElementById('rateTableBody');

    const tr = document.createElement('tr');
    tr.dataset.rowId = rowCounter;

    tr.innerHTML = `
        <td style="color:#999; text-align:center;" class="row-num">${tbody.querySelectorAll('tr').length + 1}</td>
        <td>
            <div class="custom-dropdown">
                <input type="text" class="product-search" placeholder="Search product..." autocomplete="off">
                <input type="hidden" class="product-id">
                <div class="dropdown-list"></div>
            </div>
            <div class="product-code product-info" style="display:none;"></div>
        </td>
        <td><input type="number" class="rate-input" data-field="purchase_price"  step="0.001" min="0" placeholder="0.000"></td>
        <td><input type="number" class="rate-input" data-field="trade_price"     step="0.001" min="0" placeholder="0.000"></td>
        <td><input type="number" class="rate-input" data-field="wholesale_price" step="0.001" min="0" placeholder="0.000"></td>
        <td><input type="number" class="rate-input" data-field="mrp"             step="0.001" min="0" placeholder="0.000"></td>
        <td style="text-align:center;">
            <button type="button" class="remove-btn">Remove</button>
        </td>
    `;

    initRowControls(tr);
    tbody.appendChild(tr);
    reNumberRows();
}

function initRowControls(tr) {
    const searchInput    = tr.querySelector('.product-search');
    const productIdInput = tr.querySelector('.product-id');
    const dropdownList   = tr.querySelector('.dropdown-list');
    const productInfo    = tr.querySelector('.product-info');
    let debounce;

    searchInput.addEventListener('click', function () {
        searchProducts(this.value.trim(), dropdownList, tr);
    });

    searchInput.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(debounce);
        if (q.length === 0) clearRow(tr);
        debounce = setTimeout(() => searchProducts(q, dropdownList, tr), 280);
    });

    // keyboard navigation: ArrowUp/ArrowDown to move, Enter to select + focus next field, Escape to close
    searchInput.addEventListener('keydown', function (e) {
        if (dropdownList.style.display !== 'block') {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                searchProducts(this.value.trim(), dropdownList, tr);
            }
            return;
        }

        const items = getSelectableItems(dropdownList);
        let idx = parseInt(dropdownList.dataset.activeIndex, 10);
        if (isNaN(idx)) idx = -1;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = idx < items.length - 1 ? idx + 1 : 0;
            setActiveItem(dropdownList, items, idx);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = idx > 0 ? idx - 1 : items.length - 1;
            setActiveItem(dropdownList, items, idx);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const target = items[idx] || items[0];
            if (target) {
                target.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
                dropdownList.style.display = 'none';
                const nextInput = tr.querySelector('input[data-field="purchase_price"]');
                if (nextInput) nextInput.focus();
            }
        } else if (e.key === 'Escape') {
            dropdownList.style.display = 'none';
        }
    });

    document.addEventListener('click', function (e) {
        if (!tr.contains(e.target)) dropdownList.style.display = 'none';
    });

    // mark rate inputs as changed
    tr.querySelectorAll('.rate-input').forEach(input => {
        input.addEventListener('input', function () {
            this.classList.add('changed');
        });
    });

    tr.querySelector('.remove-btn').addEventListener('click', function () {
        const pid = productIdInput.value;
        if (pid) delete addedProducts[pid];
        tr.remove();
        reNumberRows();
        if (document.getElementById('rateTableBody').querySelectorAll('tr').length === 0) {
            showEmptyState();
        }
    });
}

function getSelectableItems(dropdownList) {
    return Array.from(dropdownList.querySelectorAll('.dropdown-item.selectable'));
}

function setActiveItem(dropdownList, items, idx) {
    items.forEach(i => i.classList.remove('active'));
    if (items[idx]) {
        items[idx].classList.add('active');
        items[idx].scrollIntoView({ block: 'nearest' });
    }
    dropdownList.dataset.activeIndex = idx;
}

function searchProducts(q, dropdownList, tr) {
    fetch('../../../../server/api/inventory/products/search-products.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            dropdownList.innerHTML = '';
            dropdownList.dataset.activeIndex = -1;

            if (!data.success || !data.products || data.products.length === 0) {
                dropdownList.innerHTML = '<div class="dropdown-item" style="color:#aaa;cursor:default;">No products found</div>';
                dropdownList.style.display = 'block';
                return;
            }

            data.products.forEach(p => {
                const item = document.createElement('div');
                item.className = 'dropdown-item';

                if (addedProducts[p.id]) {
                    item.innerHTML = `<strong>${p.name}</strong> (${p.code}) <span style="color:#4caf50;font-size:11px;">✓ already added</span>`;
                    item.style.color = '#aaa';
                } else {
                    item.classList.add('selectable');
                    item.innerHTML = `<strong>${p.name}</strong> (${p.code})`;
                    item.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        selectProduct(tr, p);
                        dropdownList.style.display = 'none';
                    });
                    item.addEventListener('mouseenter', function () {
                        const items = getSelectableItems(dropdownList);
                        setActiveItem(dropdownList, items, items.indexOf(item));
                    });
                }

                dropdownList.appendChild(item);
            });

            dropdownList.style.display = 'block';
        })
        .catch(() => {
            dropdownList.innerHTML = '<div class="dropdown-item" style="color:#f44336;cursor:default;">Error loading products</div>';
            dropdownList.style.display = 'block';
        });
}

function selectProduct(tr, product) {
    const searchInput    = tr.querySelector('.product-search');
    const productIdInput = tr.querySelector('.product-id');
    const productInfo    = tr.querySelector('.product-info');

    const prevId = productIdInput.value;
    if (prevId) delete addedProducts[prevId];

    addedProducts[product.id] = true;
    searchInput.value    = product.name;
    productIdInput.value = product.id;
    productInfo.textContent = product.code;
    productInfo.style.display = 'block';

    fetch('../../../../server/api/inventory/products/product-get.php?id=' + product.id)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.product) return;
            const p = data.product;
            setRate(tr, 'purchase_price',  p.purchase_price);
            setRate(tr, 'trade_price',     p.trade_price);
            setRate(tr, 'wholesale_price', p.wholesale_price);
            setRate(tr, 'mrp',             p.mrp);
        });
}

function setRate(tr, field, value) {
    const input = tr.querySelector(`input[data-field="${field}"]`);
    if (input && value != null && value !== '') {
        input.value = parseFloat(value).toFixed(3);
    }
}

function clearRow(tr) {
    const pid = tr.querySelector('.product-id').value;
    if (pid) delete addedProducts[pid];
    tr.querySelector('.product-id').value = '';
    tr.querySelector('.product-info').style.display = 'none';
    tr.querySelectorAll('.rate-input').forEach(i => { i.value = ''; i.classList.remove('changed'); });
}

function reNumberRows() {
    document.querySelectorAll('#rateTableBody tr').forEach((tr, i) => {
        const cell = tr.querySelector('.row-num');
        if (cell) cell.textContent = i + 1;
    });
}

function showEmptyState() {
    const tbody = document.getElementById('rateTableBody');
    const tr = document.createElement('tr');
    tr.id = 'emptyRow';
    tr.innerHTML = '<td colspan="7" class="empty-state">Click "Add Row" to search and add products</td>';
    tbody.appendChild(tr);
}

function saveRates() {
    const rows = document.querySelectorAll('#rateTableBody tr[data-row-id]');

    if (rows.length === 0) {
        showToast('Please add at least one product', 'error');
        return;
    }

    const products = [];
    let hasError = false;
    let rowIndex = 0;

    rows.forEach(tr => {
        rowIndex++;
        const id = tr.querySelector('.product-id').value;

        if (!id) {
            showToast(`Row ${rowIndex}: Please select a product`, 'error');
            hasError = true;
            return;
        }

        products.push({
            id,
            purchase_price:  parseFloat(tr.querySelector('input[data-field="purchase_price"]').value)  || 0,
            trade_price:     parseFloat(tr.querySelector('input[data-field="trade_price"]').value)     || 0,
            wholesale_price: parseFloat(tr.querySelector('input[data-field="wholesale_price"]').value) || 0,
            mrp:             parseFloat(tr.querySelector('input[data-field="mrp"]').value)             || 0,
        });
    });

    if (hasError) return;

    const saveBtn = document.getElementById('saveBtn');
    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving...';

    fetch('../../../../server/api/inventory/products/bulk-rate-update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ products })
    })
    .then(r => r.json())
    .then(data => {
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save Rates';

        if (data.success) {
            document.querySelectorAll('.rate-input.changed').forEach(i => i.classList.remove('changed'));
            showToast(data.message, 'success');
        } else {
            showToast(data.message || 'Error saving rates', 'error');
        }
    })
    .catch(() => {
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save Rates';
        showToast('Network error. Please try again.', 'error');
    });
}

function showToast(message, type) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.style.backgroundColor = type === 'error' ? '#f44336' : type === 'success' ? '#4caf50' : '#2196f3';
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}