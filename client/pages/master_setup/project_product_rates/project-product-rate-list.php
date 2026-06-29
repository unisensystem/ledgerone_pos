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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Project Product Rates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --success: #28a745;
            --error: #dc3545;
            --warning: #ffc107;
            --surface-0: #ffffff;
            --surface-1: #f8f9fa;
            --surface-2: #e9ecef;
            --surface-3: #dee2e6;
            --text: #212529;
            --subtext: #6c757d;
            --border-default: #dee2e6;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--surface-1);
            color: var(--text);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .add-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .add-btn:hover {
            background: var(--primary-dark);
        }

        .table-container {
            background: var(--surface-0);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--surface-2);
            border-bottom: 1px solid var(--border-default);
        }

        th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: var(--text);
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--surface-2);
        }

        tbody tr:hover {
            background: var(--surface-1);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-default);
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            background: var(--surface-0);
            color: var(--text);
            text-decoration: none;
        }

        .btn-edit {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-edit:hover {
            background: var(--primary);
            color: white;
        }

        .btn-delete {
            border-color: var(--error);
            color: var(--error);
        }

        .btn-delete:hover {
            background: var(--error);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--subtext);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--subtext);
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--surface-2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--surface-0);
            padding: 32px;
            border-radius: 8px;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-body {
            margin-bottom: 24px;
            color: var(--subtext);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--border-default);
        }

        .btn-secondary:hover {
            background: var(--surface-3);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
        }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--border-default);
            border-radius: 6px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Project Product Rates</h1>
            <a href="project-product-rate-add.php" class="add-btn">
                <i class="fas fa-plus"></i> Add New Rate
            </a>
        </div>

        <div class="alert success" id="successAlert" style="display: none;"></div>
        <div class="alert error" id="errorAlert" style="display: none;"></div>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search project, product...">
        </div>

        <div class="table-container">
            <div class="loading" id="loadingState">
                <div class="spinner"></div>
                <p>Loading data...</p>
            </div>

            <table id="ratesTable" style="display: none;">
                <thead>
                    <tr>
                        <th style="width: 30%;">Project</th>
                        <th style="width: 35%;">Product</th>
                        <th style="width: 15%;">Rate</th>
                        <th style="width: 20%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                </tbody>
            </table>

            <div class="empty-state" id="emptyState" style="display: none;">
                <i class="fas fa-inbox"></i>
                <p>No project product rates found</p>
                <a href="project-product-rate-add.php" class="add-btn">
                    <i class="fas fa-plus"></i> Add First Rate
                </a>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Delete Rate</h2>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this project product rate? This action cannot be undone.
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-primary" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        let allRates = [];
        let selectedRateId = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadRates();
            
            document.getElementById('searchInput').addEventListener('input', (e) => {
                filterRates(e.target.value);
            });

            document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);
        });

        function loadRates() {
            document.getElementById('loadingState').style.display = 'block';
            document.getElementById('ratesTable').style.display = 'none';
            document.getElementById('emptyState').style.display = 'none';

            fetch('../../../../server/api/master_setup/project_product_rates/get-all-rates.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        allRates = data.rates;
                        renderTable(allRates);
                    } else {
                        showError(data.message || 'Error loading rates');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showError('Error loading rates: ' + err.message);
                })
                .finally(() => {
                    document.getElementById('loadingState').style.display = 'none';
                });
        }

        function renderTable(rates) {
            const tbody = document.getElementById('tableBody');
            const table = document.getElementById('ratesTable');
            const emptyState = document.getElementById('emptyState');

            if (rates.length === 0) {
                table.style.display = 'none';
                emptyState.style.display = 'block';
                return;
            }

            tbody.innerHTML = rates.map(rate => `
                <tr>
                    <td>${rate.project_code} - ${rate.project_name}</td>
                    <td>${rate.product_code} - ${rate.product_name}</td>
                    <td>${parseFloat(rate.rate).toFixed(2)}</td>
                    <td>
                        <div class="actions">
                            <a href="project-product-rate-add.php?edit=${rate.id}" class="btn-sm btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn-sm btn-delete" title="Delete" onclick="openDeleteModal(${rate.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            table.style.display = 'table';
            emptyState.style.display = 'none';
        }

        function filterRates(searchTerm) {
            const term = searchTerm.toLowerCase();
            const filtered = allRates.filter(rate => 
                rate.project_name.toLowerCase().includes(term) ||
                rate.project_code.toLowerCase().includes(term) ||
                rate.product_name.toLowerCase().includes(term) ||
                rate.product_code.toLowerCase().includes(term)
            );
            renderTable(filtered);
        }

        function openDeleteModal(id) {
            selectedRateId = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            selectedRateId = null;
        }

        function confirmDelete() {
            if (!selectedRateId) return;

            const btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

            fetch('../../../../server/api/master_setup/project_product_rates/delete-rate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: selectedRateId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccess('Rate deleted successfully');
                        setTimeout(() => loadRates(), 1500);
                        closeDeleteModal();
                    } else {
                        showError(data.message || 'Error deleting rate');
                    }
                })
                .catch(err => {
                    showError('Error: ' + err.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = 'Delete';
                });
        }

        function showSuccess(message) {
            const alert = document.getElementById('successAlert');
            alert.textContent = message;
            alert.style.display = 'block';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 2000);
        }

        function showError(message) {
            const alert = document.getElementById('errorAlert');
            alert.textContent = message;
            alert.style.display = 'block';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 2000);
        }
    </script>
</body>
</html>
