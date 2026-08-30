document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('importForm');
    const fileInput = document.getElementById('importFile');
    const companySelect = document.getElementById('companySelect');
    const submitBtn = document.getElementById('submitBtn');
    const resetBtn = document.getElementById('resetBtn');
    const resultBox = document.getElementById('resultBox');
    const resultSummary = document.getElementById('resultSummary');
    const resultErrors = document.getElementById('resultErrors');

    const notification = document.getElementById('notification');
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationClose = document.getElementById('notificationClose');

    function showNotification(title, message, type = 'success') {
        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
        notification.className = 'notification';
        notification.classList.add(type, 'show');
        setTimeout(() => notification.classList.remove('show'), 5000);
    }
    notificationClose.addEventListener('click', () => notification.classList.remove('show'));

    // ---- Load companies into the dropdown ----
    fetch('../../../../server/api/customer_supplier/customer_supplier_both/get-companies.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && Array.isArray(data.companies)) {
                data.companies.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.company_code ? `${c.company_name} (${c.company_code})` : c.company_name;
                    companySelect.appendChild(opt);
                });
            } else {
                console.warn('Could not load companies:', data.message || 'Unknown error');
            }
        })
        .catch(() => {
            console.warn('Failed to fetch company list - the dropdown will only show the default option.');
        });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!fileInput.files.length) {
            showNotification('Validation Error', 'Please choose a file to import', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        if (companySelect.value) {
            formData.append('company_id', companySelect.value);
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
        resultBox.style.display = 'none';

        fetch('../../../../server/api/customer_supplier/customer_supplier_both/both-import.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                resultBox.style.display = 'block';
                resultSummary.innerHTML = `
                    <span style="color: var(--success, green); font-weight:600;">
                        <i class="fas fa-check-circle"></i> Imported: ${data.inserted}
                    </span>
                    &nbsp;|&nbsp;
                    <span style="color: var(--warning, #b58900); font-weight:600;">
                        <i class="fas fa-forward"></i> Skipped (duplicate): ${data.skipped}
                    </span>
                    &nbsp;|&nbsp;
                    <span style="color: var(--error, red); font-weight:600;">
                        <i class="fas fa-times-circle"></i> Errors: ${data.errors.length}
                    </span>
                `;
                if (data.errors.length) {
                    resultErrors.innerHTML = '<ul>' + data.errors.map(e => `<li>Row ${e.row}: ${e.message}</li>`).join('') + '</ul>';
                } else {
                    resultErrors.innerHTML = '';
                }
                showNotification('Import Complete', `${data.inserted} party records imported successfully`, 'success');
                fileInput.value = '';
            } else {
                showNotification('Error', data.message || 'Import failed', 'error');
            }
        })
        .catch(() => {
            showNotification('Error', 'Failed to import file', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-file-import"></i> Import Customer + Supplier';
        });
    });

    resetBtn.addEventListener('click', function () {
        form.reset();
        resultBox.style.display = 'none';
    });
});