document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('importForm');
    const companySelect = document.getElementById('company');
    const fileInput = document.getElementById('importFile');
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

    $(document).ready(function () {
        $('#company').select2({ placeholder: 'Select Company', allowClear: false });
    });

    loadCompanies();

    function loadCompanies() {
        fetch('../../../../server/api/customer_supplier/customers/get-companies.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    data.companies.forEach(c => {
                        companySelect.appendChild(new Option(c.company_name, c.id));
                    });
                    if (data.companies.length === 1) {
                        $('#company').val(data.companies[0].id).trigger('change');
                    }
                }
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!companySelect.value) {
            showNotification('Validation Error', 'Please select a company', 'error');
            return;
        }
        if (!fileInput.files.length) {
            showNotification('Validation Error', 'Please choose a file to import', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('companyId', companySelect.value);
        formData.append('file', fileInput.files[0]);

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
        resultBox.style.display = 'none';

        fetch('../../../../server/api/customer_supplier/customers/customer-import.php', {
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
                showNotification('Import Complete', `${data.inserted} customers imported successfully`, 'success');
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
            submitBtn.innerHTML = '<i class="fas fa-file-import"></i> Import Customers';
        });
    });

    resetBtn.addEventListener('click', function () {
        form.reset();
        resultBox.style.display = 'none';
        $('#company').val('').trigger('change');
    });
});