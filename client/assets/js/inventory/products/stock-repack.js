const REPACK_API = '../../../../server/api/inventory/products/';

let repackProducts = [];
let repackInfo = null;

window.addEventListener('load', function () {
    loadRepackProducts();
    initRepackProductDropdown();

    document.getElementById('qtyBags').addEventListener('input', updateRepackPreview);
    document.getElementById('resetBtn').addEventListener('click', resetRepackForm);
    document.getElementById('repackForm').addEventListener('submit', handleRepackSubmit);

    // Prevent form submission on Enter key (same as product form)
    document.getElementById('repackForm').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    });
});

function loadRepackProducts() {
    fetch(REPACK_API + 'stock-repack-list.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && Array.isArray(data.products)) {
            repackProducts = data.products;
        }
    })
    .catch(error => console.error('Error loading products:', error));
}

function initRepackProductDropdown() {
    const searchInput = document.getElementById('productSearch');
    const hiddenInput = document.getElementById('productId');
    const dropdown = document.getElementById('productDropdown');

    function render(query) {
        dropdown.innerHTML = '';
        const q = query.toLowerCase();
        const filtered = repackProducts.filter(p =>
            q === '' ||
            p.name.toLowerCase().includes(q) ||
            (p.code && p.code.toLowerCase().includes(q)) ||
            (p.parent_name && p.parent_name.toLowerCase().includes(q))
        );

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'dropdown-item';
            empty.style.color = 'var(--subtext)';
            empty.style.cursor = 'default';
            empty.textContent = repackProducts.length === 0 ? 'No child products available' : 'No products found';
            dropdown.appendChild(empty);
            return;
        }

        filtered.forEach(product => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = `${product.name} — ${product.unit_name || ''} (Parent: ${product.parent_name})`;
            item.dataset.id = product.id;
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                searchInput.value = product.name;
                hiddenInput.value = product.id;
                dropdown.style.display = 'none';
                fetchRepackInfo();
            });
            dropdown.appendChild(item);
        });
    }

    searchInput.addEventListener('focus', () => {
        render(searchInput.value.trim());
        dropdown.style.display = 'block';
    });

    searchInput.addEventListener('input', () => {
        // Typing again invalidates the earlier selection
        hiddenInput.value = '';
        clearRepackInfo();
        render(searchInput.value.trim());
        dropdown.style.display = 'block';
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function clearRepackInfo() {
    repackInfo = null;
    document.getElementById('repackInfo').style.display = 'none';
    document.getElementById('repackPreview').style.display = 'none';
    const qty = document.getElementById('qtyBags');
    qty.value = '';
    qty.disabled = true;
    document.getElementById('qtyLabel').textContent = 'Bags to Add';
    document.getElementById('qtyHelper').textContent = 'Select a product first';
    document.getElementById('submitBtn').disabled = true;
}

function fetchRepackInfo() {
    const productId = document.getElementById('productId').value;

    if (!productId) {
        clearRepackInfo();
        return;
    }

    fetch(`${REPACK_API}stock-repack-info.php?product_id=${encodeURIComponent(productId)}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            repackInfo = data.data;
            showRepackInfo();
        } else {
            clearRepackInfo();
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        clearRepackInfo();
        alert('An error occurred while loading product stock');
    });
}

function formatRepackNumber(value) {
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 3 });
}

function showRepackInfo() {
    const info = repackInfo;
    const packName = info.child.unit_name || 'bag';

    document.getElementById('infoParent').textContent = info.parent.name;
    document.getElementById('infoPackSize').textContent = `${formatRepackNumber(info.kg_per_bag)} kg (${packName})`;
    document.getElementById('infoParentKg').textContent = `${formatRepackNumber(info.parent_available_kg)} kg`;
    document.getElementById('infoChildBags').textContent = formatRepackNumber(info.child_bags);
    document.getElementById('infoMaxBags').textContent =
        `${info.max_bags} (limit ${info.max_kg} kg per entry)`;
    document.getElementById('repackInfo').style.display = 'grid';

    const qty = document.getElementById('qtyBags');
    qty.disabled = false;
    qty.max = info.max_bags;
    qty.value = '';
    document.getElementById('qtyLabel').textContent = `Bags to Add (${packName})`;
    document.getElementById('qtyHelper').textContent =
        `Each bag = ${formatRepackNumber(info.kg_per_bag)} kg, taken from ${info.parent.name}. Max ${info.max_kg} kg per entry.`;
    document.getElementById('repackPreview').style.display = 'none';
    document.getElementById('submitBtn').disabled = true;
    qty.focus();
}

function updateRepackPreview() {
    const preview = document.getElementById('repackPreview');
    const submitBtn = document.getElementById('submitBtn');
    const raw = document.getElementById('qtyBags').value;

    if (!repackInfo || raw === '') {
        preview.style.display = 'none';
        submitBtn.disabled = true;
        return;
    }

    const bags = Number(raw);
    const info = repackInfo;
    const kgNeeded = bags * info.kg_per_bag;

    preview.classList.remove('repack-error');
    preview.style.display = 'block';

    let error = '';
    if (!Number.isInteger(bags) || bags < 1) {
        error = 'Please enter a whole number of bags (minimum 1).';
    } else if (kgNeeded > info.max_kg) {
        error = `Maximum ${info.max_kg} kg can be added in one entry. ${bags} bags = ${formatRepackNumber(kgNeeded)} kg.`;
    } else if (kgNeeded > info.parent_available_kg) {
        error = `Not enough parent stock. ${bags} bags need ${formatRepackNumber(kgNeeded)} kg, but ${info.parent.name} has only ${formatRepackNumber(info.parent_available_kg)} kg.`;
    }

    if (error) {
        preview.classList.add('repack-error');
        preview.textContent = error;
        submitBtn.disabled = true;
        return;
    }

    preview.innerHTML = '';
    const lines = [
        `${bags} bag(s) × ${formatRepackNumber(info.kg_per_bag)} kg = ${formatRepackNumber(kgNeeded)} kg will be used`,
        `${info.parent.name}: ${formatRepackNumber(info.parent_available_kg)} kg → ${formatRepackNumber(info.parent_available_kg - kgNeeded)} kg`,
        `${info.child.name}: ${formatRepackNumber(info.child_bags)} → ${formatRepackNumber(info.child_bags + bags)} bags`
    ];
    lines.forEach(text => {
        const line = document.createElement('div');
        line.textContent = text;
        preview.appendChild(line);
    });
    submitBtn.disabled = false;
}

function handleRepackSubmit(e) {
    e.preventDefault();

    if (!repackInfo) return;

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    const formData = new FormData(document.getElementById('repackForm'));

    fetch(REPACK_API + 'stock-repack-save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            document.getElementById('qtyBags').value = '';
            fetchRepackInfo(); // refresh available kg / bags
        } else {
            alert('Error: ' + data.message);
            fetchRepackInfo();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving');
    })
    .finally(() => {
        submitBtn.textContent = 'Add Stock';
        updateRepackPreview();
    });
}

function resetRepackForm() {
    document.getElementById('productSearch').value = '';
    document.getElementById('productId').value = '';
    clearRepackInfo();
}