<?php
require_once '../../../../includes/dashboard.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$edit_id = $_GET['edit'] ?? null;
$is_edit = $edit_id !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Project Product Rates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --primary-light: #e7f1ff;
            --success: #28a745;
            --success-light: #d4edda;
            --error: #dc3545;
            --error-light: #f8d7da;
            --surface-0: #ffffff;
            --surface-1: #f8f9fa;
            --surface-2: #e9ecef;
            --surface-3: #dee2e6;
            --text: #212529;
            --text-secondary: #6c757d;
            --border: #dee2e6;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.12);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
        }

        .page-wrapper { max-width: 900px; margin: 0 auto; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 i { color: var(--primary); }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--surface-0);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }

        .back-btn:hover { background: var(--surface-2); transform: translateX(-2px); }

        .form-card {
            background: var(--surface-0);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 32px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            display: none;
        }

        .alert.success { background: var(--success-light); color: #155724; border: 1px solid #c3e6cb; }
        .alert.error   { background: var(--error-light);   color: #721c24; border: 1px solid #f5c6cb; }
        .alert.show    { display: flex; }

        /* Project selector at top */
        .project-selector {
            background: var(--primary-light);
            border: 1px solid #b8d4f8;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        .project-selector label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--primary-dark);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
        }

        .project-selector .searchable-dropdown { position: relative; }

        .project-selector .dropdown-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--primary);
            border-radius: 7px;
            font-size: 15px;
            font-family: inherit;
            background: var(--surface-0);
            color: var(--text);
            transition: all 0.2s;
        }

        .project-selector .dropdown-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .project-selector .dropdown-input:disabled {
            background: var(--surface-2);
            cursor: not-allowed;
            opacity: 0.8;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-light);
            border: 1px solid #b8d4f8;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 16px;
        }

        /* Table */
        .rates-table {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: visible;
            margin-bottom: 24px;
        }

        .rates-table-header {
            background: var(--primary-light);
            display: grid;
            grid-template-columns: 1fr 1fr 50px 50px;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border);
            font-weight: 600;
            font-size: 13px;
            color: var(--primary-dark);
            align-items: center;
        }

        .rate-row {
            display: grid;
            grid-template-columns: 1fr 1fr 50px 50px;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--surface-2);
            align-items: flex-end;
            transition: background 0.2s;
            position: relative;
        }

        .rate-row:hover { background: var(--surface-1); }
        .rate-row:last-child { border-bottom: none; }

        .rate-row-field { display: flex; flex-direction: column; }

        .rate-row-field label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .searchable-dropdown { position: relative; z-index: 10; }

        .dropdown-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            background: var(--surface-0);
            color: var(--text);
            transition: all 0.2s;
        }

        .dropdown-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .dropdown-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--surface-0);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 9999;
            display: none;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }

        .dropdown-options.show { display: block; }

        .dropdown-option {
            padding: 9px 12px;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 1px solid var(--surface-2);
            transition: background 0.15s;
        }

        .dropdown-option:hover { background: var(--primary-light); color: var(--primary-dark); }
        .dropdown-option:last-child { border-bottom: none; }

        .rate-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            background: var(--surface-0);
            color: var(--text);
            transition: all 0.2s;
        }

        .rate-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-icon {
            padding: 8px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            height: 38px;
        }

        .btn-add    { background: var(--success); }
        .btn-add:hover    { background: #218838; transform: scale(1.1); }
        .btn-delete { background: var(--error); }
        .btn-delete:hover { background: #c82333; transform: scale(1.1); }

        .add-row-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .add-row-btn:hover { background: var(--primary-dark); transform: translateY(-2px); }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            justify-content: flex-end;
        }

        .btn {
            padding: 11px 26px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-primary:disabled { background: #6c757d; cursor: not-allowed; transform: none; }

        .btn-secondary { background: var(--surface-2); color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--surface-3); transform: translateY(-2px); }

        @media (max-width: 600px) {
            .page-header { flex-direction: column; gap: 16px; align-items: flex-start; }
            .rates-table-header, .rate-row { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
            .btn, .add-row-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="page-header">
            <h1>
                <i class="fas fa-list"></i>
                <?php echo $is_edit ? 'Edit Project Product Rate' : 'Add Project Product Rates'; ?>
            </h1>
            <a href="project-product-rate-list.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="form-card">
            <div class="alert success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <span id="successMessage">Records saved successfully!</span>
            </div>
            <div class="alert error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorMessage">An error occurred</span>
            </div>

            <!-- Project selector at top -->
            <div class="project-selector">
                <label><i class="fas fa-project-diagram"></i> &nbsp;Select Project</label>
                <div class="searchable-dropdown">
                    <input type="text" id="projectSearchInput" class="dropdown-input" placeholder="Search project..." autocomplete="off">
                    <div class="dropdown-options" id="projectDropdownOptions"></div>
                    <input type="hidden" id="selectedProjectId">
                </div>
            </div>

            <form id="rateForm">
                <div class="stat-badge">
                    <i class="fas fa-table"></i> Total Rows: <span id="totalRows">0</span>
                </div>

                <button type="button" class="add-row-btn" id="addRowBtn">
                    <i class="fas fa-plus-circle"></i> Add New Row
                </button>

                <div class="rates-table">
                    <div class="rates-table-header">
                        <div>Product</div>
                        <div>Rate</div>
                        <div style="text-align:center">Add</div>
                        <div style="text-align:center">Del</div>
                    </div>
                    <div id="ratesContainer"></div>
                </div>

                <div class="form-actions">
                    <a href="project-product-rate-list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save All Records
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let projectsData = [];
        let productsData = [];
        let rowCounter   = 0;

        const editId = new URLSearchParams(window.location.search).get('edit');

        document.addEventListener('DOMContentLoaded', function () {
            Promise.all([loadProjectsAsync(), loadProductsAsync()]).then(() => {
                initProjectSelector();
                addRateRow();
                if (editId) loadEditData();
            });

            document.getElementById('addRowBtn').addEventListener('click', (e) => {
                e.preventDefault();
                addRateRow();
            });
            document.getElementById('rateForm').addEventListener('submit', handleSubmit);
        });

        function loadProjectsAsync() {
            return fetch('../../../../server/api/master_setup/project_product_rates/get-projects.php')
                .then(r => r.json())
                .then(d => { if (d.success) projectsData = d.projects || []; })
                .catch(err => console.error('Projects load error:', err));
        }

        function loadProductsAsync() {
            return fetch('../../../../server/api/master_setup/project_product_rates/get-products.php')
                .then(r => r.json())
                .then(d => { if (d.success) productsData = d.products || []; })
                .catch(err => console.error('Products load error:', err));
        }

        /* ── Project selector (top) ── */
        function initProjectSelector() {
            const input      = document.getElementById('projectSearchInput');
            const options    = document.getElementById('projectDropdownOptions');
            const hiddenId   = document.getElementById('selectedProjectId');

            options.innerHTML = projectsData.map(p =>
                `<div class="dropdown-option" data-value="${p.id}">${p.project_code} - ${p.project_name}</div>`
            ).join('');

            input.addEventListener('focus', () => options.classList.add('show'));
            input.addEventListener('click', () => options.classList.add('show'));

            input.addEventListener('input', () => {
                const q = input.value.toLowerCase();
                options.querySelectorAll('.dropdown-option').forEach(o => {
                    o.style.display = o.textContent.toLowerCase().includes(q) ? 'block' : 'none';
                });
                options.classList.add('show');
            });

            options.addEventListener('click', (e) => {
                if (e.target.classList.contains('dropdown-option')) {
                    hiddenId.value = e.target.dataset.value;
                    input.value    = e.target.textContent;
                    options.classList.remove('show');
                }
            });

            document.addEventListener('click', (e) => {
                if (e.target !== input && !options.contains(e.target)) {
                    options.classList.remove('show');
                }
            });
        }

        /* ── Rows ── */
        function addRateRow() {
            const container = document.getElementById('ratesContainer');
            const rowId     = rowCounter++;

            container.insertAdjacentHTML('beforeend', `
                <div class="rate-row" data-row="${rowId}">
                    <div class="rate-row-field">
                        <label>Product</label>
                        <div class="searchable-dropdown">
                            <input type="text" class="dropdown-input productSearch" data-row="${rowId}" placeholder="Search..." autocomplete="off">
                            <div class="dropdown-options productOptions" data-row="${rowId}"></div>
                            <input type="hidden" class="product" data-row="${rowId}">
                        </div>
                    </div>
                    <div class="rate-row-field">
                        <label>Rate</label>
                        <input type="number" class="rate-input rate" data-row="${rowId}" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <button type="button" class="btn-icon btn-add" onclick="addRateRow()" title="Add row">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="btn-icon btn-delete" onclick="removeRateRow(${rowId})" title="Delete row">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `);

            initRowDropdown(rowId);
            updateRowCount();
        }

        function removeRateRow(rowId) {
            const row = document.querySelector(`.rate-row[data-row="${rowId}"]`);
            if (!row) return;
            row.style.opacity   = '0';
            row.style.transform = 'translateX(-10px)';
            row.style.transition = 'all 0.25s';
            setTimeout(() => { row.remove(); updateRowCount(); }, 260);
        }

        function initRowDropdown(rowId) {
            const searchInput      = document.querySelector(`.productSearch[data-row="${rowId}"]`);
            const optionsContainer = document.querySelector(`.productOptions[data-row="${rowId}"]`);
            const hiddenInput      = document.querySelector(`.product[data-row="${rowId}"]`);

            optionsContainer.innerHTML = productsData.map(p =>
                `<div class="dropdown-option" data-value="${p.id}">${p.code} - ${p.name}</div>`
            ).join('');

            searchInput.addEventListener('focus', () => optionsContainer.classList.add('show'));
            searchInput.addEventListener('click', () => optionsContainer.classList.add('show'));

            searchInput.addEventListener('input', () => {
                const q = searchInput.value.toLowerCase();
                optionsContainer.querySelectorAll('.dropdown-option').forEach(o => {
                    o.style.display = o.textContent.toLowerCase().includes(q) ? 'block' : 'none';
                });
                optionsContainer.classList.add('show');
            });

            optionsContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('dropdown-option')) {
                    hiddenInput.value    = e.target.dataset.value;
                    searchInput.value    = e.target.textContent;
                    optionsContainer.classList.remove('show');
                }
            });

            document.addEventListener('click', (e) => {
                if (e.target !== searchInput && !optionsContainer.contains(e.target)) {
                    optionsContainer.classList.remove('show');
                }
            });
        }

        function updateRowCount() {
            document.getElementById('totalRows').textContent = document.querySelectorAll('.rate-row').length;
        }

        /* ── Submit ── */
        function handleSubmit(e) {
            e.preventDefault();
            hideAlerts();

            const projectId = document.getElementById('selectedProjectId').value;
            if (!projectId) {
                showError('Please select a project first');
                return;
            }

            const rows  = document.querySelectorAll('.rate-row');
            const rates = [];

            for (const row of rows) {
                const rowId     = row.dataset.row;
                const productId = document.querySelector(`.product[data-row="${rowId}"]`).value;
                const rateVal   = document.querySelector(`.rate[data-row="${rowId}"]`).value;

                if (!productId || !rateVal) {
                    showError('Please fill Product and Rate in all rows');
                    return;
                }

                rates.push({
                    project_id: parseInt(projectId),
                    product_id: parseInt(productId),
                    rate:        parseFloat(rateVal)
                });
            }

            if (rates.length === 0) {
                showError('Please add at least one row');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            if (editId) {
                submitSingleRate(rates[0], editId, submitBtn);
            } else {
                submitMultipleRates(rates, submitBtn);
            }
        }

        function submitSingleRate(rate, id, submitBtn) {
            fetch('../../../../server/api/master_setup/project_product_rates/update-rate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, project_id: rate.project_id, product_id: rate.product_id, rate: rate.rate })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Rate updated successfully!');
                    setTimeout(() => window.location.href = 'project-product-rate-list.php', 1500);
                } else {
                    showError(data.message || 'Error updating rate');
                    resetBtn(submitBtn);
                }
            })
            .catch(err => { showError('Error: ' + err.message); resetBtn(submitBtn); });
        }

        function submitMultipleRates(rates, submitBtn) {
            fetch('../../../../server/api/master_setup/project_product_rates/add-rate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ rates })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.added ? `${data.added} rate(s) saved successfully!` : 'Records saved!');
                    setTimeout(() => window.location.href = 'project-product-rate-list.php', 1500);
                } else {
                    showError(data.message || 'Error saving rates');
                    resetBtn(submitBtn);
                }
            })
            .catch(err => { showError('Error: ' + err.message); resetBtn(submitBtn); });
        }

        function resetBtn(btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save All Records';
        }

        function showSuccess(msg) {
            document.getElementById('successMessage').textContent = msg;
            document.getElementById('successAlert').classList.add('show');
        }

        function showError(msg) {
            document.getElementById('errorMessage').textContent = msg;
            document.getElementById('errorAlert').classList.add('show');
        }

        function hideAlerts() {
            document.getElementById('successAlert').classList.remove('show');
            document.getElementById('errorAlert').classList.remove('show');
        }

        /* ── Edit mode ── */
        function loadEditData() {
            fetch(`../../../../server/api/master_setup/project_product_rates/get-rate.php?id=${editId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.rate) populateEditForm(data.rate);
                })
                .catch(err => console.error('Edit load error:', err));
        }

        function populateEditForm(rate) {
            // Set project selector
            const project = projectsData.find(p => parseInt(p.id) === parseInt(rate.project_id));
            if (project) {
                document.getElementById('projectSearchInput').value = `${project.project_code} - ${project.project_name}`;
                document.getElementById('selectedProjectId').value  = rate.project_id;
                // Lock project in edit mode — only one record being edited
                document.getElementById('projectSearchInput').disabled = true;
            }

            // Set row 0 product + rate
            const product = productsData.find(p => parseInt(p.id) === parseInt(rate.product_id));
            if (product) {
                document.querySelector('.productSearch[data-row="0"]').value = `${product.code} - ${product.name}`;
                document.querySelector('.product[data-row="0"]').value        = rate.product_id;
            }
            document.querySelector('.rate[data-row="0"]').value = rate.rate;

            // Hide "Add row" button in edit mode
            document.getElementById('addRowBtn').style.display = 'none';
        }
    </script>
</body>
</html>
