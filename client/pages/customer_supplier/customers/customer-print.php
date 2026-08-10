<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}

// Get user's full name and base currency
require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$user_id, $tenant_id]);
$user = $stmt->fetch();
$user_name = $user['full_name'] ?? 'System User';

// Get base currency symbol
$stmt = $pdo->prepare("
    SELECT c.symbol 
    FROM tenant_currencies tc 
    JOIN ledgerone_public.currencies c ON tc.currency_id = c.id 
    WHERE tc.tenant_id = ? AND tc.is_base_currency = 1
");
$stmt->execute([$tenant_id]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - Customers List (Print)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: white;
        }

        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .print-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .customers-table th,
        .customers-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .customers-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
        }

        .customers-table td {
            font-size: 10px;
        }

        .customer-code {
            font-family: monospace;
            font-weight: bold;
        }

        .balance-positive {
            color: #2fbf71;
            font-weight: bold;
        }

        .balance-negative {
            color: #e34f4f;
            font-weight: bold;
        }

        .status-active {
            color: #2fbf71;
        }

        .status-blacklisted {
            color: #e34f4f;
            font-weight: bold;
        }

        .print-footer {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-size: 10px;
            text-align: center;
            color: #666;
        }

        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }

        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .customers-table {
                page-break-inside: avoid;
            }
            
            .customers-table th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
            }
        }

        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .btn {
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #1f7bff;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="customer-list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="print-header">
        <div class="company-name">LedgerOne ERP</div>
        <div class="report-title">Customers List</div>
    </div>

    <div class="print-info">
        <div>
            <strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?>
        </div>
        <div>
            <strong>User:</strong> <?php echo $user_name; ?>
        </div>
    </div>

    <div class="summary-stats" id="summaryStats">
        <div class="stat-item">
            <div class="stat-value" id="totalCustomers">-</div>
            <div class="stat-label">Total Customers</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" id="activeCustomers">-</div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" id="blacklistedCustomers">-</div>
            <div class="stat-label">Blacklisted</div>
        </div>
        <div class="stat-item">
            <div class="stat-value" id="totalReceivables">-</div>
            <div class="stat-label">Total Receivables</div>
        </div>
    </div>

    <table class="customers-table">
        <thead>
            <tr>
                <th>Customer Code</th>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Current Balance</th>
                <th>Status</th>
                <th>Created Date</th>
            </tr>
        </thead>
        <tbody id="customersTableBody">
            <!-- Customers will be loaded here -->
        </tbody>
    </table>

    <div class="print-footer">
        <p>This report was generated by FuelingSys ERP on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p>© <?php echo date('Y'); ?> FuelingSys ERP. All rights reserved.</p>
    </div>

    <script>
        const currencySymbol = '<?php echo $currency_symbol; ?>';
        
        document.addEventListener('DOMContentLoaded', function() {
            loadCustomersForPrint();
        });

        function loadCustomersForPrint() {
            fetch('../../../../server/api/customer_supplier/customers/customer-list.php?limit=1000')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderCustomersForPrint(data.customers);
                        updatePrintStats(data.stats);
                    } else {
                        console.error('Failed to load customers:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading customers:', error);
                });
        }

        function renderCustomersForPrint(customers) {
            const tbody = document.getElementById('customersTableBody');
            tbody.innerHTML = '';

            customers.forEach(customer => {
                const balance = parseFloat(customer.current_balance);
                const balanceClass = balance > 0 ? 'balance-positive' :
                    balance < 0 ? 'balance-negative' : '';

                const balanceText = balance > 0 ? `${currencySymbol}${balance.toFixed(2)}` :
                    balance < 0 ? `-${currencySymbol}${Math.abs(balance).toFixed(2)}` : `${currencySymbol}0.00`;
                    
                const status = customer.is_blacklisted ? 'blacklisted' : 'active';
                const statusClass = `status-${status}`;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><span class="customer-code">${customer.customer_code}</span></td>
                    <td>${customer.customer_name}</td>
                    <td>${customer.primary_phone || '-'}</td>
                    <td>${customer.email || '-'}</td>
                    <td><span class="${balanceClass}">${balanceText}</span></td>
                    <td><span class="${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                    <td>${new Date(customer.created_at).toLocaleDateString()}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function updatePrintStats(stats) {
            document.getElementById('totalCustomers').textContent = stats.total_customers;
            document.getElementById('activeCustomers').textContent = stats.active_customers;
            document.getElementById('blacklistedCustomers').textContent = stats.blacklisted_customers;
            document.getElementById('totalReceivables').textContent = `${currencySymbol}${parseFloat(stats.total_receivables || 0).toFixed(2)}`;
        }
    </script>
</body>
</html>