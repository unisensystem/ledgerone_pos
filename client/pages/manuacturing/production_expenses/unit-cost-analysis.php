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
    <title>LedgerOne · Unit Cost Analysis</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/manufacturing/production_expenses/expense-list.css">
</head>
<body>
    <div class="app-container">
        <div class="page">
            <div class="header-actions">
                <div class="page-title">
                    <h1>Unit Cost Analysis</h1>
                    <p>View per-unit production costs by batch</p>
                </div>
                <button class="btn btn-secondary" onclick="window.location.href='expense-list.php'"><i class="fas fa-arrow-left"></i> Back</button>
            </div>

            <div class="filters-bar">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search Product</label>
                    <input type="text" id="searchTerm" placeholder="Product name or SKU">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-secondary" onclick="loadCosts()" style="width:100%;"><i class="fas fa-search"></i> Search</button>
                </div>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Total Qty Produced</th>
                                <th>Batches</th>
                                <th>Avg Material Cost/Unit</th>
                                <th>Avg Overhead Cost/Unit</th>
                                <th>Avg Total Cost/Unit</th>
                                <th>Last Production</th>
                            </tr>
                        </thead>
                        <tbody id="costTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="toast" id="toast"><i class="fas fa-check-circle" id="toastIcon"></i>
            <div><strong id="toastTitle"></strong>
                <div id="toastMsg"></div>
            </div>
        </div>
    </div>

    <script>
        async function loadCosts() {
            const search = document.getElementById('searchTerm').value.trim();
            
            try {
                const response = await fetch(`../../../../server/api/manufacturing/production_expenses/unit-cost-analysis.php?search=${encodeURIComponent(search)}`);
                const result = await response.json();
                
                if (result.success) {
                    renderCosts(result.data);
                } else {
                    showToast('error', 'Error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('error', 'Error', 'Failed to load cost data');
            }
        }

        function renderCosts(data) {
            const tbody = document.getElementById('costTableBody');
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fas fa-inbox"></i><br/>No completed production batches found</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(row => `
                <tr>
                    <td><strong>${row.product_name}</strong></td>
                    <td>${row.sku_code || '—'}</td>
                    <td>${parseFloat(row.total_qty_produced).toLocaleString()} ${row.uom_name || 'Units'}</td>
                    <td>${row.batch_count} batches</td>
                    <td class="amount-cell">Rs. ${parseFloat(row.avg_material_cost || 0).toFixed(2)}</td>
                    <td class="amount-cell">Rs. ${parseFloat(row.avg_overhead_cost || 0).toFixed(2)}</td>
                    <td class="amount-cell"><strong>Rs. ${parseFloat(row.avg_unit_cost).toFixed(2)}</strong></td>
                    <td>${formatDate(row.last_production_date)}</td>
                </tr>
            `).join('');
        }

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function showToast(type, title, msg) {
            const t = document.getElementById('toast');
            const icon = t.querySelector('#toastIcon');
            icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            document.getElementById('toastTitle').innerText = title;
            document.getElementById('toastMsg').innerText = msg;
            t.classList.remove('success','error');
            t.classList.add(type);
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3800);
        }

        window.onload = () => loadCosts();
    </script>
</body>
</html>
